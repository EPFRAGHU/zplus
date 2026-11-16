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
        $totals = $contract->isDaily() ? $calc->daily($lines) : $calc->monthly($lines);

        return DB::transaction(function () use ($sheet, $project, $contract, $totals, $user, $daysInMonth) {
            // SW/001/26-27(HTA): the work order's serial for the month, shared with its other sub-bills and its tax invoice.
            $number = app(BillNumbers::class)->subBillNumber($contract, $project, $sheet->month);
            for ($n = 2; Invoice::query()->where('agency_id', $contract->agency_id)->where('invoice_no', $number)->exists(); $n++) {
                $number = app(BillNumbers::class)->subBillNumber($contract, $project, $sheet->month).'-'.$n;   // two projects with the same code
            }

            $invoice = Invoice::query()->create([
                'agency_id' => $contract->agency_id,
                'invoice_no' => $number,
                'gem_contract_id' => $contract->id,
                'project_id' => $project->id,
                'attendance_sheet_id' => $sheet->id,
                'month' => $sheet->month->toDateString(),
                'invoice_date' => now()->toDateString(),
                'billing_type' => $contract->billing_type,
                'service_charge_percent' => $contract->service_charge_percent,
                'service_charge_includes_gst' => (bool) ($contract->service_charge_includes_gst ?? true),
                'gst_percent' => $contract->gst_percent,
                'wages' => $totals->wages, 'epf' => $totals->epf, 'esic' => $totals->esic,
                'subtotal' => $totals->subtotal, 'service_charge' => $totals->serviceCharge,
                'taxable' => $totals->taxable, 'cgst' => $totals->cgst, 'sgst' => $totals->sgst, 'igst' => $totals->igst,
                'total' => $totals->total, 'rounded_total' => $totals->roundedTotal,
                'status' => 'draft', 'created_by' => $user->id,
            ]);

            foreach ($totals->lines as $line) {
                $entry = $line->ref;
                $invoice->lines()->create([
                    'employee_id' => $entry->employee_id,
                    'employee_name' => $entry->employee->name,
                    'designation' => $entry->employee->post?->designation ?? '',
                    'rate' => $line->rate,
                    'rate_type' => $line->rateType,
                    'days_in_month' => $line->rateType === 'monthly' ? $daysInMonth : null,
                    'paid_days' => $line->paidDays,
                    'amount' => $line->amount,
                    'epf' => $line->epfAmount,
                    'esic' => $line->esicAmount,
                ]);
            }

            return $invoice;
        });
    }
}
