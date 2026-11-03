<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AttendanceSheet;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\GeneratedDocument;
use App\Models\Invoice;
use App\Models\Project;
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
        $agencyId = $request->integer('agency') ?: null;
        $clientId = $request->integer('client') ?: null;
        $filtered = Invoice::query()
            ->when($agencyId, fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('agency_id', $agencyId)))
            ->when($clientId, fn ($q) => $q->whereHas('contract', fn ($c) => $c->where('client_id', $clientId)))
            ->when($request->integer('contract'), fn ($q, $id) => $q->where('gem_contract_id', $id))
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('month'), fn ($q, $m) => $q->where('month', $m.'-01'));

        // One block per agency: sort by agency name, then newest bill first.
        $agencyName = Agency::query()->select('name')->whereColumn('agencies.id', 'invoices.agency_id');
        $clientName = Client::query()->select('clients.name')->join('gem_contracts', 'gem_contracts.client_id', '=', 'clients.id')
            ->whereColumn('gem_contracts.id', 'invoices.gem_contract_id');
        $sorted = fn () => (clone $filtered)->orderByRaw('agency_id is null')->orderBy($agencyName)->orderBy($clientName)->latest('month')->latest('id');
        $invoices = $sorted()->with('project', 'contract.agency', 'contract.client')->paginate(50)->withQueryString();
        $blockTotals = (clone $filtered)->groupBy('agency_id')
            ->selectRaw("agency_id, count(*) as bills, sum(rounded_total) as billed, sum(case when status in ('approved','submitted') then rounded_total else 0 end) as unpaid")
            ->get()->keyBy('agency_id');
        $clientTotals = (clone $filtered)->join('gem_contracts', 'gem_contracts.id', '=', 'invoices.gem_contract_id')->groupBy('gem_contracts.client_id')
            ->selectRaw("gem_contracts.client_id, count(*) as bills, sum(invoices.rounded_total) as billed, sum(case when invoices.status in ('approved','submitted') then invoices.rounded_total else 0 end) as unpaid")
            ->get()->keyBy('client_id');
        $carriedAgency = $invoices->onFirstPage() ? false
            : $sorted()->skip(($invoices->currentPage() - 1) * $invoices->perPage() - 1)->take(1)->value('agency_id');

        $ready = AttendanceSheet::query()->where('status', 'locked')->doesntHave('invoice')
            ->with('project.contract.agency', 'project.contract.client')->orderByDesc('month')->get();

        // Months still being filled in, so an empty Bills page says what is holding bills up.
        $drafts = AttendanceSheet::query()->where('status', 'draft')
            ->with('project.contract.agency', 'project.contract.client')->orderByDesc('month')->limit(20)->get();

        return view('invoices.index', [
            'invoices' => $invoices, 'ready' => $ready, 'drafts' => $drafts, 'blockTotals' => $blockTotals, 'carriedAgency' => $carriedAgency, 'clientTotals' => $clientTotals,
            'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'agencyId' => $agencyId, 'clientId' => $clientId,
            'contracts' => GemContract::query()->orderBy('bid_no')->get(['id', 'agency_id', 'client_id', 'bid_no']),
            'allProjects' => Project::query()->with('contract:id,agency_id,client_id')->orderBy('name')->get(['id', 'gem_contract_id', 'name', 'pi_name']),
        ]);
    }

    public function store(Request $request, InvoiceGenerator $generator): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate(['attendance_sheet_id' => ['required', new \App\Rules\ExistsInMyAgency(\App\Models\AttendanceSheet::class)]]);
        try {
            $invoice = $generator->generate(AttendanceSheet::query()->findOrFail($data['attendance_sheet_id']), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', "Bill {$invoice->invoice_no} created as a draft.");
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('lines', 'project', 'contract.agency', 'contract.client', 'sheet', 'taxInvoice');

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
        if ($invoice->tax_invoice_id) {
            return back()->withErrors(['invoice' => 'This sub-bill is in a tax invoice. Delete the draft tax invoice first.']);
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
