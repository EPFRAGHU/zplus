<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class AttendanceService
{
    /** Opens (or returns) the month's sheet, pre-filled for every active employee on the project's posts. */
    public function open(Project $project, CarbonImmutable $month, array $holidays = []): AttendanceSheet
    {
        $month = $month->startOfMonth();

        return DB::transaction(function () use ($project, $month, $holidays) {
            $sheet = AttendanceSheet::query()->firstOrCreate(
                ['project_id' => $project->id, 'month' => $month->toDateString()],
                ['holidays' => implode(',', $holidays), 'status' => 'draft'],
            );

            if (! $sheet->isLocked()) {
                $existing = $sheet->entries()->pluck('employee_id')->all();
                $employees = Employee::query()
                    ->whereIn('post_id', $project->posts()->pluck('id'))
                    ->whereIn('status', ['active', 'notice'])
                    ->where(fn ($q) => $q->whereNull('exit_date')->orWhere('exit_date', '>=', $month->toDateString()))   // left before this month
                    ->whereNotIn('id', $existing)
                    ->get();

                foreach ($employees as $employee) {
                    $codes = AttendanceCodes::defaultMonth($month, $sheet->holidayDays(), $project->saturday_off ?? true);
                    $codes = $this->blankOutsideEmployment($codes, $month, $employee);
                    $sheet->entries()->create([
                        'employee_id' => $employee->id,
                        'codes' => $codes,
                        'paid_days' => AttendanceCodes::paidDays($codes),
                    ]);
                }
            }

            return $sheet->load('entries.employee.post', 'project.contract');
        });
    }

    /**
     * @param  array<int, array<int,string>>  $codesByEntry  entry id => [day => code]
     * Once the PI has submitted the month, every code the agency changes is recorded (who, when, old → new).
     */
    public function save(AttendanceSheet $sheet, array $codesByEntry, ?User $by = null): int
    {
        $this->assertEditable($sheet);
        $days = $sheet->month->daysInMonth;
        $record = $sheet->isSubmitted();

        return DB::transaction(function () use ($sheet, $codesByEntry, $days, $record, $by) {
            $changed = 0;
            $log = [];
            foreach ($sheet->entries()->with('employee')->get() as $entry) {
                if (! isset($codesByEntry[$entry->id])) {
                    continue;
                }
                $codes = [];
                for ($d = 1; $d <= $days; $d++) {
                    $codes[$d] = strtoupper(trim((string) ($codesByEntry[$entry->id][$d] ?? 'A')));
                }
                $codes = $entry->employee ? $this->blankOutsideEmployment($codes, $sheet->month, $entry->employee) : $codes;   // nothing is paid after the last working day
                for ($d = 1; $d <= $days; $d++) {
                    $old = strtoupper((string) ($entry->codes[$d] ?? $entry->codes[(string) $d] ?? 'A'));
                    if ($old !== $codes[$d] && count($log) < 200) {
                        $log[($entry->employee?->name ?? 'Staff').' · day '.$d] = [$old, $codes[$d]];
                    }
                    if ($record && $old !== $codes[$d]) {
                        $sheet->changes()->create(['employee_id' => $entry->employee_id, 'employee_name' => $entry->employee?->name ?? '',
                            'day' => $d, 'old_code' => $old, 'new_code' => $codes[$d], 'user_id' => $by?->id]);
                        $changed++;
                    }
                }
                $entry->update(['codes' => $codes, 'paid_days' => AttendanceCodes::paidDays($codes)]);
            }
            if ($log) {
                \App\Support\Activity\Activity::log('updated', $sheet, $log, count($log).' attendance '.\Illuminate\Support\Str::plural('code', count($log)).' changed', $by);
            }

            return $changed;
        });
    }

    /** The PI submits the month: it is frozen for the PI and the certificate PDF is made exactly as submitted. */
    public function submit(AttendanceSheet $sheet, User $pi): void
    {
        if (! in_array($sheet->status, ['draft'], true)) {
            throw new RuntimeException('This month has already been submitted.');
        }
        if ($sheet->entries()->count() === 0) {
            throw new RuntimeException('There is no one on this sheet yet. Ask the agency to link the staff to your project.');
        }
        $sheet->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $pi->id])->save();
        $path = sprintf('attendance-submitted/%s/%s-%s-%s.pdf', $sheet->month->format('Y-m'), \App\Support\Codes::fileSafe($sheet->project->code), $sheet->id, now()->format('YmdHis'));
        Storage::disk('local')->put($path, $this->certificatePdf($sheet->fresh()));
        $sheet->forceFill(['submitted_pdf_path' => $path])->save();
    }

    /** The Monthly Attendance and Working Certificate (no bank details: it goes to the PI). */
    public function certificatePdf(AttendanceSheet $sheet, bool $showBank = false): string
    {
        $sheet->loadMissing('entries.employee.post', 'project.contract.client', 'project.contract.agency', 'submitter', 'changes');

        return Pdf::loadView('pdf.attendance-certificate', ['sheet' => $sheet, 'showBank' => $showBank])->setPaper('a4', 'landscape')->output();
    }

    public function lock(AttendanceSheet $sheet, User $user, ?string $certificatePath): void
    {
        $this->assertEditable($sheet);
        if ($sheet->entries()->count() === 0) {
            throw new RuntimeException('There is no one on this sheet. Link employees to the project posts first.');
        }
        $sheet->forceFill([
            'status' => 'locked',
            'certificate_path' => $certificatePath ?? $sheet->certificate_path,
            'locked_at' => now(),
            'locked_by' => $user->id,
        ])->save();
        // Staff whose last working day falls in this month are Inactive from next month (owner, 21 Nov 2026).
        Employee::query()->whereIn('id', $sheet->entries()->pluck('employee_id'))->whereNotNull('exit_date')
            ->where('exit_date', '<=', $sheet->month->endOfMonth()->toDateString())->where('status', '<>', 'inactive')
            ->get()->each(fn (Employee $e) => $e->update(['status' => 'inactive']));
    }

    /**
     * The PI (or the agency) records a resignation: the last working day of staff on this sheet, within this month.
     * Days after it are cleared on save. A blank date removes a resignation entered for this month.
     *
     * @param  array<int|string, ?string>  $exits  entry id => Y-m-d or blank
     * @return array<int, string> names of staff whose date changed
     */
    public function setExits(AttendanceSheet $sheet, array $exits): array
    {
        $this->assertEditable($sheet);
        $first = $sheet->month->startOfMonth();
        $last = $sheet->month->endOfMonth();
        $changed = [];
        foreach ($sheet->entries()->with('employee')->whereIn('id', array_keys($exits))->get() as $entry) {
            $e = $entry->employee;
            $raw = $exits[$entry->id] ?? null;
            $date = filled($raw) ? CarbonImmutable::parse($raw)->startOfDay() : null;
            if (! $e) {
                continue;
            }
            if ($date && ($date->lt($first) || $date->gt($last))) {
                throw new RuntimeException("The last working day of {$e->name} must be in {$sheet->month->format('F Y')}.");
            }
            if ($date && $e->joining_date && $date->lt($e->joining_date)) {
                throw new RuntimeException("The last working day of {$e->name} is before their joining date.");
            }
            $current = $e->exit_date ? CarbonImmutable::parse($e->exit_date)->startOfDay() : null;
            if (! $date && (! $current || $current->lt($first) || $current->gt($last))) {
                continue;   // a blank box only clears a date of this month
            }
            if ($current?->toDateString() === $date?->toDateString()) {
                continue;
            }
            $e->update(['exit_date' => $date?->toDateString()]);
            $changed[] = $e->name;
        }

        return $changed;
    }

    public function unlock(AttendanceSheet $sheet): void
    {
        if ($sheet->invoice()->exists()) {
            throw new RuntimeException('A bill was made from this sheet. Delete the draft bill before unlocking.');
        }
        $sheet->forceFill(['status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();
    }

    /**
     * Resets one project's month completely, e.g. a month made as an example (owner, 16 Nov 2026; Admin only):
     * its sub-bill (any status except paid) is removed and taken out of its tax invoice (a tax invoice left empty is
     * deleted, otherwise worked out again), and the attendance sheet with every employee's codes, the corrections
     * and the uploaded PDFs is deleted, so the month can be started afresh. The work order's bill number for the
     * month is kept, so the next sub-bill gets it again. Returns a summary for the Activity log.
     */
    public function reset(AttendanceSheet $sheet): string
    {
        $invoice = $sheet->invoice()->with('taxInvoice')->first();
        if ($invoice && $invoice->status === 'paid') {
            throw new RuntimeException("Sub-bill {$invoice->invoice_no} is paid, so this month cannot be reset.");
        }
        if ($invoice?->taxInvoice && $invoice->taxInvoice->status === 'paid') {
            throw new RuntimeException("Tax invoice {$invoice->taxInvoice->number} is paid, so this month cannot be reset.");
        }
        if ($invoice && \App\Models\PaymentReceiptLine::query()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->exists()) {
            throw new RuntimeException("A payment received is entered against sub-bill {$invoice->invoice_no}. A Super Admin must delete that payment first.");
        }
        $files = array_filter([$sheet->certificate_path, $sheet->submitted_pdf_path]);
        $summary = [];

        DB::transaction(function () use ($sheet, $invoice, &$summary) {
            if ($invoice) {
                $tax = $invoice->taxInvoice;
                $invoice->forceFill(['tax_invoice_id' => null])->save();
                $invoice->lines()->delete();
                $invoice->delete();
                $summary[] = "sub-bill {$invoice->invoice_no} (₹{$invoice->rounded_total}, {$invoice->status}) deleted";
                if ($tax) {
                    $left = $tax->subBills()->get();
                    if ($left->isEmpty()) {
                        $tax->delete();
                        $summary[] = "tax invoice {$tax->number} deleted (no sub-bills left)";
                    } else {
                        $t = \App\Services\Billing\TaxInvoiceBuilder::totals($left, $tax->contract ?? $left->first()->contract);
                        $tax->update(['taxable' => $t->taxable, 'cgst' => $t->cgst, 'sgst' => $t->sgst, 'igst' => $t->igst,
                            'total' => $t->total, 'rounded_total' => $t->roundedTotal]);
                        $summary[] = "tax invoice {$tax->number} worked out again without it (now ₹{$t->roundedTotal})";
                    }
                }
            }
            $people = $sheet->entries()->count();
            \App\Models\AttendanceChange::query()->where('attendance_sheet_id', $sheet->id)->delete();
            $sheet->entries()->delete();
            $sheet->delete();
            $summary[] = "attendance of {$people} ".\Illuminate\Support\Str::plural('employee', $people).' deleted';
        });
        foreach ($files as $path) {
            Storage::disk('local')->delete($path);   // signed scan / PDF as submitted by the PI
        }

        return ucfirst(implode('; ', $summary)).'.';
    }

    private function assertEditable(AttendanceSheet $sheet): void
    {
        if ($sheet->isLocked()) {
            throw new RuntimeException('This month is locked. Unlock it to make changes.');
        }
    }

    /** Days before joining or after exit are not paid. */
    private function blankOutsideEmployment(array $codes, CarbonImmutable $month, Employee $e): array
    {
        foreach ($codes as $d => $code) {
            $date = $month->setDay($d);
            if (($e->joining_date && $date->lt($e->joining_date)) || ($e->exit_date && $date->gt($e->exit_date))) {
                $codes[$d] = 'A';
            }
        }

        return $codes;
    }
}
