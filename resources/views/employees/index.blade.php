<x-layouts.app title="Employees" crumb="Masters / <span class='text-danger'>Employees</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('employees.create') }}"><i class="bi bi-plus-lg me-1"></i>Add employee</a>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 row g-2 mx-0">
    <div class="col-md-3"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, Emp ID or mobile" aria-label="Search"></div>
    <div class="col-md-2"><select class="form-select" name="agency" aria-label="Agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(request('agency') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><select class="form-select" name="client" aria-label="Client"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->label() }}</option>@endforeach</select></div>
    <div class="col-md-4"><select class="form-select" name="project" aria-label="Project"><option value="">All projects</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected(request('project') == $p->id)>{{ $p->label() }}</option>@endforeach</select></div>
    <div class="col-md-2"><select class="form-select" name="status" aria-label="Status"><option value="">Any status</option>@foreach(\App\Models\Employee::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-2"><select class="form-select" name="bank" aria-label="Bank details"><option value="">Any bank details</option><option value="problem" @selected(request('bank') === 'problem')>Missing account or IFSC</option></select></div>
    <div class="col-md-1 d-grid"><button class="btn btn-outline-secondary">Go</button></div>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Employee</th><th>Agency · client</th><th>Post</th><th>Project · PI</th><th class="num">Rate</th><th>Bank</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($employees as $e)
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="{{ route('employees.show', $e) }}">{{ $e->name }}</a><div class="mono text-secondary">{{ $e->emp_code }}</div></td>
        <td>@if($e->agency)<span class="badge text-bg-light border">{{ $e->agency->code }}</span>@endif {{ $e->client?->name ?? '—' }}</td>
        <td>{{ $e->post?->designation ?? '—' }}</td>
        <td>{{ $e->post?->project->label() ?? 'Not linked to a post' }}</td>
        <td class="num">{{ inr($e->rate()) }}</td>
        <td>@if($e->account_last4 && $e->ifsc)<span class="mono">{{ $e->maskedAccount() }}</span> <span class="badge text-bg-{{ $e->bank_verified_at ? 'success' : 'light border' }}">{{ $e->bank_verified_at ? 'Verified' : 'Not verified' }}</span>
            @else<span class="badge text-bg-danger">{{ $e->account_last4 ? 'IFSC missing' : 'No account' }}</span>@endif</td>
        <td><x-status :value="$e->status" /></td>
      </tr>
    @empty
      <tr><td colspan="7" class="text-center text-secondary py-4">No employees match these filters.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $employees->links() }}</div>
</x-layouts.app>
