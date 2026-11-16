<?php

namespace App\Services\Billing;

use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Combines the sub-bills of one work order (GeM contract) for one month into a single tax invoice, numbered
 * SW/001/26-27: the serial its sub-bills SW/001/26-27(HTA), (CCRH), (MPH) already carry.
 * As on the agencies' own tax invoice: the sub-bills' wages, EPF and ESIC are added up into one sub-total, and the
 * service charge and GST are worked out once on that sub-total (not added up from the sub-bills), with no rounding
 * except the final "Rounding off". So the total can differ from the sum of the sub-bills' totals by a few paise.
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

        $t = self::totals($subBills, $contract);

        return DB::transaction(function () use ($contract, $client, $month, $subBills, $user, $number, $date, $t) {
            $number = blank($number) ? $this->numbers->taxInvoiceNumber($contract, $month) : trim($number);
            if (TaxInvoice::query()->where('agency_id', $contract->agency_id)->where('number', $number)->exists()) {
                throw new RuntimeException("Tax invoice number {$number} is already used.");
            }

            $tax = TaxInvoice::query()->create([
                'agency_id' => $contract->agency_id, 'client_id' => $client->id, 'gem_contract_id' => $contract->id, 'number' => $number,
                'month' => $month->toDateString(), 'invoice_date' => $date ?: now()->toDateString(),
                'place_of_supply' => \App\Support\IndianStates::name($client->state_code) ?? 'State code '.$client->state_code,
                'taxable' => $t->taxable, 'cgst' => $t->cgst, 'sgst' => $t->sgst, 'igst' => $t->igst,
                'total' => $t->total, 'rounded_total' => $t->roundedTotal,
                'status' => 'draft', 'created_by' => $user->id,
            ]);
            Invoice::query()->whereKey($subBills->pluck('id'))->update(['tax_invoice_id' => $tax->id]);

            return $tax;
        });
    }

    /**
     * Tax invoice figures, worked out as on the agencies' own tax invoice: every person of every sub-bill is added up
     * exactly (no rounding), then service charge and GST are worked out once on that sub-total. Uses the rates the
     * sub-bills were made with. Returns the calculator's totals (strings, 2 decimals) with its lines (each with ->exact).
     */
    public static function totals(Collection $subBills, GemContract $contract): BillTotals
    {
        $subBills->loadMissing('lines', 'project');
        $first = $subBills->first();
        $lines = [];
        foreach ($subBills as $b) {
            foreach ($b->lines as $l) {
                $paid = number_format((float) $l->paid_days, 1, '.', '');
                $lines[] = $l->rate_type === 'daily'
                    ? BillLine::daily($l->designation, (string) $l->rate, $paid, ['bill' => $b, 'line' => $l], (float) $l->epf > 0, (float) $l->esic > 0)
                    : BillLine::monthly($l->designation, (string) $l->rate, (int) $l->days_in_month, $paid, ['bill' => $b, 'line' => $l], (float) $l->epf > 0, (float) $l->esic > 0);
            }
        }
        $dec = fn ($v) => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v;
        $calc = new GemBillCalculator($dec($first->service_charge_percent), $dec($first->gst_percent), (bool) $contract->inter_state,
            $dec($contract->epf_percent), $dec($contract->esic_percent), (bool) $first->service_charge_includes_gst);

        return $calc->monthly($lines);
    }

    /**
     * Rows of the tax invoice's "Particulars" table: one per post of each project (in the order of the sub-bills),
     * with the number of persons, their total working days, the per-day wage (monthly rate / days in month) and the
     * exact amount. Also splits employer EPF into EPF 12%, EDLI 0.5% and PF admin charges 0.5% when the rate is 13%.
     *
     * @return array{rows: array<int, array{designation: string, project: string, manpower: int, days: string, perDay: string, amount: string}>,
     *               manpower: int, days: string, epfSplit: ?array{epf: string, edli: string, admin: string}}
     */
    public static function particulars(BillTotals $t, string $epfPercent): array
    {
        $groups = [];
        $ceiling = \App\Support\Exact::of((string) \App\Support\Statutory::EPF_WAGE_CEILING);
        $epf12 = $edli = $admin = \App\Support\Exact::zero();
        foreach ($t->lines as $line) {
            $bill = $line->ref['bill'];
            $key = $bill->id.'|'.$line->label.'|'.$line->rate.'|'.$line->rateType.'|'.$line->daysInMonth;
            $groups[$key] ??= ['designation' => $line->label, 'project' => $bill->project->code, 'manpower' => 0,
                'days' => \App\Support\Exact::zero(), 'amount' => \App\Support\Exact::zero(),
                'perDay' => $line->rateType === 'monthly' ? \App\Support\Exact::mulDiv(\App\Support\Exact::of($line->rate), '1', $line->daysInMonth) : \App\Support\Exact::of($line->rate)];
            $groups[$key]['manpower']++;
            $groups[$key]['days'] = $groups[$key]['days']->plus($line->paidDays);
            $groups[$key]['amount'] = $groups[$key]['amount']->plus($line->exact);
            if ($line->epf) {
                $base = $line->exact->compareTo($ceiling) > 0 ? $ceiling : $line->exact;
                $epf12 = $epf12->plus(\App\Support\Exact::mulDiv($base, '12', 100));
                $edli = $edli->plus(\App\Support\Exact::mulDiv($base, '0.5', 100));
                $admin = $admin->plus(\App\Support\Exact::mulDiv($base, '0.5', 100));
            }
        }
        $days = fn ($d) => rtrim(rtrim((string) $d->toScale(1, \Brick\Math\RoundingMode::HalfUp), '0'), '.');
        $rows = array_values(array_map(fn ($g) => ['designation' => $g['designation'], 'project' => $g['project'], 'manpower' => $g['manpower'],
            'days' => $days($g['days']), 'perDay' => \App\Support\Exact::money($g['perDay']), 'amount' => \App\Support\Exact::money($g['amount'])], $groups));

        return [
            'rows' => $rows,
            'manpower' => array_sum(array_column($rows, 'manpower')),
            'days' => $days(array_reduce($groups, fn ($c, $g) => $c->plus($g['days']), \App\Support\Exact::zero())),
            'epfSplit' => (float) $epfPercent === 13.0
                ? ['epf' => \App\Support\Exact::money($epf12), 'edli' => \App\Support\Exact::money($edli), 'admin' => \App\Support\Exact::money($admin)] : null,
        ];
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
