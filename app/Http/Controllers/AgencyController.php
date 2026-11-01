<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(): View
    {
        return view('agencies.index', ['agencies' => Agency::query()->withCount(['contracts', 'clients'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('manage-masters');

        return view('agencies.form', ['agency' => new Agency]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        Agency::query()->create($this->validated($request));

        return redirect()->route('agencies.index')->with('status', 'Agency added.');
    }

    public function edit(Agency $agency): View
    {
        Gate::authorize('manage-masters');

        return view('agencies.form', compact('agency'));
    }

    public function update(Request $request, Agency $agency): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $agency->update($this->validated($request, $agency));

        return redirect()->route('agencies.index')->with('status', 'Agency saved.');
    }

    private function validated(Request $request, ?Agency $agency = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_num', 'max:10', Rule::unique('agencies')->ignore($agency)],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
        ], ['gstin.regex' => 'Enter a 15-character GSTIN, e.g. 21AAAGO1234F1Z5.', 'pan.regex' => 'Enter a 10-character PAN, e.g. ABCDE1234F.']);
    }
}
