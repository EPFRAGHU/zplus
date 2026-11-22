<?php

namespace App\Services\Payments;

use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptLine;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Approvals\Approvals;
use App\Support\Activity\Activity;
use App\Support\Exact;
use Brick\Math\BigRational;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Payments received from the client (owner, 22 Nov 2026). A receipt is one bank credit or cheque split over the sub-bills
 * it pays. Per sub-bill the client deducts income-tax TDS (194C, 2% for a firm/company) and GST TDS (section 51: 1% CGST +
 * 1% SGST, or 2% IGST), both on the value before GST, and sometimes something else; bill total = received + deductions.
 * The expected figures are only suggestions: what the client actually deducted is entered. Only approved receipts count.
 */
class Receipts
{
    public const TDS_IT_PERCENT = '2';

    public const GST_TDS_PERCENT = '2';   // 1% CGST + 1% SGST, or 2% IGST

    /** Paisa / rupee rounding the client may apply: within ₹1 a sub-bill counts as fully paid. */
    public const TOLERANCE = '1';

    private const FIELDS = ['tds_it', 'tds_cgst', 'tds_sgst', 'tds_igst', 'other_deduction', 'received'];

    /** Received + deductions on a sub-bill by receipts that count (approved; with $pending also those waiting), optionally leaving one receipt out. */
    public function settled(Invoice $bill, bool $pending = false, ?int $except = null): BigRational
    {
        $lines = PaymentReceiptLine::query()->where('invoice_id', $bill->id)
            ->whereHas('receipt', fn ($q) => $q->whereIn('status', $pending ? ['approved', 'pending'] : ['approved'])->when($except, fn ($r) => $r->whereKeyNot($except)))
            ->get();

        return $lines->reduce(fn ($c, $l) => $c->plus(Exact::of($l->settled())), Exact::zero());
    }

    /** What is still to come on a sub-bill. */
    public function outstanding(Invoice $bill, bool $pending = false, ?int $except = null): BigRational
    {
        return Exact::of((string) $bill->rounded_total)->minus($this->settled($bill, $pending, $except));
    }

    /**
     * Suggested deductions and net for the rest of a sub-bill: TDS 2% and GST TDS 2% of the value before GST, less what
     * earlier receipts already deducted, each rounded to the rupee as departments do.
     *
     * @return array<string, string>
     */
    public function expected(Invoice $bill, ?int $except = null): array
    {
        $taxable = Exact::of((string) $bill->taxable);
        $done = PaymentReceiptLine::query()->where('invoice_id', $bill->id)
            ->whereHas('receipt', fn ($q) => $q->whereIn('status', ['approved', 'pending'])->when($except, fn ($r) => $r->whereKeyNot($except)))->get();
        $sum = fn (string $f) => $done->reduce(fn ($c, $l) => $c->plus(Exact::of((string) $l->{$f})), Exact::zero());
        $left = fn (BigRational $want, string $f) => ($r = $want->minus($sum($f)))->isNegative() ? Exact::zero() : $r;

        $it = $left($taxable->multipliedBy(self::TDS_IT_PERCENT)->dividedBy(100), 'tds_it');
        $gst = $taxable->multipliedBy(self::GST_TDS_PERCENT)->dividedBy(100);
        $inter = (float) $bill->igst > 0;
        $out = ['tds_it' => Exact::rupees($it), 'tds_cgst' => '0.00', 'tds_sgst' => '0.00', 'tds_igst' => '0.00', 'other_deduction' => '0.00'];
        if ($inter) {
            $out['tds_igst'] = Exact::rupees($left($gst, 'tds_igst'));
        } else {
            $out['tds_cgst'] = Exact::rupees($left($gst->dividedBy(2), 'tds_cgst'));
            $out['tds_sgst'] = Exact::rupees($left($gst->dividedBy(2), 'tds_sgst'));
        }
        $deducted = array_reduce($out, fn ($c, $v) => $c->plus(Exact::of($v)), Exact::zero());
        $net = $this->outstanding($bill, true, $except)->minus($deducted);
        $out['received'] = Exact::money($net->isNegative() ? Exact::zero() : $net);

        return $out;
    }

