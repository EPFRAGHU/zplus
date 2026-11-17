<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\Payroll\BankLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bank Letter: one salary transfer letter per agency and month, for all the employees of all its projects whose month
 * is locked (or the ticked projects), on the agency's own letterhead.
 */
class BankLetterController extends Controller
{
    public function __construct(private readonly BankLetter $letters) {}

    public function index(Request $request): View
    {
        Gate::authorize('view-bank-details');
        $agencies = Agency::query()->orderBy('name')->get();
        $agency = $agencies->firstWhere('id', $request->integer('agency')) ?? ($agencies->count() === 1 ? $agencies->first() : null);
        $month = $this->month($request->input('month'));
        $data = ['agencies' => $agencies, 'agency' => $agency, 'month' => $month, 'letter' => null, 'pending' => collect(), 'all' => collect()];
        if ($agency) {
            [$locked, $pending] = $this->letters->sheets($agency, $month);
            $chosen = $request->has('projects') ? $locked->whereIn('project_id', array_map('intval', (array) $request->input('projects')))->values() : $locked;
            $data = ['letter' => $this->letters->build($agency, $month, $chosen), 'pending' => $pending, 'all' => $locked, 'chosen' => $chosen->pluck('project_id')->all()] + $data;
        }

        return view('bank-letters.index', $data);
    }

    public function pdf(Request $request): Response
    {
        Gate::authorize('view-bank-details');
        $request->validate(['agency' => ['required', new \App\Rules\ExistsInMyAgency(Agency::class)], 'month' => ['required', 'date_format:Y-m'],
            'projects' => ['nullable', 'array'], 'projects.*' => ['integer']]);
        $agency = Agency::query()->findOrFail($request->integer('agency'));
        $month = $this->month($request->input('month'));
        [$locked] = $this->letters->sheets($agency, $month, $request->input('projects') ? array_map('intval', (array) $request->input('projects')) : null);
        abort_if($locked->isEmpty(), 422, 'No locked attendance for this agency and month.');
        $approvals = app(\App\Services\Approvals\Approvals::class);
        abort_unless(\App\Services\Approvals\Approvals::canApprove($request->user()) || $locked->every(fn ($s) => $approvals->payoutClearedFor($s)), 403,
            'The salary payout for this month is not approved by the owner yet. Use "Send for approval" on the Bank Letter page.');
        $data = $this->letters->build($agency, $month, $locked);

        return Pdf::loadView('pdf.bank-letter', $data)->setPaper('a4')
            ->stream('bank-letter-'.$agency->numberPrefix().'-'.$month->format('Y-m').'.pdf');
    }

    private function month(?string $value): CarbonImmutable
    {
        return $value && preg_match('/^\d{4}-\d{2}$/', $value) ? CarbonImmutable::createFromFormat('!Y-m', $value)
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }
}
