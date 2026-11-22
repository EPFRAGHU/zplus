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
use App\Services\Billing\TaxInvoiceBuilder;
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
    // Two levels only (owner, 19 Nov 2026): the maker sends the bill to the owner; no internal "Approve" step any more.
    // Bills already marked approved simply continue to submitted.
    private const NEXT = ['draft' => 'submitted', 'approved' => 'submitted', 'submitted' => 'paid'];

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

        // One block per agency and client, then grouped by GeM contract, bills in serial order (owner, 22 Nov 2026):
        // SW/001/26-27(HTA), SW/001/26-27(NTM), SW/002/26-27(…), …
        $agencyName = Agency::query()->select('name')->whereColumn('agencies.id', 'invoices.agency_id');
        $clientName = Client::query()->select('clients.name')->join('gem_contracts', 'gem_contracts.client_id', '=', 'clients.id')
            ->whereColumn('gem_contracts.id', 'invoices.gem_contract_id');
        // Work order groups follow the serial of their first bill (the group with SW/001 first, then SW/002, …).
        $firstNo = Invoice::query()->from('invoices as first_bills')->selectRaw('min(first_bills.invoice_no)')->whereColumn('first_bills.gem_contract_id', 'invoices.gem_contract_id');
        $sorted = fn () => (clone $filtered)->orderByRaw('agency_id is null')->orderBy($agencyName)->orderBy($clientName)
            ->orderBy($firstNo)->orderBy('gem_contract_id')->orderBy('invoice_no')->orderBy('id');
        $invoices = $sorted()->with('project', 'contract.agency', 'contract.client')->paginate(50)->withQueryString();
        $blockTotals = (clone $filtered)->groupBy('agency_id')
            ->selectRaw("agency_id, count(*) as bills, sum(rounded_total) as billed, sum(case when status in ('approved','submitted') then rounded_total else 0 end) as unpaid")
            ->get()->keyBy('agency_id');
        $clientTotals = (clone $filtered)->join('gem_contracts', 'gem_contracts.id', '=', 'invoices.gem_contract_id')->groupBy('gem_contracts.client_id')
            ->selectRaw("gem_contracts.client_id, count(*) as bills, sum(invoices.rounded_total) as billed, sum(case when invoices.status in ('approved','submitted') then invoices.rounded_total else 0 end) as unpaid")
            ->get()->keyBy('client_id');
        // Column totals per work order and for everything listed (owner, 22 Nov 2026).
        $sums = 'count(*) as bills, sum(wages) as wages, sum(epf) as epf, sum(esic) as esic, sum(service_charge) as service_charge, sum(taxable) as taxable, '
            .'sum(cgst) as cgst, sum(sgst) as sgst, sum(igst) as igst, sum(total) as total, sum(rounded_total) as billed';
        $contractTotals = (clone $filtered)->groupBy('gem_contract_id')->selectRaw('gem_contract_id, '.$sums)->get()->keyBy('gem_contract_id');
        $grandTotal = (clone $filtered)->selectRaw($sums)->first();
        $carriedAgency = $invoices->onFirstPage() ? false
            : $sorted()->skip(($invoices->currentPage() - 1) * $invoices->perPage() - 1)->take(1)->value('agency_id');

        $ready = AttendanceSheet::query()->where('status', 'locked')->doesntHave('invoice')
            ->with('project.contract.agency', 'project.contract.client')->orderByDesc('month')->get();

        // Months still being filled in, so an empty Bills page says what is holding bills up.
        $drafts = AttendanceSheet::query()->where('status', 'draft')
            ->with('project.contract.agency', 'project.contract.client')->orderByDesc('month')->limit(20)->get();

        return view('invoices.index', [
            'invoices' => $invoices, 'ready' => $ready, 'drafts' => $drafts, 'blockTotals' => $blockTotals, 'carriedAgency' => $carriedAgency, 'clientTotals' => $clientTotals, 'contractTotals' => $contractTotals, 'grandTotal' => $grandTotal,
            'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'agencyId' => $agencyId, 'clientId' => $clientId,
            'contracts' => GemContract::query()->with('client:id,name,short_code')->orderBy('bid_no')->orderBy('contract_no')->get(['id', 'agency_id', 'client_id', 'bid_no', 'contract_no']),
            'allProjects' => Project::query()->with('contract:id,agency_id,client_id')->orderBy('name')->get(['id', 'gem_contract_id', 'name', 'pi_name']),
        ]);
    }

    public function store(Request $request, InvoiceGenerator $generator): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate(['attendance_sheet_id' => ['required', new \App\Rules\ExistsInMyAgency(\App\Models\AttendanceSheet::class)]]);
        $sheet = AttendanceSheet::query()->findOrFail($data['attendance_sheet_id']);
        if (! app(\App\Services\Approvals\Approvals::class)->cleared('attendance', $sheet)) {
            return back()->withErrors(['invoice' => 'The attendance of this month is not approved by the owner yet. The sub-bill is made automatically once it is approved.']);
        }
        try {
            $invoice = $generator->generate($sheet, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', "Bill {$invoice->invoice_no} created as a draft.");
    }

    /** Works one draft or approved bill out again with today's rules (and its draft tax invoice, if any). */
    public function recalculate(Request $request, Invoice $invoice, InvoiceGenerator $generator): RedirectResponse
    {
        Gate::authorize('billing');
        try {
            [$old, $new] = $generator->recalculate($invoice, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }
        \App\Support\Activity\Activity::log('updated', $invoice, [], "Recalculated with current rules: ₹{$old} → ₹{$new}", section: 'Sub-bills');

        return redirect()->route('invoices.show', $invoice)->with('status', "Bill {$invoice->fresh()->invoice_no} recalculated: ₹".number_format((float) $old, 2).' → ₹'.number_format((float) $new, 2).'.');
    }

    /** Recalculates every open bill (draft / approved) made under the old rules, in the user's own agency. */
    public function recalculateOutdated(Request $request, InvoiceGenerator $generator): RedirectResponse
    {
        Gate::authorize('billing');
        $done = $skipped = [];
        foreach (InvoiceGenerator::outdated()->get() as $invoice) {
            try {
                [$old, $new] = $generator->recalculate($invoice, $request->user());
                \App\Support\Activity\Activity::log('updated', $invoice, [], "Recalculated with current rules: ₹{$old} → ₹{$new}", section: 'Sub-bills');
                $done[] = $invoice->fresh()->invoice_no;
            } catch (RuntimeException $e) {
                $skipped[] = $e->getMessage();
            }
        }
        $taxes = [];
        foreach (TaxInvoiceBuilder::outdated()->get() as $tax) {
            try {
                [$oldNo, $newNo, $oldTotal, $newTotal] = app(TaxInvoiceBuilder::class)->refresh($tax);
                \App\Support\Activity\Activity::log('updated', $tax, [], "Recalculated with current rules: {$oldNo} ₹{$oldTotal} → {$newNo} ₹{$newTotal}", section: 'Tax invoices');
                $taxes[] = "{$oldNo} → {$newNo}";
            } catch (RuntimeException $e) {
                $skipped[] = $e->getMessage();
            }
        }
        $msg = count($done).' '.\Illuminate\Support\Str::plural('bill', count($done)).' recalculated with the current rules'.($done ? ': '.implode(', ', $done) : '').'.'
            .($taxes ? ' Tax invoices renumbered: '.implode(', ', $taxes).'.' : '');

        $response = back()->with('status', $msg);

        return $skipped ? $response->withErrors(['invoice' => implode(' ', $skipped)]) : $response;
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
        if ($data['status'] === 'submitted' && ! app(\App\Services\Approvals\Approvals::class)->cleared('sub_bill', $invoice)) {
            return back()->withErrors(['invoice' => 'The owner has not approved this sub-bill yet. Use "Send for approval" first.']);
        }

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
        // Super Admin (owner, 17 Nov 2026): any bill that is not paid; it is taken out of its tax invoice first.
        if (Gate::allows('delete-records') && $invoice->status !== 'draft') {
            if ($invoice->status === 'paid') {
                return back()->withErrors(['invoice' => 'Paid bills cannot be deleted.']);
            }
            try {
                $released = app(\App\Services\Billing\TaxInvoiceBuilder::class)->release($invoice);
            } catch (RuntimeException $e) {
                return back()->withErrors(['invoice' => $e->getMessage()]);
            }
            $note = "Sub-bill deleted: {$invoice->invoice_no} (₹{$invoice->rounded_total}, {$invoice->status})".($released ? "; {$released}" : '');
            $invoice->lines()->delete();
            $invoice->delete();
            \App\Support\Activity\Activity::log('deleted', null, [], $note, section: 'Sub-bills', label: $invoice->invoice_no);

            return redirect()->route('invoices.index')->with('status', $note.'. The attendance month stays locked; make the bill again or reset the month.');
        }
        if ($invoice->status !== 'draft') {
            return back()->withErrors(['invoice' => 'Only draft bills can be deleted.']);
        }
        if ($invoice->tax_invoice_id) {
            return back()->withErrors(['invoice' => 'This sub-bill is in a tax invoice. Delete the draft tax invoice first.']);
        }
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Draft bill deleted. The same number is used when the bill is made again.');
    }

    public function pdf(Invoice $invoice): Response
    {
        return Pdf::loadView('pdf.invoice', self::pdfData($invoice))->setPaper('a4')->stream(str_replace('/', '-', $invoice->invoice_no).'.pdf');
    }

    public function coveringLetter(Invoice $invoice): Response
    {
        GeneratedDocument::query()->firstOrCreate(
            ['type' => 'covering_letter', 'documentable_type' => Invoice::class, 'documentable_id' => $invoice->id],
            ['number' => $invoice->invoice_no, 'created_by' => auth()->id()],
        );

        return Pdf::loadView('pdf.covering-letter', self::pdfData($invoice))->setPaper('a4')
            ->stream('covering-letter-'.str_replace('/', '-', $invoice->invoice_no).'.pdf');
    }

    public static function pdfData(Invoice $invoice): array
    {
        $invoice->load('lines', 'project.incharge', 'contract.agency', 'contract.client');

        return ['invoice' => $invoice, 'agency' => $invoice->contract->agency, 'client' => $invoice->contract->client, 'project' => $invoice->project, 'contract' => $invoice->contract];
    }
}
