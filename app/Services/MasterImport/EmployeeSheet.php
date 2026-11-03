<?php

namespace App\Services\MasterImport;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Post;
use App\Support\Ifsc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class EmployeeSheet extends MasterSheet
{
    private Collection $agencies;

    private Collection $clients;

    private Collection $posts;

    private Collection $byCode;

    private Collection $byNameMobile;

    public function type(): string
    {
        return 'employees';
    }

    public function title(): string
    {
        return 'Employees';
    }

    public function description(): string
    {
        return 'Staff with their placement (agency, client, contract, project, post), bank and KYC details. Upload agencies, clients, contracts, projects and posts first.';
    }

    public function columns(): array
    {
        return [
            new Column('emp_code', 'Employee code', help: 'Leave blank for new staff: the app gives the next code (EMP-1001 ...). Use the code to update someone already in the app.', example: 'EMP-1001', width: 13),
            new Column('name', 'Name', true, example: 'Amit Kumar', width: 24),
            new Column('agency_code', 'Agency code', true, help: 'Agency that employs this person', example: 'ZPS', width: 12),
            new Column('client_code', 'Client code', help: 'Client where the person works', example: 'ICMR', width: 12),
            new Column('contract_no', 'GeM contract no.', help: 'With Project code and Designation, places the person on a post', example: 'GEMC-511687731718030', width: 26, asText: true),
            new Column('project_code', 'Project code', example: 'MPH', width: 12),
            new Column('designation', 'Designation (post)', help: 'Must match a post of that project exactly', example: 'Academic Programme Manager', width: 28),
            new Column('father_name', "Father's name", width: 22),
            new Column('dob', 'Date of birth', type: 'date', help: 'DD-MM-YYYY', example: '15-08-1995', width: 13),
            new Column('gender', 'Gender', type: 'list', options: ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'], width: 10),
            new Column('mobile', 'Mobile', help: '10 digits', example: '9876543210', width: 13, asText: true),
            new Column('whatsapp', 'WhatsApp', help: '10 digits', width: 13, asText: true),
            new Column('email', 'E-mail', width: 24),
            new Column('address', 'Address', width: 34),
            new Column('qualification', 'Qualification', width: 18),
            new Column('joining_date', 'Joining date', type: 'date', help: 'DD-MM-YYYY', example: '01-10-2026', width: 13),
            new Column('exit_date', 'Exit date', type: 'date', help: 'DD-MM-YYYY, only if the person has left', width: 13),
            new Column('status', 'Status', type: 'list', options: Employee::STATUSES, help: 'Active, On notice or Inactive. Blank = Active.', example: 'Active', width: 11),
            new Column('rate_override', 'Salary override (₹)', type: 'number', help: 'Only if this person\'s rate differs from the post rate', width: 15),
            new Column('bank_name', 'Bank name', example: 'Canara Bank', width: 18),
            new Column('account_holder', 'Name as per bank', width: 22),
            new Column('account_number', 'Bank account no.', help: 'Digits only. Stored encrypted; never shown in downloads.', width: 20, secret: true, asText: true),
            new Column('ifsc', 'IFSC', example: 'CNRB0002957', width: 13, asText: true),
            new Column('upi_id', 'UPI ID', width: 18),
            new Column('pan', 'PAN', example: 'ABCDE1234F', width: 12, asText: true),
            new Column('aadhaar', 'Aadhaar', help: '12 digits. Stored encrypted; never shown in downloads.', width: 15, secret: true, asText: true),
            new Column('uan', 'UAN', help: '12 digits', width: 14, asText: true),
            new Column('esic_ip', 'ESIC IP no.', help: '10 to 17 digits', width: 18, asText: true),
            new Column('pf_applicable', 'EPF', type: 'bool', help: 'Yes = deduct EPF and bill employer EPF. Blank on new staff = No.', example: 'Yes', width: 7),
            new Column('esic_applicable', 'ESIC', type: 'bool', help: 'Yes = deduct ESIC and bill employer ESIC. Blank on new staff = No.', example: 'No', width: 7),
        ];
    }

    public function lists(): array
    {
        return [
            'agency_code' => Agency::query()->orderBy('code')->pluck('code')->all(),
            'client_code' => Client::query()->orderBy('short_code')->distinct()->pluck('short_code')->all(),
            'contract_no' => GemContract::query()->orderBy('contract_no')->pluck('contract_no')->all(),
            'designation' => Post::query()->orderBy('designation')->distinct()->pluck('designation')->all(),
            'gender' => ['Male', 'Female', 'Other'], 'status' => array_values(Employee::STATUSES),
            'pf_applicable' => ['Yes', 'No'], 'esic_applicable' => ['Yes', 'No'],
        ];
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->agencies = Agency::query()->get()->keyBy(fn ($a) => strtoupper($a->code));
        $this->clients = Client::query()->get()->keyBy(fn ($c) => $c->agency_id.'|'.strtoupper($c->short_code));
        $this->posts = Post::query()->with('project.contract')->get()
            ->keyBy(fn ($p) => strtoupper((string) $p->project?->contract?->contract_no).'|'.strtoupper((string) $p->project?->code).'|'.mb_strtolower(trim($p->designation)));
        $all = Employee::query()->get();
        $this->byCode = $all->keyBy(fn ($e) => strtoupper($e->emp_code));
        $this->byNameMobile = $all->filter(fn ($e) => $e->mobile)->keyBy(fn ($e) => mb_strtolower(trim($e->name)).'|'.$e->mobile);
    }

    protected function check(array $v, int $row): RowResult
    {
        foreach (['pan', 'ifsc'] as $k) {
            $v[$k] = $v[$k] ? strtoupper(str_replace(' ', '', $v[$k])) : null;
        }
        foreach (['mobile', 'whatsapp', 'account_number', 'aadhaar', 'uan', 'esic_ip'] as $k) {
            $v[$k] = $v[$k] !== null ? preg_replace('/[\s-]/', '', $v[$k]) : null;
        }
        $v['ifsc'] = Ifsc::normalize($v['ifsc']);
        $errors = $this->validate($v, [
            'emp_code' => ['nullable', 'max:20'], 'name' => ['nullable', 'max:255'],
            'mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/'], 'whatsapp' => ['nullable', 'regex:/^[6-9]\d{9}$/'], 'email' => ['nullable', 'email'],
            'dob' => ['nullable', 'date', 'before:today'], 'rate_override' => ['nullable', 'numeric', 'min:0'],
            'account_number' => ['nullable', 'regex:/^\d{6,20}$/'], 'upi_id' => ['nullable', 'regex:/^[\w.\-]{2,}@[a-zA-Z]{2,}$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'], 'aadhaar' => ['nullable', 'regex:/^\d{12}$/'],
            'uan' => ['nullable', 'digits:12'], 'esic_ip' => ['nullable', 'digits_between:10,17'],
        ], ['mobile.regex' => 'Mobile must be 10 digits starting with 6 to 9.', 'whatsapp.regex' => 'WhatsApp must be 10 digits.',
            'account_number.regex' => 'Bank account no. must be 6 to 20 digits.', 'pan.regex' => 'PAN must look like ABCDE1234F.',
            'aadhaar.regex' => 'Aadhaar must be 12 digits.', 'upi_id.regex' => 'UPI ID must look like name@bank.']);
        if ($v['ifsc'] && ! Ifsc::isValid($v['ifsc'])) {
            $errors[] = 'IFSC must have 11 characters: 4 letters, a zero, then 6 letters or digits.';
        }
        if ($v['joining_date'] && $v['exit_date'] && $v['exit_date'] < $v['joining_date']) {
            $errors[] = 'Exit date is before the joining date.';
        }

        // Placement: agency → client → post.
        $agencyCode = strtoupper((string) $v['agency_code']);
        $agency = $this->agencies[$agencyCode] ?? null;
        if ($agencyCode && ! $agency) {
            $errors[] = "Agency code {$agencyCode} is not in the app.";
        }
        $client = null;
        if ($agency && $v['client_code']) {
            $client = $this->clients[$agency->id.'|'.strtoupper($v['client_code'])] ?? null;
            $client || $errors[] = "Client code {$v['client_code']} is not a client of {$agencyCode}.";
        }
        $post = null;
        $placing = array_filter([$v['contract_no'], $v['project_code'], $v['designation']], fn ($x) => $x !== null);
        if ($placing && count($placing) < 3) {
            $errors[] = 'To place the person on a post, fill all three: GeM contract no., Project code and Designation.';
        } elseif ($placing) {
            $key = strtoupper($v['contract_no']).'|'.strtoupper($v['project_code']).'|'.mb_strtolower(trim($v['designation']));
            $post = $this->posts[$key] ?? null;
            if (! $post) {
                $errors[] = "No post \"{$v['designation']}\" under project {$v['project_code']} of contract {$v['contract_no']}. Upload the posts first.";
            } elseif ($agency && (int) $post->project->contract->agency_id !== (int) $agency->id) {
                $errors[] = 'That post is under a different agency\'s contract.';
            } elseif ($client && (int) $post->project->contract->client_id !== (int) $client->id) {
                $errors[] = 'That post is under a different client.';
            }
        }

        $code = $v['emp_code'] ? strtoupper($v['emp_code']) : null;
        $existing = $code ? ($this->byCode[$code] ?? null)
            : ($v['name'] && $v['mobile'] ? ($this->byNameMobile[mb_strtolower(trim($v['name'])).'|'.$v['mobile']] ?? null) : null);

        if ($code && ! $existing && \App\Support\Agency\AgencyContext::unscoped(fn () => Employee::query()->where('emp_code', $code)->exists())) {
            $errors[] = "Employee code {$code} is already used by another agency's employee. Leave the code blank for a new person.";
        }
        $data = [
            'emp_code' => $code, 'name' => $v['name'], 'agency_id' => $agency?->id,
            'client_id' => $post ? $post->project->contract->client_id : $client?->id, 'post_id' => $post?->id,
            'father_name' => $v['father_name'], 'dob' => $v['dob'], 'gender' => $v['gender'], 'mobile' => $v['mobile'], 'whatsapp' => $v['whatsapp'],
            'email' => $v['email'], 'address' => $v['address'], 'qualification' => $v['qualification'], 'joining_date' => $v['joining_date'],
            'exit_date' => $v['exit_date'], 'status' => $v['status'], 'rate_override' => $v['rate_override'], 'bank_name' => $v['bank_name'],
            'account_holder' => $v['account_holder'], 'account_number' => $v['account_number'], 'ifsc' => $v['ifsc'], 'upi_id' => $v['upi_id'],
            'pan' => $v['pan'], 'aadhaar' => $v['aadhaar'], 'uan' => $v['uan'], 'esic_ip' => $v['esic_ip'],
            'pf_applicable' => $v['pf_applicable'], 'esic_applicable' => $v['esic_applicable'],
        ];
        $key = $existing ? 'id:'.$existing->id : ($code ? "code:{$code}" : "row:{$row}");
        $label = trim(($existing?->emp_code ?? $code ?? 'New').' · '.($v['name'] ?? '').($post ? " · {$post->designation}" : ''));

        return $this->result($row, $key, $label, $data, $existing, $errors);
    }

    protected function newModel(): Model
    {
        return new Employee(['status' => 'active', 'pf_applicable' => false, 'esic_applicable' => false]);
    }

    public function save(RowResult $r): Model
    {
        if (! $r->model && empty($r->data['emp_code'])) {
            $r->data['emp_code'] = Employee::nextCode();
        }

        return parent::save($r);
    }

    public function export(): iterable
    {
        foreach (Employee::query()->with('agency', 'client', 'post.project.contract')->orderBy('emp_code')->get() as $e) {
            yield ['emp_code' => $e->emp_code, 'name' => $e->name, 'agency_code' => $e->agency?->code, 'client_code' => $e->client?->short_code,
                'contract_no' => $e->post?->project?->contract?->contract_no, 'project_code' => $e->post?->project?->code, 'designation' => $e->post?->designation,
                'father_name' => $e->father_name, 'dob' => self::dmy($e->dob), 'gender' => $e->gender ? ucfirst($e->gender) : null, 'mobile' => $e->mobile,
                'whatsapp' => $e->whatsapp, 'email' => $e->email, 'address' => $e->address, 'qualification' => $e->qualification,
                'joining_date' => self::dmy($e->joining_date), 'exit_date' => self::dmy($e->exit_date), 'status' => Employee::STATUSES[$e->status] ?? $e->status,
                'rate_override' => $e->rate_override !== null ? (float) $e->rate_override : null, 'bank_name' => $e->bank_name,
                'account_holder' => $e->account_holder, 'ifsc' => $e->ifsc, 'upi_id' => $e->upi_id, 'pan' => $e->pan, 'uan' => $e->uan,
                'esic_ip' => $e->esic_ip, 'pf_applicable' => self::yesNo($e->pf_applicable), 'esic_applicable' => self::yesNo($e->esic_applicable)];
        }
    }
}
