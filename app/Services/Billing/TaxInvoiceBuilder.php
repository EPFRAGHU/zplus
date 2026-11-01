<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Combines a client's sub-bills for one month into a single tax invoice.
 * Each sub-bill becomes one line; the totals are the sums of the sub-bills' own figures,
 * so the tax invoice always agrees with the sub-bills it covers.
 */
final class TaxInvoiceBuilder
{
    /** Sub-bills of this client and month that are not yet in a tax invoice. */
    public function pending(Client $client, CarbonImmutable $month): Collection
    {
        return Invoice::query()->with('project')
            ->whereHas('contract', fn ($q) => $q->where('client_id', $client->id))
            ->where('month', $month->startOfMonth()->toDateString())
            ->whereNull('tax_invoice_id')
            ->orderBy('invoice_no')->get();
    }

    public function suggestNumber(Client $client, CarbonImmutable $month): string
    {
        $last = (int) DocumentSequence::query()->where('key', $this->sequenceKey($client, $month))->value('last_number');

        return sprintf('%s/TI/%02d/%d', $client->short_code, $last + 1, $month->year);
    }

    /** @param int[] $subBillIds */
    public function create(Client $client, CarbonImmutable $month, array $subBillIds, User $user, ?string $number = null, ?string $date = null): TaxInvoice
    {
        $month = $month->startOfMonth();
        $subBills = Invoice::query()->with('contract')->whereKey($subBillIds)->get();

        if ($subBills->isEmpty()) {
            throw new RuntimeException('Choose at least one sub-bill for the tax invoice.');
        }
        foreach ($subBills as $b) {
            if ((int) $b->contract->client_id !== (int) $client->id) {
                throw new RuntimeException("Sub-bill {$b->invoice_no} is for a different client.");
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

        return DB::transaction(function () use ($client, $month, $subBills, $user, $number, $date, $sum, $total) {
            if (blank($number)) {
                $seq = DocumentSequence::next($this->sequenceKey($client, $month));
                $number = sprintf('%s/TI/%02d/%d', $client->short_code, $seq, $month->year);
            } elseif (TaxInvoice::query()->where('agency_id', $client->agency_id)->where('number', $number)->exists()) {
                throw new RuntimeException("Tax invoice number {$number} is already used.");
            }

            $tax = TaxInvoice::query()->create([
                'agency_id' => $client->agency_id, 'client_id' => $client->id, 'number' => $number,
                'month' => $month->toDateString(), 'invoice_date' => $date ?: now()->toDateString(),
                'place_of_supply' => $client->state_code === '21' ? 'Odisha' : 'State code '.$client->state_code,
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

    private function sequenceKey(Client $client, CarbonImmutable $month): string
    {
        return "tax-invoice:{$client->agency_id}:{$client->short_code}:{$month->year}";
    }
}
