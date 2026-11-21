<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Post;
use App\Services\Reports\PostWiseBills;
use App\Support\Exact;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Reports for the agency's own use; they show figures already in the sub-bills and change nothing. */
class ReportController extends Controller
{
    public function postWise(Request $request, PostWiseBills $report): View
    {
        Gate::authorize('billing');

        return view('reports.post-wise', $this->postWiseData($request, $report));
    }

    public function postWisePdf(Request $request, PostWiseBills $report): Response
    {
        Gate::authorize('billing');
        $data = $this->postWiseData($request, $report);

        return Pdf::loadView('pdf.post-wise', $data)->setPaper('a4')
            ->stream('post-wise-sub-bills-'.$data['month']->format('Y-m').'.pdf');
    }

    private function postWiseData(Request $request, PostWiseBills $report): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->input('month'))
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $agencyId = $request->integer('agency') ?: \App\Support\Agency\AgencyContext::id();
        $post = $request->string('post')->trim()->value() ?: null;
        $groups = $report->month($month, $agencyId, $post);

        return [
            'month' => $month, 'agencyId' => $agencyId, 'post' => $post, 'groups' => $groups,
            'agency' => $agencyId ? Agency::query()->find($agencyId) : null,
            'agencies' => Agency::query()->orderBy('name')->get(),
            'designations' => Post::query()->pluck('designation')->groupBy(fn ($d) => PostController::postKey($d))->map->first()->sort()->values(),
            'total' => Exact::money($groups->reduce(fn ($c, $g) => $c->plus(Exact::of($g['amount'])), Exact::zero())),
            'staff' => $groups->sum('staff'),
        ];
    }
}
