<?php

namespace App\Services\Billing;

use App\Models\Agency;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Month bill (owner, 22 Nov 2026): an internal summary of one agency's month, one row per work order with its tax
 * invoice(s), sub-bills, staff and the bill components, and the month total. Not a GST invoice and not numbered;
 * every figure is read from the sub-bills and tax invoices already made, nothing is worked out again.
 */
final class MonthBill
{
    /** Stored bill figures have 2 decimals: added exactly, as text. */
    private static function add(string $a, string $b): string
    {
        return (string) BigDecimal::of($a)->plus(BigDecimal::of($b === '' ? '0' : $b))->toScale(2);
    }

    /** @return array{rows: Collection, total: array<string, mixed>} */
    public function build(Agency $agency, CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $bills = Invoice::query()->with('taxInvoice')->withCount('lines')
            ->where('agency_id', $agency->id)->where('month', $month->toDateString())->orderBy('invoice_no')->get();
        $contracts = GemContract::query()->with('client')->whereKey($bills->pluck('gem_contract_id')->unique())->get()->keyBy('id');
        $money = ['wages', 'epf', 'esic', 'service_charge', 'taxable', 'cgst', 'sgst', 'igst', 'total', 'rounded_total'];

        $rows = $bills->groupBy('gem_contract_id')->map(function (Collection $group, $contractId) use ($contracts, $money) {
            $taxes = $group->pluck('taxInvoice')->filter()->unique('id')->sortBy(fn (TaxInvoice $t) => $t->number, SORT_NATURAL)->values();
            $row = ['contract' => $contracts[$contractId], 'first_no' => $group->first()->invoice_no, 'tax_invoices' => $taxes,
                'sub_bills' => $group->count(), 'not_in_tax' => $group->whereNull('tax_invoice_id')->count(), 'staff' => (int) $group->sum('lines_count'),
                'tax_amount' => $taxes->reduce(fn ($c, $t) => self::add($c, (string) $t->rounded_total), '0')];
            foreach ($money as $field) {
                $row[$field] = $group->reduce(fn ($c, $b) => self::add($c, (string) $b->{$field}), '0');
            }

            return $row;
        })->sortBy('first_no', SORT_NATURAL)->values();   // the work order with SW/001 first, then SW/002 …

        $total = ['work_orders' => $rows->count(), 'sub_bills' => $rows->sum('sub_bills'), 'not_in_tax' => $rows->sum('not_in_tax'),
            'staff' => $rows->sum('staff'), 'tax_invoices' => $rows->sum(fn ($r) => $r['tax_invoices']->count())];
        foreach ([...$money, 'tax_amount'] as $field) {
            $total[$field] = $rows->reduce(fn ($c, $r) => self::add($c, $r[$field]), '0');
        }

        return ['rows' => $rows, 'total' => $total];
    }
}
