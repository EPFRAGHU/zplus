<?php

namespace App\Http\Controllers;

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
        $clients = Client::query()->withCount('contracts')
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('gstin', 'like', "%{$s}%")))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        Gate::authorize('manage-masters');

        return view('clients.form', ['client' => new Client(['state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'])]);
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

        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $client->update($this->validated($request, $client));

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
        ], ['gstin.regex' => 'Enter a 15-character GSTIN, e.g. 21AAAGO1234F1Z5.']);
    }
}
