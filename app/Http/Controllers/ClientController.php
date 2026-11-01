<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()->with('agencies')->withCount('contracts')
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('gstin', 'like', "%{$s}%")))
            ->when($request->integer('agency'), fn ($q, $id) => $q->whereHas('agencies', fn ($a) => $a->whereKey($id)))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('clients.index', ['clients' => $clients, 'agencies' => Agency::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('manage-masters');

        return view('clients.form', ['client' => new Client(['state_code' => '21', 'payment_term_days' => 30, 'status' => 'active']),
            'agencies' => Agency::query()->orderBy('name')->get(), 'selected' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $this->validated($request);
        $client = Client::query()->create($data);
        $client->agencies()->sync($data['agency_ids']);

        return redirect()->route('clients.index')->with('status', 'Client added.');
    }

    public function edit(Client $client): View
    {
        Gate::authorize('manage-masters');

        return view('clients.form', ['client' => $client, 'agencies' => Agency::query()->orderBy('name')->get(),
            'selected' => $client->agencies()->pluck('agencies.id')->all(),
            'locked' => $client->contracts()->distinct()->pluck('agency_id')->all()]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $this->validated($request, $client);
        $inUse = $client->contracts()->distinct()->pluck('agency_id')->all();
        $missing = array_diff($inUse, $data['agency_ids']);
        if ($missing) {
            $names = Agency::query()->whereIn('id', $missing)->pluck('name')->join(', ');

            return back()->withInput()->withErrors(['agency_ids' => "{$names} still has GeM contracts with this client, so it cannot be removed."]);
        }
        $client->update($data);
        $client->agencies()->sync($data['agency_ids']);

        return redirect()->route('clients.index')->with('status', 'Client saved.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'alpha_num', 'max:12', Rule::unique('clients')->ignore($client)],
            'letter_code' => ['nullable', 'alpha_num', 'max:12'],
            'addressee' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'district' => ['nullable', 'string', 'max:100'],
            'state_code' => ['required', 'digits:2'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(['active', 'hold', 'inactive'])],
            'agency_ids' => ['required', 'array', 'min:1'],
            'agency_ids.*' => ['integer', 'exists:agencies,id'],
        ], [
            'gstin.regex' => 'Enter a 15-character GSTIN, e.g. 21AAAGO1234F1Z5.',
            'agency_ids.required' => 'Choose at least one agency this client works with.',
        ]);
    }
}
