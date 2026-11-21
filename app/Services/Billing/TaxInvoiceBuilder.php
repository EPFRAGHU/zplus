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
            // The same post at the same rate is one row across projects, manpower added up (owner, 21 Nov 2026).
            $key = mb_strtoupper(preg_replace('/\s+/', ' ', trim($line->label))).'|'.$line->rate.'|'.$line->rateType.'|'.$line->daysInMonth;
            $groups[$key] ??= ['designation' => $line->label, 'projects' => [], 'manpower' => 0,
                'days' => \App\Support\Exact::zero(), 'amount' => \App\Support\Exact::zero(),
                'perDay' => $line->rateType === 'monthly' ? \App\Support\Exact::mulDiv(\App\Support\Exact::of($line->rate), '1', $line->daysInMonth) : \App\Support\Exact::of($line->rate)];
            $groups[$key]['manpower']++;
            $groups[$key]['projects'][$bill->project->code] = true;
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
        $rows = array_values(array_map(fn ($g) => ['designation' => $g['designation'], 'project' => implode(', ', array_keys($g['projects'])), 'manpower' => $g['manpower'],
            'days' => $days($g['days']), 'perDay' => \App\Support\Exact::money($g['perDay']), 'amount' => \App\Support\Exact::money($g['amount'])], $groups));

        return [
            'rows' => $rows,
            'manpower' => array_sum(array_column($rows, 'manpower')),
            'days' => $days(array_reduce($groups, fn ($c, $g) => $c->plus($g['days']), \App\Support\Exact::zero())),
            'epfSplit' => (float) $epfPercent === 13.0
                ? ['epf' => \App\Support\Exact::money($epf12), 'edli' => \App\Support\Exact::money($edli), 'admin' => \App\Support\Exact::money($admin)] : null,
        ];
    }

    /**
     * Brings an unpaid tax invoice made under the old rules up to date: links it to its work order, gives it the
     * work order's number for the month (SW/001/26-27, the serial its sub-bills carry) and works its totals out again.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} [old number, new number, old total, new total]
     */
    /**
     * Running balance of the work order's tender value (owner, 21 Nov 2026): value incl. GST, less every earlier tax
     * invoice of the contract (opening balance), less this one (closing balance, next month's opening).
     *
     * @return array{value: string, previous: string, opening: string, current: string, closing: string, count: int}|null
     */
    public static function contractBalance(TaxInvoice $tax): ?array
    {
        $contract = $tax->contract;
        if (! $contract || blank($contract->contract_value) || (float) $contract->contract_value <= 0) {
            return null;
        }
        $earlier = TaxInvoice::query()->where('gem_contract_id', $contract->id)->whereKeyNot($tax->id)
            ->where(fn ($q) => $q->where('month', '<', $tax->month->toDateString())
                ->orWhere(fn ($w) => $w->where('month', $tax->month->toDateString())->where('id', '<', $tax->id)))
            ->get(['rounded_total']);
        $sum = fn ($rows) => $rows->reduce(fn ($c, $r) => $c->plus(\App\Support\Exact::of((string) $r->rounded_total)), \App\Support\Exact::zero());
        $value = \App\Support\Exact::of((string) $contract->contract_value);
        $previous = $sum($earlier);
        $opening = $value->minus($previous);
        $current = \App\Support\Exact::of((string) $tax->rounded_total);

        return ['value' => \App\Support\Exact::money($value), 'previous' => \App\Support\Exact::money($previous), 'opening' => \App\Support\Exact::money($opening),
            'current' => \App\Support\Exact::money($current), 'closing' => \App\Support\Exact::money($opening->minus($current)), 'count' => $earlier->count()];
    }

    public function refresh(TaxInvoice $tax): array
    {
        if ($tax->status === 'paid') {
            throw new RuntimeException("Tax invoice {$tax->number} is already paid, so it cannot be changed.");
        }
        $subBills = $tax->subBills()->get();
        if ($subBills->isEmpty()) {
            throw new RuntimeException("Tax invoice {$tax->number} has no sub-bills.");
        }
        if ($subBills->pluck('gem_contract_id')->unique()->count() > 1) {
            throw new RuntimeException("Tax invoice {$tax->number} holds sub-bills of more than one work order. Delete it and make one tax invoice per work order.");
        }
        $contract = GemContract::query()->with('agency')->findOrFail($subBills->first()->gem_contract_id);
        [$oldNo, $oldTotal] = [$tax->number, (string) $tax->rounded_total];

        DB::transaction(function () use ($tax, $contract, $subBills) {
            $number = $tax->number;
            if (! self::isNewStyle($number, $contract)) {
                $base = $this->numbers->taxInvoiceNumber($contract, $tax->month);
                $number = $base;
                for ($n = 2; TaxInvoice::query()->where('agency_id', $contract->agency_id)->where('number', $number)->whereKeyNot($tax->id)->exists(); $n++) {
                    $number = $base.'-'.$n;
                }
            }
            $t = self::totals($subBills, $contract);
            $tax->update(['gem_contract_id' => $contract->id, 'number' => $number,
                'taxable' => $t->taxable, 'cgst' => $t->cgst, 'sgst' => $t->sgst, 'igst' => $t->igst,
                'total' => $t->total, 'rounded_total' => $t->roundedTotal]);
        });
        $tax->refresh();

        return [$oldNo, $tax->number, $oldTotal, (string) $tax->rounded_total];
    }

    /** Unpaid tax invoices still carrying an old-style number (e.g. ICMR/TI/01/2026) or no work order. */
    public static function outdated(): \Illuminate\Database\Eloquent\Builder
    {
        return TaxInvoice::query()->where('status', '!=', 'paid')
            ->where(fn ($q) => $q->whereNull('gem_contract_id')->orWhere('number', 'not like', '%/___/__-__'));
    }

    /** SW/001/26-27 (optionally -2) with the work order's agency prefix. */
    public static function isNewStyle(string $number, GemContract $contract): bool
    {
        return (bool) preg_match('#^'.preg_quote($contract->agency->numberPrefix(), '#').'/\d{3}/\d{2}-\d{2}(-\d+)?$#', $number);
    }

    /**
     * Takes a sub-bill out of its tax invoice: the tax invoice is deleted when it is left empty, otherwise its figures
     * are worked out again without the sub-bill. Not for paid tax invoices. Returns what happened, for the Activity log.
     */
    public function release(Invoice $bill): ?string
    {
        $tax = $bill->taxInvoice;
        if (! $tax) {
            return null;
        }
        if ($tax->status === 'paid') {
            throw new RuntimeException("Sub-bill {$bill->invoice_no} is in tax invoice {$tax->number}, which is paid.");
        }

        return DB::transaction(function () use ($bill, $tax) {
            $bill->forceFill(['tax_invoice_id' => null])->save();
            $left = $tax->subBills()->get();
            if ($left->isEmpty()) {
                $tax->delete();

                return "tax invoice {$tax->number} deleted (no sub-bills left)";
            }
            $t = self::totals($left, $tax->contract ?? $left->first()->contract);
            $tax->update(['taxable' => $t->taxable, 'cgst' => $t->cgst, 'sgst' => $t->sgst, 'igst' => $t->igst, 'total' => $t->total, 'rounded_total' => $t->roundedTotal]);

            return "tax invoice {$tax->number} worked out again without it (now ₹{$t->roundedTotal})";
        });
    }

    /** Releases the sub-bills of a tax invoice and deletes it: draft ones, or (Super Admin, $any) any not yet paid. */
    public function delete(TaxInvoice $tax, bool $any = false): void
    {
        if ($any ? $tax->status === 'paid' : $tax->status !== 'draft') {
            throw new RuntimeException($any ? 'Paid tax invoices cannot be deleted.' : 'Only draft tax invoices can be deleted.');
        }
        DB::transaction(function () use ($tax) {
            $tax->subBills()->update(['tax_invoice_id' => null]);
            $tax->delete();
        });
    }
}