    /** Sub-bills of a work order that can still be paid (submitted, not settled), plus those already on $receipt. */
    public function openBills(GemContract $contract, ?PaymentReceipt $receipt = null): Collection
    {
        $on = $receipt ? $receipt->lines()->pluck('invoice_id')->all() : [];

        return Invoice::query()->where('gem_contract_id', $contract->id)->with('project:id,code,name')
            ->where(fn ($q) => $q->whereIn('status', ['approved', 'submitted'])->orWhereIn('id', $on))
            ->orderBy('month')->orderBy('invoice_no')->get()
            ->filter(fn (Invoice $b) => in_array($b->id, $on, true) || $this->outstanding($b, true)->isGreaterThan(Exact::of(self::TOLERANCE)))
            ->values();
    }

    /**
     * Saves a new receipt or changes one that is waiting / returned; it then waits for a Super Admin.
     *
     * @param  array<string, mixed>  $data  receipt fields
     * @param  array<int, array<string, mixed>>  $lines  invoice_id => deductions and net
     */
    public function save(?PaymentReceipt $receipt, GemContract $contract, array $data, array $lines, User $user, ?UploadedFile $advice = null): PaymentReceipt
    {
        if ($receipt && $receipt->status === 'approved') {
            throw new RuntimeException('This receipt is approved, so it cannot be changed. A Super Admin can delete it, then enter it again.');
        }
        $open = $this->openBills($contract, $receipt)->keyBy('id');
        $rows = [];
        $total = Exact::zero();
        foreach ($lines as $invoiceId => $l) {
            $bill = $open->get((int) $invoiceId);
            if (! $bill) {
                throw new RuntimeException('A chosen sub-bill is not an open sub-bill of this work order.');
            }
            $row = ['invoice_id' => $bill->id, 'other_reason' => trim((string) ($l['other_reason'] ?? '')) ?: null];
            $settles = Exact::zero();
            foreach (self::FIELDS as $f) {
                $v = Exact::of(number_format((float) ($l[$f] ?? 0), 2, '.', ''));
                if ($v->isNegative()) {
                    throw new RuntimeException("Amounts cannot be negative ({$bill->invoice_no}).");
                }
                $row[$f] = Exact::money($v);
                $settles = $settles->plus($v);
            }
            if ((float) $row['other_deduction'] > 0 && ! $row['other_reason']) {
                throw new RuntimeException("Write the reason for the other deduction on {$bill->invoice_no}.");
            }
            $left = $this->outstanding($bill, true, $receipt?->id);
            if ($settles->isGreaterThan($left->plus(Exact::of(self::TOLERANCE)))) {
                throw new RuntimeException("{$bill->invoice_no}: received + deductions (₹".Exact::money($settles).') is more than what is still due (₹'.Exact::money($left).').');
            }
            if ($settles->isZero()) {
                continue;
            }
            $row += ['bill_amount' => (string) $bill->rounded_total, 'taxable' => (string) $bill->taxable];
            $total = $total->plus(Exact::of($row['received']));
            $rows[] = $row;
        }
        if ($rows === []) {
            throw new RuntimeException('Tick at least one sub-bill and enter what was received for it.');
        }
        if (! $total->isEqualTo(Exact::of(number_format((float) $data['amount'], 2, '.', '')))) {
            throw new RuntimeException('The amount credited (₹'.number_format((float) $data['amount'], 2, '.', '').') must equal the total received on the sub-bills (₹'.Exact::money($total).').');
        }

        return DB::transaction(function () use ($receipt, $contract, $data, $rows, $user, $advice) {
            $fields = $data + ['agency_id' => $contract->agency_id, 'client_id' => $contract->client_id, 'gem_contract_id' => $contract->id,
                'status' => 'pending', 'return_note' => null, 'approved_by' => null, 'approved_at' => null];
            if ($advice) {
                $old = $receipt?->advice_path;
                $fields['advice_path'] = $advice->store('payment-advices/'.now()->format('Y-m'), 'local');
                $fields['advice_name'] = (\App\Support\Codes::fileSafe(pathinfo($advice->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'payment-advice').'.'.strtolower($advice->getClientOriginalExtension() ?: 'pdf');
            }
            if ($receipt) {
                $receipt->update($fields);
                $receipt->lines()->delete();
                if (isset($old) && $old) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($old);
                }
            } else {
                $receipt = PaymentReceipt::query()->create($fields + ['entered_by' => $user->id]);
            }
            foreach ($rows as $row) {
                $receipt->lines()->create($row + ['agency_id' => $contract->agency_id]);
            }

            return $receipt->fresh('lines');
        });
    }

    /** Super Admin, not the one who entered it: the receipt counts, and sub-bills it settles in full turn Paid (their tax invoice too when all are). */
    public function approve(PaymentReceipt $receipt, User $user): array
    {
        if (! Approvals::canApprove($user)) {
            throw new RuntimeException('Only a Super Admin can approve payments.');
        }
        if ($receipt->entered_by === $user->id) {
            throw new RuntimeException('You entered this receipt, so another Super Admin must approve it.');
        }
        if ($receipt->status !== 'pending') {
            throw new RuntimeException('Only a receipt waiting for approval can be approved.');
        }

        return DB::transaction(function () use ($receipt, $user) {
            $receipt->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'return_note' => null]);

            return $this->resettle($receipt->lines()->with('invoice.taxInvoice')->get()->pluck('invoice'), $receipt->credit_date->toDateString());
        });
    }

    public function sendBack(PaymentReceipt $receipt, User $user, string $note): void
    {
        if (! Approvals::canApprove($user) || $receipt->status !== 'pending') {
            throw new RuntimeException('Only a Super Admin can return a receipt that is waiting for approval.');
        }
        $receipt->update(['status' => 'returned', 'return_note' => mb_substr($note, 0, 500)]);
    }

    /** Super Admin: removes a receipt; sub-bills it had settled go back to Submitted (and their tax invoice). */
    public function delete(PaymentReceipt $receipt): array
    {
        return DB::transaction(function () use ($receipt) {
            $bills = $receipt->lines()->with('invoice.taxInvoice')->get()->pluck('invoice');
            $receipt->delete();

            return $this->resettle($bills, null);
        });
    }

    /**
     * Brings sub-bills in line with their approved receipts: fully settled → Paid (paid on the latest credit date);
     * a Paid sub-bill no longer settled → Submitted. Returns notes for the Activity log / message.
     *
     * @return list<string>
     */
    private function resettle(Collection $bills, ?string $creditDate): array
    {
        $notes = [];
        $taxes = collect();
        foreach ($bills->filter()->unique('id') as $bill) {
            $bill->refresh();
            $settled = $this->outstanding($bill)->isLessThanOrEqualTo(Exact::of(self::TOLERANCE));
            if ($settled && $bill->status !== 'paid') {
                $last = PaymentReceiptLine::query()->where('invoice_id', $bill->id)->whereHas('receipt', fn ($q) => $q->where('status', 'approved'))
                    ->with('receipt:id,credit_date')->get()->max(fn ($l) => $l->receipt->credit_date->toDateString());
                $bill->forceFill(['status' => 'paid', 'paid_on' => $last ?? $creditDate])->save();
                $notes[] = "{$bill->invoice_no} paid in full";
            } elseif (! $settled && $bill->status === 'paid') {
                $bill->forceFill(['status' => 'submitted', 'paid_on' => null])->save();
                $notes[] = "{$bill->invoice_no} back to Submitted";
            }
            if ($bill->tax_invoice_id) {
                $taxes->push($bill->tax_invoice_id);
            }
        }
        foreach (TaxInvoice::query()->whereKey($taxes->unique()->all())->with('subBills')->get() as $tax) {
            $allPaid = $tax->subBills->isNotEmpty() && $tax->subBills->every(fn ($b) => $b->status === 'paid');
            if ($allPaid && $tax->status !== 'paid') {
                $tax->forceFill(['status' => 'paid', 'paid_on' => $tax->subBills->max(fn ($b) => $b->paid_on?->toDateString())])->save();
                $notes[] = "tax invoice {$tax->number} paid";
            } elseif (! $allPaid && $tax->status === 'paid') {
                $tax->forceFill(['status' => 'submitted', 'paid_on' => null])->save();
                $notes[] = "tax invoice {$tax->number} back to Submitted";
            }
        }
        if ($notes) {
            Activity::log('status', null, [], 'Payments: '.implode('; ', $notes), section: 'Payments received');
        }

        return $notes;
    }
}
