<?php

namespace App\Services\Billing;

use App\Models\AttendanceSheet;
use App\Models\DocumentSequence;
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

        $sheet->loadMissing('entries.employee.post', 'project.contract.client');
        $project = $sheet->project;
        $contract = $project->contract;
        $daysInMonth = $sheet->month->daysInMonth;

        $lines = [];
        foreach ($sheet->entries as $entry) {
            $e = $entry->employee;
            $paid = number_format((float) $entry->paid_days, 1, '.', '');
            $lines[] = $contract->isDaily()
                ? BillLine::daily($e->name, $e->rate(), $paid, $entry)
                : BillLine::monthly($e->name, $e->rate(), $daysInMonth, $paid, $entry);
        }
        $calc = $contract->calculator();
        $totals = $contract->isDaily() ? $calc->daily($lines) : $calc->monthly($lines);

        return DB::transaction(function () use ($sheet, $project, $contract, $totals, $user, $daysInMonth) {
            $year = $sheet->month->year;
            $prefix = $contract->client->short_code.'/'.$project->code;
            $seq = DocumentSequence::next("invoice:{$contract->agency_id}:{$prefix}:{$year}");   // separate series per agency

            $invoice = Invoice::query()->create([
                'agency_id' => $contract->agency_id,
                'invoice_no' => sprintf('%s/%02d/%d', $prefix, $seq, $year),
                'gem_contract_id' => $contract->id,
                'project_id' => $project->id,
                'attendance_sheet_id' => $sheet->id,
                'month' => $sheet->month->toDateString(),
                'invoice_date' => now()->toDateString(),
                'billing_type' => $contract->billing_type,
                'service_charge_percent' => $contract->service_charge_percent,
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
                ]);
            }

            return $invoice;
        });
    }
}
