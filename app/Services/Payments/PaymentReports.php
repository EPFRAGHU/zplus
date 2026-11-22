<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptLine;
use App\Support\Exact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Registers of payments received (owner, 22 Nov 2026): receipts, outstanding sub-bills with ageing, and TDS / GST TDS. */
class PaymentReports
{
    public const AGES = ['0-30' => '0–30 days', '31-60' => '31–60 days', '61-90' => '61–90 days', '90+' => 'Over 90 days'];

    public function __construct(private Receipts $receipts) {}

    /** Receipts credited in the period (all statuses), newest first. */
    public function receipts(CarbonImmutable $from, CarbonImmutable $to, ?int $contractId): Collection
    {
        return PaymentReceipt::query()->with(['lines.invoice:id,invoice_no', 'contract:id,contract_no', 'agency:id,code,name', 'client:id,name,short_code', 'enteredBy:id,name'])
            ->whereBetween('credit_date', [$from->toDateString(), $to->toDateString()])
            ->when($contractId, fn ($q, $id) => $q->where('gem_contract_id', $id))
            ->orderByDesc('credit_date')->orderByDesc('id')->get();
    }

    /**
     * Unpaid sub-bills (submitted / approved) with what approved receipts settled and what is still due, by work order.
     *
     * @return array{rows: Collection, total: array<string, string>, ages: array<string, string>}
     */
    public function outstanding(?int $contractId): array
    {
        $today = CarbonImmutable::today();
        $rows = Invoice::query()->with(['contract:id,contract_no,agency_id,client_id', 'contract.agency:id,code', 'contract.client:id,name,short_code', 'project:id,code'])
            ->whereIn('status', ['approved', 'submitted'])
            ->when($contractId, fn ($q, $id) => $q->where('gem_contract_id', $id))
            ->orderBy('gem_contract_id')->orderBy('month')->orderBy('invoice_no')->get();
        // What approved receipts settled per sub-bill, in one query (received + every deduction).
        $paid = PaymentReceiptLine::query()->whereIn('invoice_id', $rows->pluck('id'))->whereHas('receipt', fn ($q) => $q->where('status', 'approved'))
            ->get()->groupBy('invoice_id')->map(fn ($ls) => $ls->reduce(fn ($c, $l) => $c->plus(Exact::of($l->settled())), Exact::zero()));
        $rows = $rows
            ->map(function (Invoice $b) use ($today, $paid) {
                $settled = $paid->get($b->id) ?? Exact::zero();
                $due = Exact::of((string) $b->rounded_total)->minus($settled);
                $days = $b->invoice_date ? (int) CarbonImmutable::parse($b->invoice_date)->diffInDays($today) : 0;

                return ['bill' => $b, 'billed' => (string) $b->rounded_total, 'settled' => Exact::money($settled), 'due' => Exact::money($due), 'days' => $days,
                    'age' => match (true) { $days <= 30 => '0-30', $days <= 60 => '31-60', $days <= 90 => '61-90', default => '90+' }];
            })
            ->filter(fn ($r) => (float) $r['due'] > (float) Receipts::TOLERANCE)->values();

        $sum = fn (Collection $c, string $k) => Exact::money($c->reduce(fn ($t, $r) => $t->plus(Exact::of($r[$k])), Exact::zero()));
        $ages = [];
        foreach (array_keys(self::AGES) as $a) {
            $ages[$a] = $sum($rows->where('age', $a), 'due');
        }

        return ['rows' => $rows, 'total' => ['billed' => $sum($rows, 'billed'), 'settled' => $sum($rows, 'settled'), 'due' => $sum($rows, 'due')], 'ages' => $ages];
    }

    /** Deductions on approved receipts credited in the period, one row per sub-bill paid. */
    public function tds(CarbonImmutable $from, CarbonImmutable $to, ?int $contractId, bool $pendingOnly = false): Collection
    {
        return PaymentReceiptLine::query()->with(['receipt:id,agency_id,gem_contract_id,mode,utr_no,cheque_no,credit_date', 'receipt.contract:id,contract_no', 'receipt.agency:id,code', 'invoice:id,invoice_no,month'])
            ->whereHas('receipt', fn ($q) => $q->where('status', 'approved')->whereBetween('credit_date', [$from->toDateString(), $to->toDateString()])
                ->when($contractId, fn ($r, $id) => $r->where('gem_contract_id', $id)))
            ->when($pendingOnly, fn ($q) => $q->where(fn ($w) => $w->where(fn ($a) => $a->where('tds_it', '>', 0)->where('form16a_seen', false))
                ->orWhere(fn ($b) => $b->whereRaw('(tds_cgst + tds_sgst + tds_igst) > 0')->where('gstr7a_accepted', false))))
            ->get()->sortBy([fn ($a, $b) => $a->receipt->credit_date <=> $b->receipt->credit_date, fn ($a, $b) => strnatcmp($a->invoice->invoice_no, $b->invoice->invoice_no)])->values();
    }

    /** Column totals of receipt lines. */
    public static function lineTotals(iterable $lines): array
    {
        $out = [];
        foreach (['bill_amount', 'taxable', 'tds_it', 'tds_cgst', 'tds_sgst', 'tds_igst', 'other_deduction', 'received'] as $f) {
            $t = Exact::zero();
            foreach ($lines as $l) {
                $t = $t->plus(Exact::of((string) $l->{$f}));
            }
            $out[$f] = Exact::money($t);
        }

        return $out;
    }

    /** Indian financial year (1 April – 31 March) holding $day. */
    public static function financialYear(CarbonImmutable $day): array
    {
        $start = $day->month >= 4 ? $day->setDate($day->year, 4, 1) : $day->setDate($day->year - 1, 4, 1);

        return [$start->startOfDay(), $start->addYear()->subDay()->startOfDay()];
    }
}
