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
            ->with(['agency', 'client', 'projects.incharge', 'projects.posts' => fn ($q) => $q->withCount('activeEmployees')])
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

    public function store(Request $request, \App\Services\Masters\WorkOrderSetup $setup): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $projects = $this->setupRows($request);
        $data = $this->validated($request);
        [$contract, $counts] = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $projects, $setup, $request) {
            $contract = GemContract::query()->create($data);

            return [$contract, $setup->addProjects($contract, $projects, $request->user())];
        });
        if ($counts['projects'] === 0) {
            return redirect()->route('contracts.show', $contract)->with('status', 'Work order added. Now add its projects and posts.');
        }
        $staffNote = $counts['staff'] && \App\Services\Approvals\StaffChanges::applies() ? ' New staff stay Inactive until a Super Admin approves them.' : '';

        return redirect()->route('contracts.show', $contract)->with('status', sprintf('Work order added with %d %s, %d %s and %d staff.%s',
            $counts['projects'], \Illuminate\Support\Str::plural('project', $counts['projects']), $counts['posts'], \Illuminate\Support\Str::plural('post', $counts['posts']), $counts['staff'], $staffNote));
    }

    /** The same one-page entry on an existing work order: more projects, with their PI, posts and staff (owner, 22 Nov 2026). */
    public function setup(Request $request, GemContract $contract, \App\Services\Masters\WorkOrderSetup $setup): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $projects = $this->setupRows($request);
        if (! $projects) {
            return back()->withErrors(['setup' => 'Enter at least one project (short name and project name).']);
        }
        $taken = $contract->projects()->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        foreach ($projects as $i => $p) {
            if (in_array(strtoupper($p['code']), $taken, true)) {
                throw ValidationException::withMessages(["setup.{$i}.code" => 'This work order already has a project '.strtoupper($p['code']).'. Use another short name, or add posts and staff to it below.']);
            }
        }
        $counts = $setup->addProjects($contract, $projects, $request->user());
        $staffNote = $counts['staff'] && \App\Services\Approvals\StaffChanges::applies() ? ' New staff stay Inactive until a Super Admin approves them.' : '';

        return redirect()->route('contracts.show', $contract)->with('status', sprintf('Added %d %s, %d %s and %d staff.%s',
            $counts['projects'], \Illuminate\Support\Str::plural('project', $counts['projects']), $counts['posts'], \Illuminate\Support\Str::plural('post', $counts['posts']), $counts['staff'], $staffNote));
    }

    /**
     * Projects, posts and staff from the one-page form. Rows left completely blank are ignored; a staff member's
     * "post" is the position of that post in the same project's list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function setupRows(Request $request): array
    {
        $blank = fn (array $row, array $keys) => collect($keys)->every(fn ($k) => blank($row[$k] ?? null));
        $projects = collect($request->input('setup', []))->filter(fn ($p) => is_array($p) && ! $blank($p, ['code', 'name', 'pi_name', 'project_incharge_id']))
            ->map(function ($p) use ($blank) {
                $p['posts'] = array_values(array_filter((array) ($p['posts'] ?? []), fn ($r) => is_array($r) && ! $blank($r, ['designation', 'rate'])));
                $p['staff'] = array_values(array_filter((array) ($p['staff'] ?? []), fn ($r) => is_array($r) && ! $blank($r, ['name', 'mobile'])));

                return $p;
            })->values()->all();
        $request->merge(['setup' => $projects]);
        $validated = $request->validate([
            'setup' => ['array', 'max:30'],
            'setup.*.code' => ['required', \App\Support\Codes::PATTERN, 'max:12', 'distinct:ignore_case'],
            'setup.*.name' => ['required', 'string', 'max:255'],
            'setup.*.project_incharge_id' => ['nullable', new \App\Rules\ExistsInMyAgency(\App\Models\ProjectIncharge::class)],
            'setup.*.pi_name' => ['nullable', 'string', 'max:255'],
            'setup.*.pi_mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'setup.*.posts' => ['array', 'max:60'],
            'setup.*.posts.*.designation' => ['required', 'string', 'max:255'],
            'setup.*.posts.*.sanctioned_qty' => ['required', 'integer', 'min:1', 'max:999'],
            'setup.*.posts.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'setup.*.staff' => ['array', 'max:200'],
            'setup.*.staff.*.name' => ['required', 'string', 'max:255'],
            'setup.*.staff.*.post' => ['required', 'integer', 'min:0'],
            'setup.*.staff.*.joining_date' => ['nullable', 'date'],
            'setup.*.staff.*.mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'setup.*.staff.*.pf' => ['nullable', 'boolean'],
            'setup.*.staff.*.esic' => ['nullable', 'boolean'],
            'setup.*.staff.*.tds' => ['nullable', 'boolean'],
        ], [
            'setup.*.code.required' => 'Enter the short project name (e.g. MPH) for every project.',
            'setup.*.code.distinct' => 'Two projects have the same short name.',
            'setup.*.name.required' => 'Enter the project name for every project.',
            'setup.*.posts.*.designation.required' => 'Every post needs a designation.',
            'setup.*.posts.*.sanctioned_qty.required' => 'Every post needs the number of sanctioned posts.',
            'setup.*.posts.*.rate.required' => 'Every post needs its rate.',
            'setup.*.staff.*.name.required' => 'Every staff row needs a name.',
            'setup.*.staff.*.post.required' => 'Choose the post of every staff member.',
            'setup.*.pi_mobile.regex' => 'Enter a 10-digit mobile number for the new Project In-charge.',
            'setup.*.staff.*.mobile.regex' => 'Enter a 10-digit mobile number for each staff member.',
        ], ['setup.*.code' => 'short project name']);
        foreach ($validated['setup'] ?? [] as $i => $p) {
            foreach ($p['staff'] ?? [] as $s) {
                if (! isset($p['posts'][$s['post']])) {
                    throw ValidationException::withMessages(["setup.{$i}.staff" => 'In project '.$p['code'].', choose a post from that project for '.$s['name'].'.']);
                }
            }
        }

        return $validated['setup'] ?? [];
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
            'papers' => \App\Models\MonthlyPaper::query()->with('uploader')->where('gem_contract_id', $contract->id)->where('month', $month->toDateString())->orderBy('id')->get()->groupBy('kind'),
            'needs' => self::statutoryNeeds($contract),
            'incharges' => \App\Models\ProjectIncharge::query()->where('agency_id', $contract->agency_id)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /** Whether anyone on this work order has EPF / ESIC switched on (so its challan is needed each month). */
    public static function statutoryNeeds(GemContract $contract): array
    {
        $staff = \App\Models\Employee::query()->whereIn('status', ['active', 'notice'])
            ->whereHas('post.project', fn ($q) => $q->where('gem_contract_id', $contract->id));

        return ['epf_challan' => (clone $staff)->where('pf_applicable', true)->exists(), 'esi_challan' => (clone $staff)->where('esic_applicable', true)->exists()];
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
            'incharges' => \App\Models\ProjectIncharge::query()->where('status', 'active')->orderBy('name')->get(['id', 'agency_id', 'name']),
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
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
            'end_date.after_or_equal' => 'The service end date must be on or after the start date.',
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

    /** Super Admin: deletes a work order only when nothing depends on it (owner, 17 Nov 2026). */
    public function destroy(GemContract $contract): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-masters');
        if ($why = \App\Support\SafeDelete::contract($contract)) {
            return back()->withErrors(['delete' => $why]);
        }
        $label = $contract->contract_no;
        $contract->delete();
        \App\Support\Activity\Activity::log('deleted', null, [], "Work order deleted: {$label}", section: 'Masters', label: $label);

        return redirect()->route('contracts.index')->with('status', "Work order {$label} deleted.");
    }
}
