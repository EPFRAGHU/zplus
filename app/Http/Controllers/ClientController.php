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
        $clients = Client::query()->with('agency')->withCount('contracts')
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('gstin', 'like', "%{$s}%")))
            ->when($request->integer('agency'), fn ($q, $id) => $q->where('agency_id', $id))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('clients.index', ['clients' => $clients, 'agencies' => Agency::query()->orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-masters');
        // "Add for the other agency" pre-fills the form from an existing client.
        $from = $request->integer('copy') ? Client::query()->find($request->integer('copy')) : null;
        $client = $from ? $from->replicate(['agency_id']) : new Client(['state_code' => '21', 'payment_term_days' => 30, 'status' => 'active']);
        if (! $from && $request->integer('agency')) {
            $client->agency_id = $request->integer('agency');   // "Add client" from the Agencies page
        }

        return view('clients.form', ['client' => $client, 'agencies' => Agency::query()->orderBy('name')->get(), 'copiedFrom' => $from]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        Client::query()->create($this->validated($request));

        return redirect()->route('clients.index')->with('status', 'Client added.');
    }

    public function edit(Client $client): View
    {
        Gate::authorize('manage-masters');

        return view('clients.form', ['client' => $client, 'agencies' => Agency::query()->orderBy('name')->get(),
            'hasContracts' => $client->contracts()->exists()]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $this->validated($request, $client);
        if ((int) $data['agency_id'] !== (int) $client->agency_id && $client->contracts()->exists()) {
            return back()->withInput()->withErrors(['agency_id' => 'This client already has GeM contracts under '
                .($client->agency?->name ?? 'its agency').'. Add the client again for the other agency instead.']);
        }
        $client->update($data);

        return redirect()->route('clients.index')->with('status', 'Client saved.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'agency_id' => ['required', 'exists:agencies,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'alpha_num', 'max:12',
                Rule::unique('clients')->where('agency_id', $request->input('agency_id'))->ignore($client)],
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
        ], [
            'agency_id.required' => 'Choose the agency this client works with.',
            'short_code.unique' => 'This agency already has a client with this bill code.',
            'gstin.regex' => 'Enter a 15-character GSTIN, e.g. 21AAAGO1234F1Z5.',
        ]);
    }
}
