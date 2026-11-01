<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Support\Ifsc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AgencyController extends Controller
{
    public function index(): View
    {
        return view('agencies.index', ['agencies' => Agency::query()
            ->with(['clients' => fn ($q) => $q->withCount(['contracts', 'employees' => fn ($e) => $e->whereIn('status', ['active', 'notice'])])->orderBy('name')])
            ->withCount(['contracts', 'clients', 'employees' => fn ($q) => $q->whereIn('status', ['active', 'notice'])])
            ->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('manage-masters');

        return view('agencies.form', ['agency' => new Agency]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $agency = Agency::query()->create(Arr::except($this->validated($request), ['logo']));
        $this->saveLogo($request, $agency);

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
        $agency->update(Arr::except($this->validated($request, $agency), ['logo']));
        $this->saveLogo($request, $agency);

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
            'tagline' => ['nullable', 'string', 'max:255'],
            'registrations' => ['nullable', 'string', 'max:1000'],
            'letterhead_style' => ['sometimes', Rule::in(['classic', 'banner'])],
            'website' => ['nullable', 'string', 'max:255'],
            'letterhead_emails' => ['nullable', 'string', 'max:255'],
            'phones' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'regex:/^\d{6,20}$/'],
            'bank_ifsc' => ['nullable', fn ($a, $v, $fail) => Ifsc::isValid($v) || $fail('An IFSC has 11 characters: 4 letters, a zero, then 6 letters or digits.')],
            'bank_branch' => ['nullable', 'string', 'max:255'],
        ], [
            'gstin.regex' => 'Enter a 15-character GSTIN, e.g. 21AAAGO1234F1Z5.', 'pan.regex' => 'Enter a 10-character PAN, e.g. ABCDE1234F.',
            'bank_account_no.regex' => 'Enter the account number using digits only.',
            'logo.max' => 'The logo must be 1 MB or smaller.', 'logo.image' => 'Upload the logo as a PNG or JPG image.',
        ]);
    }

    /** Store an uploaded logo on the private disk and drop the old one. */
    private function saveLogo(Request $request, Agency $agency): void
    {
        if ($request->boolean('remove_logo') && $agency->logo_path) {
            Storage::disk('local')->delete($agency->logo_path);
            $agency->update(['logo_path' => null]);
        }
        if ($file = $request->file('logo')) {
            if ($agency->logo_path) {
                Storage::disk('local')->delete($agency->logo_path);
            }
            $agency->update(['logo_path' => $file->storeAs('letterheads', 'agency-'.$agency->id.'-'.time().'.'.$file->extension(), 'local')]);
        }
    }

    /** Shows the logo on the agency form (the file sits on the private disk). */
    public function logo(Agency $agency): Response
    {
        abort_unless($agency->logo_path && Storage::disk('local')->exists($agency->logo_path), 404);

        return Storage::disk('local')->response($agency->logo_path);
    }
}
