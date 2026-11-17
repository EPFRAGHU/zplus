<?php

namespace App\Services\Approvals;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\AttendanceSheet;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Payroll\BankLetter;
use App\Services\Payroll\SalaryPayoutSheet;
use App\Support\Activity\Activity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Maker–checker (owner, 17 Nov 2026): from October 2026, monthly attendance, sub-bills, tax invoices and each agency's
 * monthly salary payout / bank letter must be approved by the owner (a Super Admin) before they can go further:
 *  - attendance approved      → the sub-bill is made
 *  - sub-bill / tax invoice    → can be marked submitted, e-mailed, put in the GeM bill pack
 *  - payout (agency + month)   → salary payout sheets and bank letters can be downloaded
 * Strict four-eyes: whoever sent it can never approve it. An approval holds the figures as they were when sent
 * (fingerprint); if anything changes afterwards, it must be sent and approved again.
 */
final class Approvals
{
    public const START = '2026-10-01';   // default; config('approvals.start')

    public function __construct(private readonly SalaryPayoutSheet $payouts, private readonly BankLetter $letters) {}

    public static function applies(CarbonInterface $month): bool
    {
        return $month->copy()->startOfMonth()->toDateString() >= config('approvals.start', self::START);
    }

    public static function canApprove(?User $user): bool
    {
        return (bool) $user?->isSuperAdmin();
    }

    // ---------------------------------------------------------------- state

    /** not_needed | not_sent | pending | approved | returned | changed (approved, but the figures changed since). */
    public function state(string $type, AttendanceSheet|Invoice|TaxInvoice|array $subject): string
    {
        [$month, $where] = $this->key($type, $subject);
        if (! self::applies($month)) {
            return 'not_needed';
        }
        $latest = $this->latest($type, $where);
        if (! $latest) {
            // Made and sent out (or billed) before approvals began: left exactly as they are.
            return $this->grandfathered($type, $subject) ? 'not_needed' : 'not_sent';
        }

        return match ($latest->status) {
            'pending' => 'pending',
            'returned' => 'returned',
            'approved' => $latest->fingerprint === $this->fingerprint($type, $subject)[0] ? 'approved' : 'changed',
            default => 'not_sent',
        };
    }

    public function cleared(string $type, AttendanceSheet|Invoice|TaxInvoice|array $subject): bool
    {
        return in_array($this->state($type, $subject), ['not_needed', 'approved'], true);
    }

    /** Payout of one project sheet: covered by the agency-month approval when that sheet's figures are unchanged. */
    public function payoutClearedFor(AttendanceSheet $sheet): bool
    {
        $sheet->loadMissing('project.contract.agency');
        if (! self::applies($sheet->month)) {
            return true;
        }
        $latest = $this->latest('payout', ['agency_id' => $sheet->project->contract->agency_id, 'month' => $sheet->month->toDateString()]);
        if (! $latest || $latest->status !== 'approved') {
            return false;
        }

        return ($latest->details[$sheet->id] ?? null) === $this->sheetPayoutHash($sheet);
    }

    public function latest(string $type, array $where): ?Approval
    {
        return Approval::query()->with('submitter', 'decider')->where('type', $type)->where($where)->latest('id')->first();
    }

    public function history(string $type, AttendanceSheet|Invoice|TaxInvoice|array $subject)
    {
        [, $where] = $this->key($type, $subject);

        return Approval::query()->with('submitter', 'decider')->where('type', $type)->where($where)->latest('id')->limit(10)->get();
    }

    // ---------------------------------------------------------------- actions

    public function send(string $type, AttendanceSheet|Invoice|TaxInvoice|array $subject, User $maker): Approval
    {
        [$month, $where, $agencyId, $label] = $this->key($type, $subject);
        if (! self::applies($month)) {
            throw new RuntimeException('Approval is not needed for months before October 2026.');
        }
        $this->assertSendable($type, $subject);
        [$fingerprint, $details] = $this->fingerprint($type, $subject);

        return DB::transaction(function () use ($type, $where, $month, $agencyId, $label, $fingerprint, $details, $maker) {
            $open = Approval::query()->where('type', $type)->where($where)->whereIn('status', ['pending', 'approved'])->lockForUpdate()->get();
            if ($open->contains(fn ($a) => $a->fingerprint === $fingerprint)) {
                throw new RuntimeException('Already sent with these same figures. Nothing has changed since.');
            }
            $open->each(fn ($a) => $a->update(['status' => 'superseded']));
            $approval = Approval::query()->create($where + ['type' => $type, 'agency_id' => $agencyId, 'month' => $month->toDateString(), 'label' => $label,
                'fingerprint' => $fingerprint, 'details' => $details, 'status' => 'pending', 'submitted_by' => $maker->id, 'submitted_at' => now()]);
            Activity::log('status', null, [], "Sent for approval: {$label}", section: 'Approvals', label: $label);

            return $approval;
        });
    }

