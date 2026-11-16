<x-layouts.app title="Employee Management" crumb="Masters / <span class='text-danger'>Employee Management</span>">
  <form method="get" class="z-card p-3 mb-3 d-flex gap-2 flex-wrap align-items-end">
    <div class="flex-grow-1" style="min-width:280px"><label class="form-label small text-secondary mb-1" for="emp">Employee</label>
      <select id="emp" name="employee" class="form-select" onchange="this.form.submit()">
        <option value="">Choose an employee to see all their details</option>
        @foreach($employees->groupBy(fn ($e) => $e->agency?->name ?? 'No agency') as $agencyName => $list)
          <optgroup label="{{ $agencyName }}">@foreach($list as $e)<option value="{{ $e->id }}" @selected($employee?->id === $e->id)>{{ $e->emp_code }} · {{ $e->name }}{{ $e->post ? ' · '.$e->post->designation.' ('.$e->post->project->code.')' : '' }}</option>@endforeach</optgroup>
        @endforeach
      </select></div>
    <button class="btn btn-outline-secondary">Show</button>
  </form>

  @if($employee)
    <div class="row g-3 mb-3">
      <div class="col-lg-6"><div class="z-card p-3 h-100">
        <div class="d-flex align-items-start justify-content-between">
          <div><div class="fs-5 fw-bold">{{ $employee->name }}</div><div class="mono text-secondary">{{ $employee->emp_code }}</div></div>
          <div class="d-flex gap-2"><x-status :value="$employee->status" /><a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.show', $employee) }}">Open record</a></div>
        </div>
        <dl class="row small mb-0 mt-2">
          <dt class="col-sm-4 text-secondary fw-normal">Agency</dt><dd class="col-sm-8">{{ $employee->agency?->name ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Client</dt><dd class="col-sm-8">{{ $employee->client?->name ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Post · project</dt><dd class="col-sm-8">{{ $employee->post ? $employee->post->designation.' · '.$employee->post->project->name.' ('.$employee->post->project->code.')' : '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Work order</dt><dd class="col-sm-8 mono">{{ $employee->post?->project->contract->contract_no ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Monthly rate</dt><dd class="col-sm-8">{{ inr($employee->rate()) }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Joined · left</dt><dd class="col-sm-8">{{ $employee->joining_date?->format('d M Y') ?? '—' }} · {{ $employee->exit_date?->format('d M Y') ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Mobile · e-mail</dt><dd class="col-sm-8">{{ $employee->mobile ?? '—' }} · {{ $employee->email ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Father's name</dt><dd class="col-sm-8">{{ $employee->father_name ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Address</dt><dd class="col-sm-8">{{ $employee->address ?? '—' }}</dd>
        </dl>
      </div></div>
      <div class="col-lg-6"><div class="z-card p-3 h-100">
        <h2 class="h6 fw-bold">KYC, bank and statutory</h2>
        <dl class="row small mb-2">
          <dt class="col-sm-4 text-secondary fw-normal">Bank</dt><dd class="col-sm-8">{{ $employee->bank_name ?? '—' }} · <span class="mono">{{ $showBank ? ($employee->account_number ?? '—') : ($employee->maskedAccount() ?? '—') }}</span> · <span class="mono">{{ $employee->ifsc ?? '—' }}</span>
            @if(! $employee->ifsc || ! $employee->account_last4)<span class="badge text-bg-danger">Incomplete</span>@endif</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Name as per bank</dt><dd class="col-sm-8">{{ $employee->account_holder ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">PAN</dt><dd class="col-sm-8 mono">{{ $employee->pan ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Aadhaar</dt><dd class="col-sm-8 mono">{{ $employee->aadhaar_last4 ? 'XXXX XXXX '.$employee->aadhaar_last4 : '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">UAN · ESIC IP</dt><dd class="col-sm-8 mono">{{ $employee->uan ?? '—' }} · {{ $employee->esic_ip ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">EPF · ESIC</dt><dd class="col-sm-8">{{ $employee->pf_applicable ? 'EPF on' : 'EPF off' }} · {{ $employee->esic_applicable ? 'ESIC on' : 'ESIC off' }}</dd>
        </dl>
        <div class="small"><b>Documents:</b>
          @forelse($employee->documents as $d)<span class="badge text-bg-light border me-1">{{ \App\Models\Employee::DOCUMENT_TYPES[$d->type] ?? $d->type }}</span>@empty<span class="text-secondary">none uploaded</span>@endforelse</div>
        <div class="small mt-1"><b>Letters:</b> @forelse($letters as $l)<span class="mono me-2">{{ $l->number }}</span>@empty<span class="text-secondary">none</span>@endforelse</div>
      </div></div>
    </div>

    <div class="z-card mb-3">
      <div class="z-card-h"><h2>Month-wise attendance and payments</h2><span class="small text-secondary">Net pay = earned − employee EPF 12% − ESIC 0.75% (when switched on), as on the salary payout sheet and bank letter</span></div>
      <div class="table-responsive"><table class="table">
        <thead><tr><th>Month</th><th>Project</th><th>Attendance</th><th class="num">Paid days</th><th class="num">Earned</th><th class="num">EPF</th><th class="num">ESIC</th><th class="num">Net paid to bank</th><th>Paid to</th><th>Sub-bill</th></tr></thead>
        <tbody>
        @forelse($months as $m)
          @php($p = $m['pay'])
          <tr>
            <td class="text-nowrap fw-semibold">{{ $m['sheet']->month->format('M Y') }}</td>
            <td><span class="mono">{{ $m['sheet']->project->code }}</span></td>
            <td class="small">@foreach($m['codes'] as $code => $n)<span class="badge text-bg-light border me-1">{{ $code }} {{ $n }}</span>@endforeach <x-status :value="$m['sheet']->status" /></td>
            <td class="num">{{ rtrim(rtrim((string) $m['entry']->paid_days, '0'), '.') }}</td>
            <td class="num">{{ $p ? inr($p['earned'] / 100, true) : '' }}</td>
            <td class="num">{{ $p ? inr($p['epf'] / 100, true) : '' }}</td>
            <td class="num">{{ $p ? inr($p['esic'] / 100, true) : '' }}</td>
            <td class="num fw-semibold">{{ $p ? inr($p['net'] / 100, true) : '' }}</td>
            <td class="small">@if($p){{ $employee->bank_name ?? '—' }} <span class="mono">{{ $employee->maskedAccount() }}</span>@if($showBank && $m['sheet']->isLocked()) · <a href="{{ route('attendance.bank-letter', $m['sheet']) }}" target="_blank">bank letter</a>@endif @else<span class="text-secondary">Month not locked</span>@endif</td>
            <td class="small">@if($m['bill'])<a class="mono" href="{{ route('invoices.show', $m['bill']->invoice_id) }}">{{ $m['bill']->invoice->invoice_no }}</a> · {{ inr($m['bill']->amount) }}@else — @endif</td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-secondary py-4">No attendance for this employee yet.</td></tr>
        @endforelse
        </tbody>
        @if($months->isNotEmpty())
        <tfoot><tr class="fw-semibold"><td colspan="4">Total (locked months)</td>
          <td class="num">{{ inr($months->sum(fn ($m) => $m['pay']['earned'] ?? 0) / 100, true) }}</td><td class="num">{{ inr($months->sum(fn ($m) => $m['pay']['epf'] ?? 0) / 100, true) }}</td>
          <td class="num">{{ inr($months->sum(fn ($m) => $m['pay']['esic'] ?? 0) / 100, true) }}</td><td class="num">{{ inr($months->sum(fn ($m) => $m['pay']['net'] ?? 0) / 100, true) }}</td><td colspan="2"></td></tr></tfoot>
        @endif
      </table></div>
    </div>
  @endif

  <div class="z-card">
    <div class="z-card-h"><h2>All employees</h2><span class="small text-secondary">{{ $employees->count() }} in total</span></div>
    <div class="table-responsive"><table class="table table-hover">
      <thead><tr><th>Emp ID</th><th>Name</th><th>Agency</th><th>Post · project</th><th>Mobile</th><th>Bank</th><th>EPF / ESIC</th><th>Status</th></tr></thead>
      <tbody>
      @foreach($employees as $e)
        <tr @class(['table-active' => $employee?->id === $e->id])>
          <td class="mono"><a href="{{ route('staff.index', ['employee' => $e->id]) }}">{{ $e->emp_code }}</a></td>
          <td><a href="{{ route('staff.index', ['employee' => $e->id]) }}">{{ $e->name }}</a></td>
          <td><span class="badge text-bg-light border">{{ $e->agency?->code }}</span></td>
          <td class="small">{{ $e->post ? $e->post->designation.' · '.$e->post->project->code : '—' }}</td>
          <td class="small">{{ $e->mobile ?? '—' }}</td>
          <td class="small">{{ $e->bank_name ?? '—' }} <span class="mono">{{ $e->maskedAccount() }}</span></td>
          <td class="small">{{ $e->pf_applicable ? 'EPF' : '—' }} / {{ $e->esic_applicable ? 'ESIC' : '—' }}</td>
          <td><x-status :value="$e->status" /></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  </div>
</x-layouts.app>
