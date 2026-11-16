<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
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
        $contract = $request->integer('contract') ? GemContract::query()->with('agency', 'client')->find($request->integer('contract')) : null;
        $month = $this->month($request->input('month'));

        return view('tax-invoices.create', [
            'contracts' => GemContract::query()->with('agency', 'client', 'projects')->get()
                ->sortBy(fn ($k) => $k->agency->name.'|'.$k->client->name.'|'.$k->contract_no)->values(),
            'contract' => $contract, 'month' => $month,
            'pending' => $contract ? $this->builder->pending($contract, $month) : collect(),
            'suggested' => $contract ? $this->builder->suggestNumber($contract, $month) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('billing');
        $data = $request->validate([
            'gem_contract_id' => ['required', new \App\Rules\ExistsInMyAgency(GemContract::class)],
            'month' => ['required', 'date_format:Y-m'],
            'sub_bills' => ['required', 'array', 'min:1'],
            'sub_bills.*' => ['integer', new \App\Rules\ExistsInMyAgency(\App\Models\Invoice::class)],
            'number' => ['nullable', 'string', 'max:40'],
            'invoice_date' => ['required', 'date'],
        ], ['sub_bills.required' => 'Tick at least one sub-bill to include.']);

        try {
            $tax = $this->builder->create(GemContract::query()->findOrFail($data['gem_contract_id']), $this->month($data['month']),
                $data['sub_bills'], $request->user(), $data['number'] ?? null, $data['invoice_date']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['sub_bills' => $e->getMessage()]);
        }

        return redirect()->route('tax-invoices.show', $tax)->with('status', "Tax invoice {$tax->number} created.");
    }

    public function show(TaxInvoice $taxInvoice): View
    {
        $data = self::pdfData($taxInvoice);

        return view('tax-invoices.show', ['tax' => $taxInvoice, 't' => $data['t']]);
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

    /** Gives an unpaid tax invoice made under the old rules its work order number (SW/001/26-27) and current figures. */
    public function recalculate(TaxInvoice $taxInvoice): RedirectResponse
    {
        Gate::authorize('billing');
        try {
            [$oldNo, $newNo, $oldTotal, $newTotal] = $this->builder->refresh($taxInvoice);
        } catch (RuntimeException $e) {
            return back()->withErrors(['tax' => $e->getMessage()]);
        }
        \App\Support\Activity\Activity::log('updated', $taxInvoice, [], "Recalculated with current rules: {$oldNo} ₹{$oldTotal} → {$newNo} ₹{$newTotal}", section: 'Tax invoices');

        return redirect()->route('tax-invoices.show', $taxInvoice)->with('status', "Tax invoice {$oldNo} is now {$newNo}: ₹".number_format((float) $oldTotal, 2).' → ₹'.number_format((float) $newTotal, 2).'.');
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
        return Pdf::loadView('pdf.tax-invoice', $this->pdfData($taxInvoice))
            ->setPaper('a4')->stream('tax-invoice-'.str_replace('/', '-', $taxInvoice->number).'.pdf');
    }

    /** Everything the tax invoice PDF needs: exact totals over all its sub-bills' staff, and the post-wise rows. */
    public static function pdfData(TaxInvoice $taxInvoice): array
    {
        $taxInvoice->load('agency', 'client', 'contract', 'subBills.project', 'subBills.contract', 'subBills.lines');
        $taxInvoice->setRelation('subBills', $taxInvoice->subBills->sortBy('id')->values());
        $contract = $taxInvoice->contract ?? $taxInvoice->subBills->first()->contract;
        $totals = TaxInvoiceBuilder::totals($taxInvoice->subBills, $contract);

        return ['tax' => $taxInvoice, 'agency' => $taxInvoice->agency, 'client' => $taxInvoice->client, 't' => $totals,
            'p' => TaxInvoiceBuilder::particulars($totals, (string) $contract->epf_percent)];
    }

    private function month(?string $value): CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('Y-m', substr($value, 0, 7))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }
}
