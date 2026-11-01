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
        $employees = Employee::query()->with('post.project.contract', 'agency', 'client')
            ->when($request->integer('agency'), fn ($q, $id) => $q->where('agency_id', $id))
            ->when($request->integer('client'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('emp_code', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")))
            ->when($request->integer('contract'), fn ($q, $id) => $q->whereHas('post.project', fn ($p) => $p->where('gem_contract_id', $id)))
            ->when($request->integer('project'), fn ($q, $id) => $q->whereHas('post', fn ($p) => $p->where('project_id', $id)))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('bank')->value() === 'problem', fn ($q) => $q->where(fn ($w) => $w->whereNull('ifsc')->orWhereNull('account_last4')))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('employees.index', ['employees' => $employees, 'projects' => Project::query()->with('contract')->orderBy('name')->get(),
            'contracts' => GemContract::query()->orderBy('bid_no')->get(['id', 'agency_id', 'client_id', 'bid_no']),
            'agencies' => Agency::query()->orderBy('name')->get(), 'clients' => Client::query()->with('agency')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('manage-masters');

        return view('employees.form', ['employee' => new Employee(['status' => 'active', 'emp_code' => Employee::nextCode()])] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $employee = Employee::query()->create($this->validated($request));

        return redirect()->route('employees.show', $employee)->with('status', 'Employee added.');
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
        $employee->update($this->validated($request, $employee));

        return redirect()->route('employees.show', $employee)->with('status', 'Employee saved.');
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
        ])->setPaper('a4')->stream("appointment-{$employee->emp_code}.pdf");
    }

    private function formData(): array
    {
        return [
            'agencies' => Agency::query()->withCount(['contracts'])->orderBy('name')->get()
                ->each(fn ($a) => $a->posts_count = Post::query()->whereHas('project.contract', fn ($q) => $q->where('agency_id', $a->id))->count()),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
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
            'emp_code' => ['required', 'string', 'max:20', Rule::unique('employees')->ignore($employee)],
            'agency_id' => ['required', 'exists:agencies,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'post_id' => ['nullable', 'exists:posts,id'],
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
            'rate_override' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
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

        // Sensitive numbers are never sent back to the browser; a blank field keeps what is stored.
        foreach (['account_number', 'aadhaar'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        return $data;
    }
}
