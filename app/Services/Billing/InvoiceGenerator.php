<?php

namespace App\Services\Billing;

use App\Models\AttendanceSheet;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InvoiceGenerator
{
    public function generate(AttendanceSheet $sheet, User $user): Invoice
    {
        if (! $sheet->isLocked()) {
            throw new RuntimeException('Lock the attendance for this month before making the bill.');
        }
        if ($sheet->invoice()->exists()) {
            throw new RuntimeException('A bill already exists for this attendance sheet.');
        }
        [$project, $contract, $totals] = $this->compute($sheet);

        return DB::transaction(function () use ($sheet, $project, $contract, $totals, $user) {
            $invoice = Invoice::query()->create($this->figures($sheet, $contract, $totals) + [
                'agency_id' => $contract->agency_id,
                'invoice_no' => $this->newNumber($sheet, $project, $contract),
                'gem_contract_id' => $contract->id,
                'project_id' => $project->id,
                'attendance_sheet_id' => $sheet->id,
                'month' => $sheet->month->toDateString(),
                'invoice_date' => now()->toDateString(),
                'status' => 'draft', 'created_by' => $user->id,
            ]);
            $this->writeLines($invoice, $sheet, $totals);

            return $invoice;
        });
    }

    /**
     * Works a bill out again with today's rules (service charge incl. GST, exact figures, SW/001/26-27(CODE) numbers),
     * keeping its status, date and attendance. Not for paid bills, nor bills in a paid tax invoice; a tax invoice holding
     * it is worked out again too. (Owner, 16 Nov 2026: submitted bills too – the corrected PDF is then sent again.)
     * Returns [old total, new total].
     *
     * @return array{0: string, 1: string}
     */
    public function recalculate(Invoice $invoice, User $user): array
    {
        if ($invoice->status === 'paid') {
            throw new RuntimeException("Bill {$invoice->invoice_no} is already paid, so it cannot be changed.");
        }
        $tax = $invoice->taxInvoice;
        if ($tax && $tax->status === 'paid') {
            throw new RuntimeException("Bill {$invoice->invoice_no} is in tax invoice {$tax->number}, which is already paid.");
        }
        $sheet = $invoice->sheet;
        if (! $sheet || ! $sheet->isLocked()) {
            throw new RuntimeException("The attendance of bill {$invoice->invoice_no} is not locked.");
        }
        [$project, $contract, $totals] = $this->compute($sheet);
        $old = (string) $invoice->rounded_total;

        DB::transaction(function () use ($invoice, $sheet, $project, $contract, $totals, $tax) {
            $number = self::isNewStyle($invoice->invoice_no, $contract) ? $invoice->invoice_no : $this->newNumber($sheet, $project, $contract, $invoice->id);
            $invoice->update($this->figures($sheet, $contract, $totals) + ['invoice_no' => $number]);
            $invoice->lines()->delete();
            $this->writeLines($invoice, $sheet, $totals);

            if ($tax) {
                $tax->refresh();
                $t = TaxInvoiceBuilder::totals($tax->subBills()->get(), $tax->contract ?? $contract);
                $tax->update(['taxable' => $t->taxable, 'cgst' => $t->cgst, 'sgst' => $t->sgst, 'igst' => $t->igst,
                    'total' => $t->total, 'rounded_total' => $t->roundedTotal]);
            }
        });

        return [$old, (string) $invoice->fresh()->rounded_total];
    }

    /** Bills that would change if worked out again: made under the old rules and still open. */
    public static function outdated(): \Illuminate\Database\Eloquent\Builder
    {
        return Invoice::query()->whereIn('status', ['draft', 'approved', 'submitted'])
            ->where(fn ($q) => $q->where('service_charge_includes_gst', false)->orWhere('invoice_no', 'not like', '%/___/__-__(%'))
            ->where(fn ($q) => $q->whereNull('tax_invoice_id')->orWhereHas('taxInvoice', fn ($t) => $t->where('status', '!=', 'paid')));
    }

    private static function isNewStyle(string $number, $contract): bool
    {
        return (bool) preg_match('#^'.preg_quote($contract->agency->numberPrefix(), '#').'/\d{3}/\d{2}-\d{2}\(#', $number);
    }

    /** @return array{0: \App\Models\Project, 1: \App\Models\GemContract, 2: BillTotals} */
    private function compute(AttendanceSheet $sheet): array
    {
        $sheet->loadMissing('entries.employee.post', 'project.contract.client', 'project.contract.agency');
        $project = $sheet->project;
        $contract = $project->contract;
        $daysInMonth = $sheet->month->daysInMonth;

        $lines = [];
        foreach ($sheet->entries as $entry) {
            $e = $entry->employee;
            $paid = number_format((float) $entry->paid_days, 1, '.', '');
            $lines[] = $contract->isDaily()
                ? BillLine::daily($e->name, $e->rate(), $paid, $entry, (bool) $e->pf_applicable, (bool) $e->esic_applicable)
                : BillLine::monthly($e->name, $e->rate(), $daysInMonth, $paid, $entry, (bool) $e->pf_applicable, (bool) $e->esic_applicable);
        }
        $calc = $contract->calculator();

        return [$project, $contract, $contract->isDaily() ? $calc->daily($lines) : $calc->monthly($lines)];
    }

    private function figures(AttendanceSheet $sheet, $contract, BillTotals $totals): array
    {
        return [
            'billing_type' => $contract->billing_type,
            'service_charge_percent' => $contract->service_charge_percent,
            'service_charge_includes_gst' => (bool) ($contract->service_charge_includes_gst ?? true),
            'gst_percent' => $contract->gst_percent,
            'wages' => $totals->wages, 'epf' => $totals->epf, 'esic' => $totals->esic,
            'subtotal' => $totals->subtotal, 'service_charge' => $totals->serviceCharge,
            'taxable' => $totals->taxable, 'cgst' => $totals->cgst, 'sgst' => $totals->sgst, 'igst' => $totals->igst,
            'total' => $totals->total, 'rounded_total' => $totals->roundedTotal,
        ];
    }

    /** SW/001/26-27(HTA): the work order's serial for the month, shared with its other sub-bills and its tax invoice. */
    private function newNumber(AttendanceSheet $sheet, $project, $contract, ?int $exceptId = null): string
    {
        $base = app(BillNumbers::class)->subBillNumber($contract, $project, $sheet->month);
        $number = $base;
        for ($n = 2; Invoice::query()->where('agency_id', $contract->agency_id)->where('invoice_no', $number)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists(); $n++) {
            $number = $base.'-'.$n;   // two projects with the same code
        }

        return $number;
    }

    private function writeLines(Invoice $invoice, AttendanceSheet $sheet, BillTotals $totals): void
    {
        foreach ($totals->lines as $line) {
            $entry = $line->ref;
            $invoice->lines()->create([
                'employee_id' => $entry->employee_id,
                'employee_name' => $entry->employee->name,
                'designation' => $entry->employee->post?->designation ?? '',
                'rate' => $line->rate,
                'rate_type' => $line->rateType,
                'days_in_month' => $line->rateType === 'monthly' ? $sheet->month->daysInMonth : null,
                'paid_days' => $line->paidDays,
                'amount' => $line->amount,
                'epf' => $line->epfAmount,
                'esic' => $line->esicAmount,
            ]);
        }
    }
}
