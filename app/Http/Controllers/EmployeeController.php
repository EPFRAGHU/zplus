<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\GeneratedDocument;
use App\Models\Post;
use App\Models\Project;
use App\Support\AmountInWords;
use App\Support\Ifsc;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $filtered = Employee::query()
            ->when($request->integer('agency'), fn ($q, $id) => $q->where('agency_id', $id))
            ->when($request->integer('client'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")))
            ->when($request->integer('contract'), fn ($q, $id) => $q->whereHas('post.project', fn ($p) => $p->where('gem_contract_id', $id)))
            ->when($request->integer('project'), fn ($q, $id) => $q->whereHas('post', fn ($p) => $p->where('project_id', $id)))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('bank')->value() === 'problem', fn ($q) => $q->where(fn ($w) => $w->whereNull('ifsc')->orWhereNull('account_last4')));

        // One block per agency (ZPS, SWH, ...): sort by agency name, then client, then employee name.
        $agencyName = Agency::query()->select('name')->whereColumn('agencies.id', 'employees.agency_id');
        $clientName = Client::query()->select('name')->whereColumn('clients.id', 'employees.client_id');
        $employees = (clone $filtered)->with('post.project.contract', 'agency', 'client')
            ->orderByRaw('agency_id is null')->orderBy($agencyName)->orderBy($clientName)->orderBy('name')
            ->paginate(50)->withQueryString();
        $blockCounts = (clone $filtered)->selectRaw('agency_id, count(*) as total')->groupBy('agency_id')->pluck('total', 'agency_id');
        $clientCounts = (clone $filtered)->selectRaw('agency_id, client_id, count(*) as total, sum(case when post_id is null then 0 else 1 end) as placed')
            ->groupBy('agency_id', 'client_id')->get()->keyBy(fn ($r) => $r->agency_id.'-'.$r->client_id);
        // Agency of the last employee on the previous page, so a block split across pages says "continued".
        $carriedAgency = $employees->onFirstPage() ? false : (clone $filtered)
            ->orderByRaw('agency_id is null')->orderBy($agencyName)->orderBy($clientName)->orderBy('name')
            ->skip(($employees->currentPage() - 1) * $employees->perPage() - 1)->take(1)->value('agency_id');

        return view('employees.index', ['employees' => $employees, 'blockCounts' => $blockCounts, 'carriedAgency' => $carriedAgency, 'clientCounts' => $clientCounts, 'projects' => Project::query()->with('contract')->orderBy('name')->get(),
            'contracts' => GemContract::query()->orderBy('bid_no')->get(['id', 'agency_id', 'client_id', 'bid_no']),
            'agencies' => Agency::query()->orderBy('name')->get(), 'clients' => Client::query()->with('agency')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('manage-masters');

        $agencyId = auth()->user()->agency_id;   // an agency login knows its prefix now; others get it when the agency is chosen

        return view('employees.form', ['employee' => new Employee(['status' => 'active', 'agency_id' => $agencyId, 'emp_code' => $agencyId ? Employee::nextCode($agencyId) : null])] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $this->validated($request);
        $data['emp_code'] = $data['emp_code'] ?? null ?: Employee::nextCode((int) $data['agency_id']);   // blank: SW-1001 / ZP-1001 …
        $employee = app(\App\Services\Approvals\StaffChanges::class)->create($data, $request->user());

        return redirect()->route('employees.show', $employee)->with('status', \App\Services\Approvals\StaffChanges::applies()
            ? 'Employee added and sent to the owner for approval. Until approved the employee stays Inactive (not on attendance or bills).' : 'Employee added.');
    }

    public function show(Employee $employee): View
    {
        $employee->load('post.project.contract.agency', 'documents', 'agency', 'client');
        $letters = GeneratedDocument::query()->where('documentable_type', Employee::class)->where('documentable_id', $employee->id)->latest()->get();

        return view('employees.show', compact('employee', 'letters'));
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('manage-masters');

        return view('employees.form', ['employee' => $employee] + $this->formData());
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-masters');
        try {
            $held = app(\App\Services\Approvals\StaffChanges::class)->update($employee, $this->validated($request, $employee), $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['approval' => $e->getMessage()]);
        }

        return redirect()->route('employees.show', $employee)->with('status', $held
            ? 'Employee saved. Sent to the owner for approval (applied once approved): '.implode(', ', $held).'.' : 'Employee saved.');
    }

    /** Appointment letter in the SWACHHA format, numbered once per employee. */
    public function appointmentLetter(Employee $employee): Response
    {
        Gate::authorize('manage-masters');
        $employee->load('post.project.contract.agency', 'post.project.contract.client');
        abort_unless($employee->post, 422, 'Link this employee to a post before making the appointment letter.');

        $project = $employee->post->project;
        $contract = $project->contract;
        $doc = GeneratedDocument::query()->firstOrCreate(
            ['type' => 'appointment_letter', 'documentable_type' => Employee::class, 'documentable_id' => $employee->id],
            ['created_by' => auth()->id()],
        );
        if (! $doc->number) {
            $yy = ($employee->joining_date ?? now())->format('y');
            $prefix = implode('/', [$contract->agency->code, $contract->client->letter_code ?: $contract->client->short_code, $project->code]);
            $doc->update(['number' => sprintf('%s/%02d/%s', $prefix, DocumentSequence::next("appointment:{$prefix}:{$yy}"), $yy)]);
        }

        $rate = $employee->rate();

        return Pdf::loadView('pdf.appointment-letter', [
            'employee' => $employee, 'project' => $project, 'contract' => $contract, 'agency' => $contract->agency,
            'number' => $doc->number, 'rate' => $rate, 'rateWords' => AmountInWords::rupees($rate),
        ])->setPaper('a4')->stream("appointment-".\App\Support\Codes::fileSafe($employee->emp_code).".pdf");
    }

    /** Switch EPF, ESIC and TDS on or off from the employees list. */
    public function statutory(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-masters');
        try {
            $held = app(\App\Services\Approvals\StaffChanges::class)->update($employee, ['pf_applicable' => $request->boolean('pf_applicable'),
                'esic_applicable' => $request->boolean('esic_applicable'), 'tds_applicable' => $request->boolean('tds_applicable')], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }
        if ($held) {
            return back()->with('status', "{$employee->name}: ".implode(', ', $held).' change sent to the owner for approval.');
        }

        return back()->with('status', "{$employee->name}: EPF ".($employee->pf_applicable ? 'on' : 'off').', ESIC '.($employee->esic_applicable ? 'on' : 'off')
            .', TDS '.($employee->tds_applicable ? 'on' : 'off').'.');
    }

    private function formData(): array
    {
        return [
            'agencies' => Agency::query()->withCount(['contracts'])->orderBy('name')->get()
                ->each(fn ($a) => $a->posts_count = Post::query()->whereHas('project.contract', fn ($q) => $q->where('agency_id', $a->id))->count()),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'nextCodes' => Agency::query()->get()->mapWithKeys(fn ($a) => [$a->id => Employee::nextCode($a)]),
            'posts' => Post::query()->with('project.contract')->withCount('activeEmployees')->get()
                ->sortBy(fn ($p) => $p->project->contract->bid_no.$p->project->name.$p->designation),
        ] + PostController::pickerData();   // own keys (agencies with post counts) win
    }

    /** Client must belong to the agency, and the post must be under that client (the post decides both if given). */
    private function checkPlacement(array &$data): void
    {
        $client = ! empty($data['client_id']) ? Client::query()->find($data['client_id']) : null;
        if ($client && (int) $client->agency_id !== (int) $data['agency_id']) {
            throw ValidationException::withMessages(['client_id' => 'This client belongs to a different agency. Choose a client of the selected agency.']);
        }
        if (! empty($data['post_id'])) {
            $contract = Post::query()->with('project.contract')->find($data['post_id'])->project->contract;
            if ((int) $contract->agency_id !== (int) $data['agency_id'] || ($client && (int) $contract->client_id !== (int) $client->id)) {
                throw ValidationException::withMessages(['post_id' => 'This post is under a different agency or client. Choose a post of the selected client.']);
            }
            $data['client_id'] = $contract->client_id;
        }
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'emp_code' => [$employee ? 'required' : 'nullable', 'string', 'max:20', Rule::unique('employees')->ignore($employee)],
            'agency_id' => ['required', new \App\Rules\ExistsInMyAgency(\App\Models\Agency::class)],
            'client_id' => ['nullable', new \App\Rules\ExistsInMyAgency(\App\Models\Client::class)],
            'post_id' => ['nullable', new \App\Rules\ExistsInMyAgency(\App\Models\Post::class)],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'whatsapp' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:1000'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'rate_override' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'regex:/^\d{6,20}$/'],
            'ifsc' => ['nullable', fn ($a, $v, $fail) => Ifsc::isValid($v) || $fail('An IFSC has 11 characters: 4 letters, a zero, then 6 letters or digits.')],
            'upi_id' => ['nullable', 'regex:/^[\w.\-]{2,}@[a-zA-Z]{2,}$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'],
            'aadhaar' => ['nullable', 'regex:/^\d{12}$/'],
            'uan' => ['nullable', 'digits:12'],
            'esic_ip' => ['nullable', 'digits_between:10,17'],
        ], [
            'mobile.regex' => 'Enter a 10-digit mobile number.', 'whatsapp.regex' => 'Enter a 10-digit WhatsApp number.',
            'account_number.regex' => 'Enter the account number using digits only.', 'pan.regex' => 'Enter a 10-character PAN, e.g. ABCDE1234F.',
            'aadhaar.regex' => 'Enter the 12-digit Aadhaar number.', 'upi_id.regex' => 'Enter a UPI ID like name@bank.',
            'agency_id.required' => 'Choose the agency that employs this person.',
        ]);
        $this->checkPlacement($data);
        $data['ifsc'] = Ifsc::normalize($data['ifsc'] ?? null);
        $data['pf_applicable'] = $request->boolean('pf_applicable');
        $data['esic_applicable'] = $request->boolean('esic_applicable');
        if ($request->has('statutory_form')) {
            $data['tds_applicable'] = $request->boolean('tds_applicable');   // the employee form always shows the TDS switch
        } elseif (! $employee) {
            $data['tds_applicable'] = true;                                   // new staff: TDS on unless switched off
        }

        // Sensitive numbers are never sent back to the browser; a blank field keeps what is stored.
        foreach (['account_number', 'aadhaar'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        return $data;
    }

    /** "Send again" for a new employee that was returned or changed after sending. */
    public function sendForApproval(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-masters');
        try {
            app(\App\Services\Approvals\StaffChanges::class)->sendNew($employee, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', 'Sent to the owner for approval.');
    }

    /** Super Admin: deletes an employee entered by mistake (no attendance, bills or letters) only when nothing depends on it (owner, 17 Nov 2026). */
    public function destroy(Employee $employee): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');
        if ($why = \App\Support\SafeDelete::employee($employee)) {
            return back()->withErrors(['delete' => $why]);
        }
        $label = $employee->emp_code.' '.$employee->name;
        foreach ($employee->documents as $doc) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($doc->path);
            $doc->delete();
        }
        $employee->delete();
        \App\Support\Activity\Activity::log('deleted', null, [], "Employee deleted: {$label}", section: 'Masters', label: $label);

        return redirect()->route('employees.index')->with('status', "Employee {$label} deleted.");
    }
}
