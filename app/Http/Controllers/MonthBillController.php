<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\Billing\MonthBill;
use App\Support\Activity\Activity;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Month bill: an agency's month in one sheet, one row per work order (owner, 22 Nov 2026). Internal, not a GST invoice. */
class MonthBillController extends Controller
{
    public function show(Request $request, MonthBill $monthBill): View
    {
        Gate::authorize('billing');
        [$agency, $month] = $this->pick($request);

        return view('month-bill.show', ['agency' => $agency, 'month' => $month, 'agencies' => Agency::query()->orderBy('name')->get(),
            'consolidated' => $agency ? $monthBill->consolidated($agency, $month) : null]
            + ($agency ? $monthBill->build($agency, $month) : ['rows' => collect(), 'total' => null]));
    }

    public function pdf(Request $request, MonthBill $monthBill): Response
    {
        Gate::authorize('billing');
        [$agency, $month] = $this->pick($request);
        abort_unless($agency, 404);
        Activity::log('downloaded', $agency, [], 'Month bill '.$month->format('F Y').' (PDF)', section: 'Billing', label: $agency->code.' '.$month->format('M Y'));

        return Pdf::loadView('pdf.month-bill', ['agency' => $agency, 'month' => $month] + $monthBill->build($agency, $month))
            ->setPaper('a4', 'landscape')->stream('month-bill-'.$agency->code.'-'.$month->format('Y-m').'.pdf');
    }

    /** Consolidated tax invoice statement PDF: the month's tax invoices in the tax invoice layout, not a new tax invoice (owner, 22 Nov 2026). */
    public function consolidated(Request $request, MonthBill $monthBill): Response
    {
        Gate::authorize('billing');
        [$agency, $month] = $this->pick($request);
        abort_unless($agency, 404);
        $c = $monthBill->consolidated($agency, $month);
        abort_unless($c, 404, 'No tax invoices for this month yet.');
        Activity::log('downloaded', $agency, [], 'Consolidated tax invoice statement '.$month->format('F Y').' (PDF)', section: 'Billing', label: $agency->code.' '.$month->format('M Y'));

        return Pdf::loadView('pdf.consolidated-tax-invoice', ['agency' => $agency, 'month' => $month, 'c' => $c])
            ->setPaper('a4')->stream('consolidated-tax-invoice-statement-'.$agency->code.'-'.$month->format('Y-m').'.pdf');
    }

    /** Agency (only the login's own: other agencies are out of scope and give 404) and month (default: last month). */
    private function pick(Request $request): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->input('month'))
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $agencyId = $request->integer('agency') ?: \App\Support\Agency\AgencyContext::id();
        $agency = $agencyId ? Agency::query()->findOrFail($agencyId) : (Agency::query()->count() === 1 ? Agency::query()->first() : null);

        return [$agency, $month];
    }
}
