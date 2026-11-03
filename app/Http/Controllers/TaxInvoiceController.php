<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Models\TaxInvoice;
use App\Services\Billing\TaxInvoiceBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TaxInvoiceController extends Controller
{
    private const NEXT = ['draft' => 'submitted', 'submitted' => 'paid'];

    public function __construct(private readonly TaxInvoiceBuilder $builder) {}

    public function index(Request $request): View
    {
        $agencyId = $request->integer('agency') ?: null;
        $clientId = $request->integer('client') ?: null;
        $taxInvoices = TaxInvoice::query()->with('agency', 'client')->withCount('subBills')
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($request->input('month'), fn ($q, $m) => $q->where('month', $m.'-01'))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->get()
            ->sortBy([fn ($a, $b) => strcmp($a->agency->name, $b->agency->name), fn ($a, $b) => strcmp($a->client->name, $b->client->name),
                fn ($a, $b) => $b->month <=> $a->month])->values();

        return view('tax-invoices.index', [
            'taxInvoices' => $taxInvoices, 'agencyId' => $agencyId, 'clientId' => $clientId,
            'agencies' => Agency::query()->orderBy('name')->get(), 'clients' => Client::query()->with('agency')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('billing');
        $client = $request->integer('client') ? Client::query()->with('agency')->find($request->integer('client')) : null;
        $month = $this->month($request->input('month'));

        return view('tax-invoices.create', [
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'client' => $client, 'month' => $month,
            'pending' => $client ? $this->builder->pending($client, $month) : collect(),
            'suggested' => $client ? $this->builder->suggestNumber($client, $month) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate([
            'client_id' => ['required', new \App\Rules\ExistsInMyAgency(\App\Models\Client::class)],
            'month' => ['required', 'date_format:Y-m'],
            'sub_bills' => ['required', 'array', 'min:1'],
            'sub_bills.*' => ['integer', new \App\Rules\ExistsInMyAgency(\App\Models\Invoice::class)],
            'number' => ['nullable', 'string', 'max:40'],
            'invoice_date' => ['required', 'date'],
        ], ['sub_bills.required' => 'Tick at least one sub-bill to include.']);

        try {
            $tax = $this->builder->create(Client::query()->findOrFail($data['client_id']), $this->month($data['month']),
                $data['sub_bills'], $request->user(), $data['number'] ?? null, $data['invoice_date']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['sub_bills' => $e->getMessage()]);
        }

        return redirect()->route('tax-invoices.show', $tax)->with('status', "Tax invoice {$tax->number} created.");
    }

    public function show(TaxInvoice $taxInvoice): View
    {
        $taxInvoice->load('agency', 'client', 'subBills.project', 'subBills.contract');

        return view('tax-invoices.show', ['tax' => $taxInvoice]);
    }

    public function status(Request $request, TaxInvoice $taxInvoice): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate([
            'status' => ['required', Rule::in([self::NEXT[$taxInvoice->status] ?? null])],
            'paid_on' => ['required_if:status,paid', 'nullable', 'date'],
        ]);
        $taxInvoice->forceFill(['status' => $data['status'], 'paid_on' => $data['paid_on'] ?? $taxInvoice->paid_on])->save();
        if ($data['status'] === 'paid') {
            // A paid tax invoice settles its sub-bills too.
            $taxInvoice->subBills()->update(['status' => 'paid', 'paid_on' => $data['paid_on']]);
        }

        return back()->with('status', 'Tax invoice marked '.TaxInvoice::STATUSES[$data['status']].'.');
    }

    public function destroy(TaxInvoice $taxInvoice): RedirectResponse
    {
        Gate::authorize('billing');
        try {
            $this->builder->delete($taxInvoice);
        } catch (RuntimeException $e) {
            return back()->withErrors(['tax' => $e->getMessage()]);
        }

        return redirect()->route('tax-invoices.index')->with('status', 'Draft tax invoice deleted. Its sub-bills can be invoiced again.');
    }

    public function pdf(TaxInvoice $taxInvoice): Response
    {
        $taxInvoice->load('agency', 'client', 'subBills.project', 'subBills.contract');

        return Pdf::loadView('pdf.tax-invoice', ['tax' => $taxInvoice, 'agency' => $taxInvoice->agency, 'client' => $taxInvoice->client])
            ->setPaper('a4')->stream('tax-invoice-'.str_replace('/', '-', $taxInvoice->number).'.pdf');
    }

    private function month(?string $value): CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('Y-m', substr($value, 0, 7))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }
}
