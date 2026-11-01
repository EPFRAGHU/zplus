<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSheet;
use App\Models\GeneratedDocument;
use App\Models\Invoice;
use App\Services\Billing\InvoiceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    private const NEXT = ['draft' => 'approved', 'approved' => 'submitted', 'submitted' => 'paid'];

    public function index(Request $request): View
    {
        $invoices = Invoice::query()->with('project', 'contract.agency')
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('month'), fn ($q, $m) => $q->where('month', $m.'-01'))
            ->latest('month')->latest('id')->paginate(30)->withQueryString();

        $ready = AttendanceSheet::query()->where('status', 'locked')->doesntHave('invoice')->with('project')->orderByDesc('month')->get();

        // Months still being filled in, so an empty Bills page says what is holding bills up.
        $drafts = AttendanceSheet::query()->where('status', 'draft')->with('project')->orderByDesc('month')->limit(20)->get();

        return view('invoices.index', compact('invoices', 'ready', 'drafts'));
    }

    public function store(Request $request, InvoiceGenerator $generator): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate(['attendance_sheet_id' => ['required', 'exists:attendance_sheets,id']]);
        try {
            $invoice = $generator->generate(AttendanceSheet::query()->findOrFail($data['attendance_sheet_id']), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', "Bill {$invoice->invoice_no} created as a draft.");
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('lines', 'project', 'contract.agency', 'contract.client', 'sheet');

        return view('invoices.show', compact('invoice'));
    }

    public function status(Request $request, Invoice $invoice): RedirectResponse
    {
        $next = self::NEXT[$invoice->status] ?? null;
        $data = $request->validate([
            'status' => ['required', Rule::in([$next])],
            'paid_on' => ['required_if:status,paid', 'nullable', 'date'],
        ]);
        Gate::authorize($data['status'] === 'approved' ? 'approve-invoice' : 'billing');

        $invoice->forceFill(array_filter([
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'approved' ? $request->user()->id : null,
            'approved_at' => $data['status'] === 'approved' ? now() : null,
            'paid_on' => $data['paid_on'] ?? null,
        ], fn ($v) => $v !== null))->save();

        return back()->with('status', 'Bill marked '.Invoice::STATUSES[$data['status']].'.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('billing');
        if ($invoice->status !== 'draft') {
            return back()->withErrors(['invoice' => 'Only draft bills can be deleted.']);
        }
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Draft bill deleted. Its number will not be reused.');
    }

    public function pdf(Invoice $invoice): Response
    {
        return Pdf::loadView('pdf.invoice', $this->pdfData($invoice))->setPaper('a4')->stream(str_replace('/', '-', $invoice->invoice_no).'.pdf');
    }

    public function coveringLetter(Invoice $invoice): Response
    {
        GeneratedDocument::query()->firstOrCreate(
            ['type' => 'covering_letter', 'documentable_type' => Invoice::class, 'documentable_id' => $invoice->id],
            ['number' => $invoice->invoice_no, 'created_by' => auth()->id()],
        );

        return Pdf::loadView('pdf.covering-letter', $this->pdfData($invoice))->setPaper('a4')
            ->stream('covering-letter-'.str_replace('/', '-', $invoice->invoice_no).'.pdf');
    }

    private function pdfData(Invoice $invoice): array
    {
        $invoice->load('lines', 'project', 'contract.agency', 'contract.client');

        return ['invoice' => $invoice, 'agency' => $invoice->contract->agency, 'client' => $invoice->contract->client, 'project' => $invoice->project, 'contract' => $invoice->contract];
    }
}
