<x-layouts.app title="Employees" crumb="Masters / <span class='text-danger'>Employees</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('employees.create') }}"><i class="bi bi-plus-lg me-1"></i>Add employee</a>@endcan</x-slot:actions>
  @php($agencyId = request()->integer('agency') ?: null) @php($clientId = request()->integer('client') ?: null)
  <form class="z-card p-3 mb-3 row g-2 mx-0 align-items-end">
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" class="form-select" name="agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" class="form-select" name="client"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-5"><label class="form-label small text-secondary mb-1" for="f-project">Project</label>
      <select id="f-project" class="form-select" name="project"><option value="">All projects</option>@foreach($projects as $p)<option value="{{ $p->id }}" data-agency="{{ $p->contract->agency_id }}" data-client="{{ $p->contract->client_id }}" @selected(request('project') == $p->id)>{{ $p->label() }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label small text-secondary mb-1" for="f-q">Search</label><input id="f-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, Emp ID or mobile"></div>
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-status">Status</label><select id="f-status" class="form-select" name="status"><option value="">Any status</option>@foreach(\App\Models\Employee::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-bank">Bank details</label><select id="f-bank" class="form-select" name="bank"><option value="">Any</option><option value="problem" @selected(request('bank') === 'problem')>Missing account or IFSC</option></select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-secondary flex-fill">Show</button>@if(request()->query())<a class="btn btn-link px-1" href="{{ route('employees.index') }}">Clear</a>@endif</div>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Employee</th><th>Agency</th><th>Client</th><th>Post</th><th>Project · PI</th><th class="num">Rate</th><th>Bank</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($employees as $e)
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="{{ route('employees.show', $e) }}">{{ $e->name }}</a><div class="mono text-secondary">{{ $e->emp_code }}</div></td>
        <td>@if($e->agency)<span class="badge text-bg-light border" title="{{ $e->agency->name }}">{{ $e->agency->code }}</span>@else<span class="badge text-bg-warning">None</span>@endif</td>
        <td>{{ $e->client?->name ?? 'Not assigned yet' }}</td>
        <td>{{ $e->post?->designation ?? '—' }}</td>
        <td>{{ $e->post?->project->label() ?? 'Not linked to a post' }}</td>
        <td class="num">{{ inr($e->rate()) }}</td>
        <td>@if($e->account_last4 && $e->ifsc)<span class="mono">{{ $e->maskedAccount() }}</span> <span class="badge text-bg-{{ $e->bank_verified_at ? 'success' : 'light border' }}">{{ $e->bank_verified_at ? 'Verified' : 'Not verified' }}</span>
            @else<span class="badge text-bg-danger">{{ $e->account_last4 ? 'IFSC missing' : 'No account' }}</span>@endif</td>
        <td><x-status :value="$e->status" /></td>
      </tr>
    @empty
      <tr><td colspan="8" class="text-center text-secondary py-4">No employees match these filters.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $employees->links() }}</div>
  <script>
    // Agency narrows clients and projects; client narrows projects.
    (function () {
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client'), project = document.getElementById('f-project');
      function narrow(select, test) {
        [...select.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !test(o); } });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
      }
      function update() {
        narrow(client, o => !agency.value || o.dataset.agency === agency.value);
        narrow(project, o => (!agency.value || o.dataset.agency === agency.value) && (!client.value || o.dataset.client === client.value));
      }
      agency.addEventListener('change', update); client.addEventListener('change', update); update();
    })();
  </script>
</x-layouts.app>
