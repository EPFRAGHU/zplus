<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSheet;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Attendance\AttendanceCodes;
use App\Services\Attendance\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PI's own pages: mark and submit attendance for their projects, download the certificate,
 * upload the signed copy, and view the project's sub-bills. Anything outside their projects is "not found".
 */
class PiPortalController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function home(Request $request): View
    {
        $projects = $this->projects($request)->load('contract.agency', 'contract.client');
        $sheets = AttendanceSheet::query()->whereIn('project_id', $projects->pluck('id'))->with('invoice')->orderByDesc('month')->get()->groupBy('project_id');
        $months = collect([0, 1])->map(fn ($n) => CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($n));

        return view('pi.home', ['projects' => $projects, 'sheets' => $sheets, 'months' => $months]);
    }

    public function start(Request $request, Project $project): RedirectResponse
    {
        $this->own($request, $project->id);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $data['month'])->startOfMonth();
        abort_if($month->gt(CarbonImmutable::now()->startOfMonth()), 422, 'That month has not started yet.');

        return redirect()->route('pi.sheet', $this->attendance->open($project, $month));
    }

    public function sheet(Request $request, AttendanceSheet $sheet): View
    {
        $this->ownSheet($request, $sheet);
        $sheet->load('entries.employee.post', 'project.contract.agency', 'project.contract.client', 'invoice', 'submitter');

        return view('pi.sheet', ['sheet' => $sheet, 'codes' => AttendanceCodes::LABELS]);
    }

    public function save(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $this->ownSheet($request, $sheet);
        if ($sheet->status !== 'draft') {
            return back()->withErrors(['attendance' => 'This month has been submitted and can no longer be changed here. Contact the agency if something is wrong.']);
        }
        $data = $request->validate([
            'codes' => ['required', 'array'], 'codes.*' => ['array'],
            'codes.*.*' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceCodes::LABELS))],
        ]);

        return $this->attempt(fn () => $this->attendance->save($sheet, $data['codes']), 'Attendance saved. You can keep changing it until you submit.');
    }

    public function submit(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $this->ownSheet($request, $sheet);

        return $this->attempt(fn () => $this->attendance->submit($sheet, $request->user()),
            'Attendance submitted. Download the PDF, sign it and upload the signed copy here (or e-mail it to the agency).');
    }

    /** Submitted: the certificate exactly as submitted. Before that: a preview of what is saved so far. */
    public function pdf(Request $request, AttendanceSheet $sheet): Response
    {
        $this->ownSheet($request, $sheet);
        $name = "attendance-{$sheet->project->code}-{$sheet->month->format('Y-m')}.pdf";
        if ($sheet->submitted_pdf_path && Storage::disk('local')->exists($sheet->submitted_pdf_path)) {
            return Storage::disk('local')->response($sheet->submitted_pdf_path, $name);
        }

        return response($this->attendance->certificatePdf($sheet), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=\"{$name}\""]);
    }

    public function uploadSigned(Request $request, AttendanceSheet $sheet): RedirectResponse
    {
        $this->ownSheet($request, $sheet);
        if (! $sheet->isSubmitted()) {
            return back()->withErrors(['signed' => $sheet->isLocked() ? 'This month is already approved by the agency.' : 'Submit the attendance first, then upload the signed copy.']);
        }
        $request->validate(['signed' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ['signed.required' => 'Choose the signed PDF or a photo of it.', 'signed.mimes' => 'Upload a PDF, JPG or PNG.']);
        $old = $sheet->certificate_path;
        $sheet->forceFill(['certificate_path' => $request->file('signed')->store("certificates/{$sheet->month->format('Y-m')}", 'local')])->save();
        if ($old && $old !== $sheet->certificate_path) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('status', 'Signed copy uploaded. The agency will check and approve it.');
    }

    public function signed(Request $request, AttendanceSheet $sheet): Response
    {
        $this->ownSheet($request, $sheet);
        abort_unless($sheet->certificate_path && Storage::disk('local')->exists($sheet->certificate_path), 404);

        return Storage::disk('local')->response($sheet->certificate_path);
    }

    public function bills(Request $request): View
    {
        $invoices = Invoice::query()->whereIn('project_id', $this->projects($request)->pluck('id'))
            ->with('project.contract.agency', 'project.contract.client')->orderByDesc('month')->orderBy('invoice_no')->get();

        return view('pi.bills', ['invoices' => $invoices]);
    }

    public function billPdf(Request $request, Invoice $invoice): Response
    {
        $this->own($request, (int) $invoice->project_id);
        $invoice->load('lines', 'project', 'contract.agency', 'contract.client');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'agency' => $invoice->contract->agency, 'client' => $invoice->contract->client,
            'project' => $invoice->project, 'contract' => $invoice->contract])->setPaper('a4')->stream(str_replace('/', '-', $invoice->invoice_no).'.pdf');
    }

    /** @return Collection<int, Project> */
    private function projects(Request $request): Collection
    {
        return $request->user()->projects()->orderBy('name')->get();
    }

    private function own(Request $request, int $projectId): void
    {
        abort_unless($request->user()->projects()->whereKey($projectId)->exists(), 404);
    }

    private function ownSheet(Request $request, AttendanceSheet $sheet): void
    {
        $this->own($request, (int) $sheet->project_id);
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