    /** @return string message */
    public function approve(Approval $approval, User $checker): string
    {
        $this->assertDecidable($approval, $checker);
        if (in_array($approval->type, Approval::STAFF_TYPES, true)) {
            return DB::transaction(function () use ($approval, $checker) {
                $message = app(StaffChanges::class)->apply($approval);
                $approval->update(['status' => 'approved', 'decided_by' => $checker->id, 'decided_at' => now()]);
                Activity::log('status', null, [], "Approved: {$approval->label}", section: 'Approvals', label: $approval->label);

                return "Approved. {$message}";
            });
        }
        $subject = $this->subject($approval);
        if ($this->fingerprint($approval->type, $subject)[0] !== $approval->fingerprint) {
            throw new RuntimeException('This was changed after it was sent. Ask for it to be sent again, then approve the new figures.');
        }
        if ($approval->type === 'tax_invoice') {
            $open = $subject->subBills->reject(fn ($b) => $this->cleared('sub_bill', $b));
            if ($open->isNotEmpty()) {
                throw new RuntimeException('Approve its sub-bills first: '.$open->pluck('invoice_no')->implode(', ').'.');
            }
        }
        $message = "Approved: {$approval->label}.";
        DB::transaction(function () use ($approval, $checker, $subject, &$message) {
            $approval->update(['status' => 'approved', 'decided_by' => $checker->id, 'decided_at' => now()]);
            Activity::log('status', null, [], "Approved: {$approval->label}", section: 'Approvals', label: $approval->label);
            if ($approval->type === 'attendance' && ! $subject->invoice()->exists()) {
                // Approved attendance: the sub-bill is made now (as a draft, by the person who sent the month).
                $invoice = app(InvoiceGenerator::class)->generate($subject->fresh(), $approval->submitter ?? $checker);
                $message .= " Sub-bill {$invoice->invoice_no} made.";
            }
        });

        return $message;
    }

