<?php

namespace App\Services\Billing;

use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Combines the sub-bills of one work order (GeM contract) for one month into a single tax invoice, numbered
 * SW/001/26-27: the serial its sub-bills SW/001/26-27(HTA), (CCRH), (MPH) already carry.
 * Each sub-bill becomes one line; the totals are the sums of the sub-bills' own figures,
 * so the tax invoice always agrees with the sub-bills it covers.
 */
final class TaxInvoiceBuilder
{
    public function __construct(private readonly BillNumbers $numbers) {}

    /** Sub-bills of this work order and month that are not yet in a tax invoice. */
    public function pending(GemContract $contract, CarbonImmutable $month): Collection
    {
        return Invoice::query()->with('project')
            ->where('gem_contract_id', $contract->id)
            ->where('month', $month->startOfMonth()->toDateString())
            ->whereNull('tax_invoice_id')
            ->orderBy('invoice_no')->get();
    }

    public function suggestNumber(GemContract $contract, CarbonImmutable $month): string
    {
        return $this->numbers->preview($contract->loadMissing('agency'), $month->startOfMonth());
    }

    /** @param int[] $subBillIds */
    public function create(GemContract $contract, CarbonImmutable $month, array $subBillIds, User $user, ?string $number = null, ?string $date = null): TaxInvoice
    {
        $month = $month->startOfMonth();
        $contract->loadMissing('agency', 'client');
        $client = $contract->client;
        $subBills = Invoice::query()->whereKey($subBillIds)->get();

        if ($subBills->isEmpty()) {
            throw new RuntimeException('Choose at least one sub-bill for the tax invoice.');
        }
        foreach ($subBills as $b) {
            if ((int) $b->gem_contract_id !== (int) $contract->id) {
                throw new RuntimeException("Sub-bill {$b->invoice_no} is under a different work order.");
            }
            if ($b->month->toDateString() !== $month->toDateString()) {
                throw new RuntimeException("Sub-bill {$b->invoice_no} is for a different month.");
            }
            if ($b->tax_invoice_id) {
                throw new RuntimeException("Sub-bill {$b->invoice_no} is already in a tax invoice.");
            }
        }

        $sum = fn (string $field) => Money::format($subBills->sum(fn ($b) => Money::toPaise((string) $b->{$field})));
        $total = $subBills->sum(fn ($b) => Money::toPaise((string) $b->total));

        return DB::transaction(function () use ($contract, $client, $month, $subBills, $user, $number, $date, $sum, $total) {
            $number = blank($number) ? $this->numbers->taxInvoiceNumber($contract, $month) : trim($number);
            if (TaxInvoice::query()->where('agency_id', $contract->agency_id)->where('number', $number)->exists()) {
                throw new RuntimeException("Tax invoice number {$number} is already used.");
            }

            $tax = TaxInvoice::query()->create([
                'agency_id' => $contract->agency_id, 'client_id' => $client->id, 'gem_contract_id' => $contract->id, 'number' => $number,
                'month' => $month->toDateString(), 'invoice_date' => $date ?: now()->toDateString(),
                'place_of_supply' => \App\Support\IndianStates::name($client->state_code) ?? 'State code '.$client->state_code,
                'taxable' => $sum('taxable'), 'cgst' => $sum('cgst'), 'sgst' => $sum('sgst'), 'igst' => $sum('igst'),
                'total' => Money::format($total), 'rounded_total' => Money::format(Money::roundToRupee($total)),
                'status' => 'draft', 'created_by' => $user->id,
            ]);
            Invoice::query()->whereKey($subBills->pluck('id'))->update(['tax_invoice_id' => $tax->id]);

            return $tax;
        });
    }

    /** Releases the sub-bills of a draft tax invoice and deletes it. */
    public function delete(TaxInvoice $tax): void
    {
        if ($tax->status !== 'draft') {
            throw new RuntimeException('Only draft tax invoices can be deleted.');
        }
        DB::transaction(function () use ($tax) {
            $tax->subBills()->update(['tax_invoice_id' => null]);
            $tax->delete();
        });
    }
}
