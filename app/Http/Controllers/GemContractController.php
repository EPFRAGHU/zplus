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

    public function create(): View
    {
        Gate::authorize('manage-masters');

        return view('contracts.form', $this->formData(new GemContract([
            'billing_type' => 'monthly', 'service_charge_percent' => '3.85', 'gst_percent' => '18', 'epf_percent' => '13', 'esic_percent' => '3.25',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $contract = GemContract::query()->create($this->validated($request));

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract added. Now add its projects and posts.');
    }

    public function show(GemContract $contract): View
    {
        $contract->load(['agency', 'client', 'projects.posts' => fn ($q) => $q->with('activeEmployees')]);

        return view('contracts.show', compact('contract'));
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

        return redirect()->route('contracts.show', $contract)->with('status', 'Contract saved.');
    }

    private function formData(GemContract $contract): array
    {
        return ['contract' => $contract, 'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'locked' => $contract->exists && Invoice::query()->where('gem_contract_id', $contract->id)->exists()];
    }

    private function validated(Request $request, ?GemContract $contract = null): array
    {
        $data = $request->validate([
            'agency_id' => ['required', 'exists:agencies,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'bid_no' => ['required', 'string', 'max:40'],
            'contract_no' => ['required', 'string', 'max:40', Rule::unique('gem_contracts')->ignore($contract)],
            'contract_date' => ['nullable', 'date'],
            'billing_type' => ['required', Rule::in(['monthly', 'daily'])],
            'service_charge_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'gst_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'epf_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'esic_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ], [
            'agency_id.required' => 'Choose the agency that raises bills on this contract.',
            'client_id.required' => 'Choose the client this contract is with.',
        ]);
        $data['inter_state'] = $request->boolean('inter_state');

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
