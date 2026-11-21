<?php

namespace App\Services\Reports;

use App\Http\Controllers\PostController;
use App\Models\Invoice;
use App\Support\Exact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Post-wise view of a month's sub-bills (owner, 22 Nov 2026), for the agency's own use: for each post, the projects
 * that have it, how many staff, the days paid and the wages billed. Read from the sub-bills as made; nothing is changed.
 */
class PostWiseBills
{
    /**
     * @return Collection<int, array{post: string, rows: Collection, staff: int, days: string, amount: string}>
     */
    public function month(CarbonImmutable $month, ?int $agencyId = null, ?string $post = null): Collection
    {
        $bills = Invoice::query()->with('lines', 'project.contract.agency')
            ->where('month', $month->startOfMonth()->toDateString())
            ->when($agencyId, fn ($q, $id) => $q->whereHas('project.contract', fn ($c) => $c->where('agency_id', $id)))
            ->get();

        $groups = [];
        foreach ($bills as $bill) {
            foreach ($bill->lines as $line) {
                $key = PostController::postKey($line->designation);
                if ($post && $key !== PostController::postKey($post)) {
                    continue;
                }
                $groups[$key] ??= ['post' => $line->designation, 'rows' => []];
                $row = &$groups[$key]['rows'][$bill->id];
                $row ??= ['bill' => $bill, 'project' => $bill->project, 'agency' => $bill->project->contract->agency, 'staff' => 0,
                    'days' => Exact::zero(), 'amount' => Exact::zero(), 'rates' => []];
                $row['staff']++;
                $row['days'] = $row['days']->plus(Exact::of((string) $line->paid_days));
                $row['amount'] = $row['amount']->plus(Exact::of((string) $line->amount));
                $row['rates'][(string) $line->rate] = true;
                unset($row);
            }
        }
        ksort($groups);

        return collect($groups)->map(function ($g) {
            $rows = collect($g['rows'])->sortBy(fn ($r) => $r['agency']->name.'|'.$r['project']->code)->values()
                ->map(fn ($r) => ['bill' => $r['bill'], 'project' => $r['project'], 'agency' => $r['agency'], 'staff' => $r['staff'],
                    'days' => self::days($r['days']), 'amount' => Exact::money($r['amount']), 'rates' => implode(', ', array_map(fn ($x) => number_format((float) $x, 2), array_keys($r['rates'])))]);

            return ['post' => $g['post'], 'rows' => $rows, 'staff' => $rows->sum('staff'),
                'days' => self::days($rows->reduce(fn ($c, $r) => $c->plus(Exact::of($r['days'])), Exact::zero())),
                'amount' => Exact::money($rows->reduce(fn ($c, $r) => $c->plus(Exact::of($r['amount'])), Exact::zero()))];
        })->values();
    }

    private static function days($v): string
    {
        return rtrim(rtrim((string) $v->toScale(1, \Brick\Math\RoundingMode::HalfUp), '0'), '.');
    }
}
