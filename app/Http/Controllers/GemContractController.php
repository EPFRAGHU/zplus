<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GemContractController extends Controller
{
    public function index(Request $request): View
    {
        $contracts = GemContract::query()
            ->with(['agency', 'client', 'projects.posts' => fn ($q) => $q->withCount('activeEmployees')])
            ->when($request->integer('agency'), fn ($q, $id) => $q->where('agency_id', $id))
            ->when($request->integer('client'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('bid_no', 'like', "%{$s}%")->orWhere('contract_no', 'like', "%{$s}%")
                ->orWhereHas('projects', fn ($p) => $p->where('name', 'like', "%{$s}%")->orWhere('pi_name', 'like', "%{$s}%"))))
            ->get()
            ->sortBy(fn ($c) => $c->agency->name.'|'.$c->client->name.'|'.$c->bid_no)->values();

        return view('contracts.index', ['contracts' => $contracts, 'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'agencyId' => $request->integer('agency') ?: null, 'clientId' => $request->integer('client') ?: null]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-masters');

        return view('contracts.form', $this->formData(new GemContract([
            'contract_type' => 'online', 'service_charge_includes_gst' => true, 'billing_type' => 'monthly', 'sac_code' => '998514', 'service_charge_percent' => '3.85', 'gst_percent' => '18', 'epf_percent' => '13', 'esic_percent' => '3.25',
            // "Add contract for this client" pre-fills the agency and client.
            'agency_id' => $request->integer('agency') ?: null, 'client_id' => $request->integer('client') ?: null,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $contract = GemContract::query()->create($this->validated($request));

        return redirect()->route('contracts.show', $contract)->with('status', 'Work order added. Now add its projects and posts.');
    }

    /** Work order page: its projects, then the month's sub-bills (one per project), then the month's tax invoice. */
    public function show(Request $request, GemContract $contract): View
    {
        $contract->load(['agency', 'client', 'projects.incharge', 'projects.posts' => fn ($q) => $q->with('activeEmployees')]);
        $month = $request->input('month') && preg_match('/^\d{4}-\d{2}$/', $request->input('month'))
            ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $request->input('month'))
            : \Carbon\CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $sheets = \App\Models\AttendanceSheet::query()->with('invoice.taxInvoice')
            ->whereIn('project_id', $contract->projects->pluck('id'))->where('month', $month->toDateString())->get()->keyBy('project_id');
        $subBills = Invoice::query()->with('project', 'taxInvoice')->where('gem_contract_id', $contract->id)
            ->where('month', $month->toDateString())->orderBy('invoice_no')->get();

        return view('contracts.show', [
            'contract' => $contract, 'month' => $month, 'sheets' => $sheets, 'subBills' => $subBills,
            'taxInvoices' => $contract->taxInvoices()->where('month', $month->toDateString())->get(),
            'pending' => $subBills->whereNull('tax_invoice_id'),
            'nextTaxNumber' => app(\App\Services\Billing\TaxInvoiceBuilder::class)->suggestNumber($contract, $month),
            'incharges' => \App\Models\ProjectIncharge::query()->where('agency_id', $contract->agency_id)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function edit(GemContract $contract): View
    {
        Gate::authorize('manage-masters');

        return view('contracts.form', $this->formData($contract));
    }

    public function update(Request $request, GemContract $contract): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $contract->update($this->validated($request, $contract));

        return redirect()->route('contracts.show', $contract)->with('status', 'Work order saved.');
    }

    private function formData(GemContract $contract): array
    {
        return ['contract' => $contract, 'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'locked' => $contract->exists && Invoice::query()->where('gem_contract_id', $contract->id)->exists(),
            // Saved work orders (this login's agencies), so typing a known number points to it instead of making a duplicate.
            'saved' => GemContract::query()->whereKeyNot($contract->id ?? 0)->get(['id', 'bid_no', 'contract_no'])
                ->map(fn ($k) => ['bid' => $k->bid_no, 'no' => $k->contract_no, 'url' => route('contracts.show', $k).'#add-project'])->values()];
    }

    private function validated(Request $request, ?GemContract $contract = null): array
    {
        $data = $request->validate([
            'agency_id' => ['required', new \App\Rules\ExistsInMyAgency(\App\Models\Agency::class)],
            'client_id' => ['required', new \App\Rules\ExistsInMyAgency(\App\Models\Client::class)],
            'contract_type' => ['nullable', Rule::in(array_keys(GemContract::TYPES))],
            'bid_no' => [Rule::requiredIf(fn () => $request->input('contract_type', 'online') !== 'offline'), 'nullable', 'string', 'max:40'],
            'contract_no' => ['required', 'string', 'max:40', Rule::unique('gem_contracts')->ignore($contract)],
            'contract_date' => ['nullable', 'date'],
            'billing_type' => ['required', Rule::in(['monthly', 'daily'])],
            'sac_code' => ['nullable', 'digits_between:4,8'],
            'service_charge_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'gst_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'epf_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'esic_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ], [
            'agency_id.required' => 'Choose the agency that raises bills on this contract.',
            'client_id.required' => 'Choose the client this contract is with.',
            'bid_no.required' => 'Enter the GeM bid number of this online contract.',
            'contract_no.unique' => 'This contract / work order number is already saved. Open it from Work Orders and use "Add project" to add another project under it.',
        ]);
        $data['inter_state'] = $request->boolean('inter_state');
        $data['contract_type'] = $data['contract_type'] ?? null ?: 'online';
        $data['service_charge_includes_gst'] = $request->has('service_charge_includes_gst') ? $request->boolean('service_charge_includes_gst') : true;
        $data['bid_no'] = $data['bid_no'] ?? '';   // offline work orders have no GeM bid
        $data['sac_code'] = $data['sac_code'] ?? null ?: '998514';

        $client = Client::query()->findOrFail($data['client_id']);
        if ((int) $client->agency_id !== (int) $data['agency_id']) {
            throw ValidationException::withMessages(['client_id' => 'This client belongs to a different agency. Choose a client of the selected agency.']);
        }
        if ($contract && ((int) $contract->agency_id !== (int) $data['agency_id'] || (int) $contract->client_id !== (int) $data['client_id'])
            && $contract->projects()->whereHas('attendanceSheets', fn ($q) => $q->whereHas('invoice'))->exists()) {
            throw ValidationException::withMessages(['agency_id' => 'Bills have already been made on this contract, so its agency and client cannot change.']);
        }

        return $data;
    }
}