    public function returnBack(Approval $approval, User $checker, string $reason): string
    {
        $this->assertDecidable($approval, $checker);
        $message = "Returned: {$approval->label}.";
        DB::transaction(function () use ($approval, $checker, $reason, &$message) {
            $approval->update(['status' => 'returned', 'decided_by' => $checker->id, 'decided_at' => now(), 'reason' => $reason]);
            Activity::log('status', null, [], "Returned: {$approval->label} – {$reason}", section: 'Approvals', label: $approval->label);
            if ($approval->type === 'attendance' && ($sheet = $approval->sheet) && $sheet->isLocked() && ! $sheet->invoice()->exists()) {
                // Back to the maker: the month opens again for correction.
                $sheet->forceFill(['status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();
                $message .= ' The month is open again for correction.';
            }
        });

        return $message;
    }

    // ---------------------------------------------------------------- internals

    private function assertDecidable(Approval $approval, User $checker): void
    {
        if (! self::canApprove($checker)) {
            throw new RuntimeException('Only the owner (Super Admin) can approve or return.');
        }
        if ($approval->status !== 'pending') {
            throw new RuntimeException('This is no longer waiting for approval.');
        }
        if ((int) $approval->submitted_by === (int) $checker->id) {
            throw new RuntimeException('You sent this yourself, so another owner must approve it (four-eyes rule).');
        }
        if (! $this->subject($approval)) {
            throw new RuntimeException('The record no longer exists.');
        }
    }

    private function assertSendable(string $type, $subject): void
    {
        if ($type === 'attendance' && ! $subject->isLocked()) {
            throw new RuntimeException('Lock the month first.');
        }
        if ($type === 'payout' && $this->payoutSheets($subject)->isEmpty()) {
            throw new RuntimeException('No locked attendance for this agency and month.');
        }
    }

    private function grandfathered(string $type, $subject): bool
    {
        return match ($type) {
            'attendance' => $subject->invoice()->exists(),                                 // billed before approvals began
            'sub_bill', 'tax_invoice' => in_array($subject->status, ['submitted', 'paid'], true),
            default => false,
        };
    }

    /** @return array{0: CarbonImmutable, 1: array, 2: int, 3: string} [month, where, agency id, label] */
    private function key(string $type, $subject): array
    {
        return match ($type) {
            'attendance' => (function () use ($subject) {
                $subject->loadMissing('project.contract');

                return [CarbonImmutable::parse($subject->month), ['attendance_sheet_id' => $subject->id], (int) $subject->project->contract->agency_id,
                    "Attendance {$subject->project->name} ({$subject->project->code}), ".$subject->month->format('F Y')];
            })(),
            'sub_bill' => [CarbonImmutable::parse($subject->month), ['invoice_id' => $subject->id], (int) $subject->agency_id,
                "Sub-bill {$subject->invoice_no}, ".$subject->month->format('F Y')],
            'tax_invoice' => [CarbonImmutable::parse($subject->month), ['tax_invoice_id' => $subject->id], (int) $subject->agency_id,
                "Tax invoice {$subject->number}, ".$subject->month->format('F Y')],
            'payout' => [CarbonImmutable::parse($subject['month'])->startOfMonth(),
                ['agency_id' => $subject['agency']->id, 'month' => CarbonImmutable::parse($subject['month'])->startOfMonth()->toDateString()], (int) $subject['agency']->id,
                "Salary payout & bank letter {$subject['agency']->name}, ".CarbonImmutable::parse($subject['month'])->format('F Y')],
        };
    }

    private function subject(Approval $a): mixed
    {
        return match ($a->type) {
            'attendance' => $a->sheet,
            'sub_bill' => $a->invoice,
            'tax_invoice' => $a->taxInvoice,
            'payout' => $a->agency ? ['agency' => $a->agency, 'month' => $a->month] : null,
            'new_employee', 'employee_change' => $a->employee,
            'post_rate' => $a->post,
            default => null,
        };
    }

    /** @return array{0: string, 1: ?array} */
    public function fingerprint(string $type, $subject): array
    {
        return match ($type) {
            'attendance' => [hash('sha256', json_encode([$subject->status, $subject->holidays, $subject->certificate_path,
                $subject->entries()->orderBy('employee_id')->get(['employee_id', 'codes', 'paid_days'])->map(fn ($e) => [$e->employee_id, $e->codes, (string) $e->paid_days])->all()])), null],
            'sub_bill' => [hash('sha256', json_encode([$subject->invoice_no, (string) $subject->rounded_total,
                $subject->lines()->orderBy('id')->get(['employee_id', 'paid_days', 'amount', 'epf', 'esic'])->map(fn ($l) => array_map('strval', $l->only(['employee_id', 'paid_days', 'amount', 'epf', 'esic'])))->all()])), null],
            'tax_invoice' => [hash('sha256', json_encode([$subject->number, (string) $subject->rounded_total, $subject->subBills()->orderBy('id')->pluck('id')->all()])), null],
            'payout' => (function () use ($subject) {
                $details = $this->payoutSheets($subject)->mapWithKeys(fn ($s) => [$s->id => $this->sheetPayoutHash($s)])->all();
                ksort($details);

                return [hash('sha256', json_encode($details)), $details];
            })(),
        };
    }

    private function payoutSheets(array $subject)
    {
        [$locked] = $this->letters->sheets($subject['agency'], CarbonImmutable::parse($subject['month'])->startOfMonth());

        return $locked;
    }

    /** Net pay and bank account of everyone on one locked sheet. */
    private function sheetPayoutHash(AttendanceSheet $sheet): string
    {
        return hash('sha256', json_encode($this->payouts->lines($sheet)->map(fn ($l) => [$l['employee']->id, (string) $l['net'],
            $l['employee']->account_number, $l['employee']->ifsc, $l['employee']->bank_name])->values()->all()));
    }
}
