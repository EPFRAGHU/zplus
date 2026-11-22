<?php

namespace App\Services\Reports;

use App\Http\Controllers\PostController;
use App\Models\Invoice;
use App\Support\Exact;
use Brick\Math\BigRational;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Post-wise summary of a month's sub-bills (owner, 22 Nov 2026), for filing GeM returns: for a work order, one sub-bill or
 * all of them, and optionally one post, the staff, days, wages, EPF, ESIC, sub total, service charge, value before GST and
 * GST per post. Wages, EPF and ESIC are stored per person on each sub-bill; the service charge and GST (charged on the whole
 * bill) are shared out per post with the bill's own formula, so the post rows add up to the bills. Nothing is changed.
 */
class PostSummary
{
    public const MONEY = ['wages', 'epf', 'esic', 'subtotal', 'service_charge', 'taxable', 'cgst', 'sgst', 'igst', 'total'];

    /**
     * @return array{bills: Collection, rows: list<array>, total: array<string, mixed>, stored: array<string, string>, igst: bool}
     */
    public function build(CarbonImmutable $month, ?int $agencyId, ?int $contractId, ?int $invoiceId, ?string $post): array
    {
        $bills = Invoice::query()->with(['lines', 'project', 'contract'])
            ->where('month', $month->startOfMonth()->toDateString())
            ->when($agencyId, fn ($q, $id) => $q->where('agency_id', $id))
            ->when($contractId, fn ($q, $id) => $q->where('gem_contract_id', $id))
            ->when($invoiceId, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('invoice_no')->get();

        $groups = [];
        foreach ($bills as $bill) {
            $scRate = Exact::of((string) $bill->service_charge_percent)->dividedBy(100);
            if ($bill->service_charge_includes_gst) {
                $scRate = $scRate->dividedBy(Exact::of('1')->plus(Exact::of((string) $bill->gst_percent)->dividedBy(100)));
            }
            $gstRate = Exact::of((string) $bill->gst_percent)->dividedBy(100);
            $inter = (float) $bill->igst > 0;
            foreach ($bill->lines as $line) {
                $key = PostController::postKey($line->designation);
                if ($post && $key !== PostController::postKey($post)) {
                    continue;
                }
                $g = &$groups[$key];
                $g ??= ['post' => $line->designation, 'staff' => 0, 'days' => Exact::zero(), 'bills' => []]
                    + array_fill_keys(self::MONEY, Exact::zero());
                $wages = Exact::of((string) $line->amount);
                $epf = Exact::of((string) ($line->epf ?? '0'));
                $esic = Exact::of((string) ($line->esic ?? '0'));
                $sub = $wages->plus($epf)->plus($esic);
                $sc = $sub->multipliedBy($scRate);
                $taxable = $sub->plus($sc);
                $gst = $taxable->multipliedBy($gstRate);
                $g['staff']++;
                $g['days'] = $g['days']->plus(Exact::of((string) $line->paid_days));
                $g['bills'][$bill->invoice_no] = true;
                foreach (['wages' => $wages, 'epf' => $epf, 'esic' => $esic, 'subtotal' => $sub, 'service_charge' => $sc, 'taxable' => $taxable] as $f => $v) {
                    $g[$f] = $g[$f]->plus($v);
                }
                if ($inter) {
                    $g['igst'] = $g['igst']->plus($gst);
                } else {
                    $g['cgst'] = $g['cgst']->plus($gst->dividedBy(2));
                    $g['sgst'] = $g['sgst']->plus($gst->dividedBy(2));
                }
                $g['total'] = $g['total']->plus($taxable)->plus($gst);
                unset($g);
            }
        }
        ksort($groups);

        $days = fn (BigRational $d) => rtrim(rtrim((string) $d->toScale(1, \Brick\Math\RoundingMode::HalfUp), '0'), '.');
        $total = ['staff' => 0, 'days' => Exact::zero()] + array_fill_keys(self::MONEY, Exact::zero());
        $rows = [];
        foreach ($groups as $g) {
            $row = ['post' => $g['post'], 'staff' => $g['staff'], 'days' => $days($g['days']), 'bills' => implode(', ', array_keys($g['bills']))];
            foreach (self::MONEY as $f) {
                $row[$f] = Exact::money($g[$f]);
                $total[$f] = $total[$f]->plus($g[$f]);
            }
            $total['staff'] += $g['staff'];
            $total['days'] = $total['days']->plus($g['days']);
            $rows[] = $row;
        }
        $total['days'] = $days($total['days']);
        foreach (self::MONEY as $f) {
            $total[$f] = Exact::money($total[$f]);
        }

        // The same figures as stored on the sub-bills themselves (whole bills), to compare when no post is chosen.
        $stored = [];
        foreach (['wages', 'epf', 'esic', 'subtotal', 'service_charge', 'taxable', 'cgst', 'sgst', 'igst', 'total'] as $f) {
            $stored[$f] = Exact::money($bills->reduce(fn ($c, $b) => $c->plus(Exact::of((string) ($b->{$f} ?? '0'))), Exact::zero()));
        }

        return ['bills' => $bills, 'rows' => $rows, 'total' => $total, 'stored' => $stored, 'igst' => (float) $total['igst'] > 0];
    }
}
