<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AttendanceSheet;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\Project;
use App\Services\Attendance\AttendanceCodes;
use App\Services\Attendance\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $month = $this->month($request->input('month'));
        $sheets = AttendanceSheet::query()->where('month', $month->toDateString())->withCount('entries')->with('invoice')->get()->keyBy('project_id');
        $agencyId = $request->integer('agency') ?: null;
        $clientId = $request->integer('client') ?: null;
        $projects = Project::query()->with('contract.agency', 'contract.client')
            ->withCount(['employees' => fn ($q) => $q->whereIn('employees.status', ['active', 'notice'])])
            ->when($agencyId, fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('agency_id', $agencyId)))
            ->when($clientId, fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('client_id', $clientId)))
            ->when($request->integer('contract'), fn ($q, $id) => $q->where('gem_contract_id', $id))
            ->when($request->integer('project'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->sortBy(fn ($p) => $p->contract->agency->name.'|'.$p->contract->client->name.'|'.$p->name)->values();

        return view('attendance.index', [
            'month' => $month, 'sheets' => $sheets, 'projects' => $projects,
            'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'agencyId' => $agencyId, 'clientId' => $clientId,
            'contracts' => GemContract::query()->orderBy('bid_no')->get(['id', 'agency_id', 'client_id', 'bid_no']),
            'allProjects' => Project::query()->with('contract:id,agency_id,client_id')->orderBy('name')->get(['id', 'gem_contract_id', 'name', 'pi_name']),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'month' => ['required', 'date_format:Y-m'],
            'holidays' => ['nullable', 'regex:/^\s*\d{1,2}(\s*,\s*\d{1,2})*\s*$/'],
        ], ['holidays.regex' => 'List holiday dates as numbers separated by commas, e.g. 2, 19, 20.']);
        $holidays = array_values(array_filter(array_map('intval', explode(',', (string) ($data['holidays'] ?? '')))));
        $sheet = $this->attendance->open(Project::query()->findOrFail($data['project_id']), $this->month($data['month']), $holidays);

        return redirect()->route('attendance.show', $sheet);
    }

    public function show(AttendanceSheet $sheet): View
    {
        $sheet->load('entries.employee.post', 'project.contract.agency', 'project.contract.client', 'invoice', 'submitter', 'changes.user');

        return view('attendance.show', ['sheet' => $sheet, 'codes' => AttendanceCodes::LABELS]);
    }

    public function update(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $data = $request->validate([
            'codes' => ['required', 'array'],
            'codes.*' => ['array'],
            'codes.*.*' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceCodes::LABELS))],
        ]);

        $changed = 0;
        $response = $this->attempt(function () use ($sheet, $data, $request, &$changed) {
            $changed = $this->attendance->save($sheet, $data['codes'], $request->user());
        }, 'Attendance saved.');

        return $changed ? $response->with('status', "Attendance saved. {$changed} ".\Illuminate\Support\Str::plural('change', $changed).' recorded as made by the agency after the PI submitted.') : $response;
    }

    public function lock(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $request->validate(['certificate' => [$sheet->certificate_path ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ['certificate.required' => 'Upload the certificate signed by the PI before locking the month.']);
        $path = $request->file('certificate')?->store("certificates/{$sheet->month->format('Y-m')}", 'local');
        try {
            $this->attendance->lock($sheet, $request->user(), $path);
        } catch (RuntimeException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        // Approved: the sub-bill for this project and month is made straight away (as a draft for Accounts).
        try {
            $invoice = app(\App\Services\Billing\InvoiceGenerator::class)->generate($sheet->fresh(), $request->user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('status', 'Month locked.')->withErrors(['attendance' => 'The sub-bill could not be made automatically: '.$e->getMessage().' Make it from the Sub-bills page.']);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', "Month locked. Sub-bill {$invoice->invoice_no} made as a draft.");
    }

    public function unlock(AttendanceSheet $sheet): RedirectResponse
    {
        Gate::authorize('unlock-attendance');

        return $this->attempt(fn () => $this->attendance->unlock($sheet), 'Month unlocked.');
    }

    /** Salary payout (bank transfer) sheet for a locked month: earned salary, employee EPF and ESIC, net pay and bank details. */
    public function payouts(AttendanceSheet $sheet, \App\Services\Payroll\SalaryPayoutSheet $payouts): Response
    {
        Gate::authorize('view-bank-details');
        abort_unless($sheet->isLocked(), 422, 'Lock the month before making the salary payout sheet.');
        $sheet->load('entries.employee.post', 'project.contract.client', 'project.contract.agency');

        return Pdf::loadView('pdf.salary-payouts', ['sheet' => $sheet, 'lines' => $payouts->lines($sheet), 'agency' => $sheet->project->contract->agency])
            ->setPaper('a4', 'landscape')->stream("salary-payouts-{$sheet->project->code}-{$sheet->month->format('Y-m')}.pdf");
    }

    /** Printable "Monthly Attendance and Working Certificate" for the PI to sign. */
    public function certificate(AttendanceSheet $sheet): Response
    {
        $sheet->load('entries.employee.post', 'project.contract.client', 'project.contract.agency');
        $showBank = Gate::allows('view-bank-details');

        return Pdf::loadView('pdf.attendance-certificate', compact('sheet', 'showBank'))
            ->setPaper('a4', 'landscape')->stream("attendance-{$sheet->project->code}-{$sheet->month->format('Y-m')}.pdf");
    }

    /** The certificate exactly as the PI submitted it. */
    public function submittedPdf(AttendanceSheet $sheet): Response
    {
        abort_unless($sheet->submitted_pdf_path && Storage::disk('local')->exists($sheet->submitted_pdf_path), 404);

        return Storage::disk('local')->response($sheet->submitted_pdf_path, "attendance-{$sheet->project->code}-{$sheet->month->format('Y-m')}-as-submitted.pdf");
    }

    public function scan(AttendanceSheet $sheet): Response
    {
        abort_unless($sheet->certificate_path && Storage::disk('local')->exists($sheet->certificate_path), 404);

        return Storage::disk('local')->response($sheet->certificate_path);
    }

    private function month(?string $value): CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('Y-m', substr($value, 0, 7))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }

    private function attempt(callable $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['attendance' => $e->getMessage()]);
        }

        return back()->with('status', $done);
    }
}
