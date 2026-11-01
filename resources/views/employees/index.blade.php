<x-layouts.app title="Employees" crumb="Masters / <span class='text-danger'>Employees</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('employees.create') }}"><i class="bi bi-plus-lg me-1"></i>Add employee</a>@endcan</x-slot:actions>
  @php($agencyId = request()->integer('agency') ?: null) @php($clientId = request()->integer('client') ?: null)
  <form class="z-card p-3 mb-3 row g-2 mx-0 align-items-end">
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" class="form-select" name="agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" class="form-select" name="client"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-contract">GeM contract</label>
      <select id="f-contract" class="form-select" name="contract"><option value="">All contracts</option>@foreach($contracts as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected(request('contract') == $k->id)>{{ $k->bid_no }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-project">Project</label>
      <select id="f-project" class="form-select" name="project"><option value="">All projects</option>@foreach($projects as $p)<option value="{{ $p->id }}" data-agency="{{ $p->contract->agency_id }}" data-client="{{ $p->contract->client_id }}" data-contract="{{ $p->gem_contract_id }}" @selected(request('project') == $p->id)>{{ $p->label() }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label small text-secondary mb-1" for="f-q">Search</label><input id="f-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, Emp ID or mobile"></div>
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-status">Status</label><select id="f-status" class="form-select" name="status"><option value="">Any status</option>@foreach(\App\Models\Employee::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-bank">Bank details</label><select id="f-bank" class="form-select" name="bank"><option value="">Any</option><option value="problem" @selected(request('bank') === 'problem')>Missing account or IFSC</option></select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-secondary flex-fill">Show</button>@if(request()->query())<a class="btn btn-link px-1" href="{{ route('employees.index') }}">Clear</a>@endif</div>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Employee</th><th>Agency</th><th>Client</th><th>GeM contract</th><th>Project</th><th>Post</th><th class="num">Rate</th><th>Bank</th><th>Status</th></tr></thead>
    <tbody>
    @php($block = false)
    @forelse ($employees as $e)
      @if($block === false || $block !== $e->agency_id)
        @php($starts = $block === false && $carriedAgency !== false && $carriedAgency === $e->agency_id)
        @php($block = $e->agency_id)
        <tr class="table-light"><td colspan="9" class="py-2">
          @if($e->agency)
            <b>{{ $e->agency->name }}</b> <span class="badge text-bg-dark">{{ $e->agency->code }}</span>
          @else
            <b>No agency set</b>
          @endif
          <span class="small text-secondary ms-2">{{ $blockCounts[$e->agency_id ?? ''] ?? 0 }} {{ \Illuminate\Support\Str::plural('employee', $blockCounts[$e->agency_id ?? ''] ?? 0) }}{{ $starts ? ' · continued from the previous page' : '' }}</span>
        </td></tr>
      @endif
      <tr>
        <td><a class="fw-semibold text-decoration-none" href="{{ route('employees.show', $e) }}">{{ $e->name }}</a><div class="mono text-secondary">{{ $e->emp_code }}</div></td>
        <td>@if($e->agency)<span class="badge text-bg-light border" title="{{ $e->agency->name }}">{{ $e->agency->code }}</span>@else<span class="badge text-bg-warning">None</span>@endif</td>
        <td>{{ $e->client?->name ?? 'Not assigned yet' }}</td>
        @php($proj = $e->post?->project)
        <td>@if($proj)<a class="mono text-decoration-none" href="{{ route('contracts.show', $proj->contract) }}">{{ $proj->contract->bid_no }}</a>@else<span class="text-secondary small">—</span>@endif</td>
        <td>
          @if($proj)
            <span class="fw-semibold">{{ $proj->name }}</span>
            @if($proj->pi_name) <div class="small text-secondary">PI: {{ $proj->pi_name }}</div> @endif
          @else
            <span class="text-secondary small">Not placed on a post</span>
          @endif
        </td>
        <td>{{ $e->post?->designation ?? '—' }}</td>
        <td class="num">{{ inr($e->rate()) }}</td>
        <td>@if($e->account_last4 && $e->ifsc)<span class="mono">{{ $e->maskedAccount() }}</span> <span class="badge text-bg-{{ $e->bank_verified_at ? 'success' : 'light border' }}">{{ $e->bank_verified_at ? 'Verified' : 'Not verified' }}</span>
            @else<span class="badge text-bg-danger">{{ $e->account_last4 ? 'IFSC missing' : 'No account' }}</span>@endif</td>
        <td><x-status :value="$e->status" /></td>
      </tr>
    @empty
      <tr><td colspan="9" class="text-center text-secondary py-4">No employees match these filters.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $employees->links() }}</div>
  <script>
    // Agency narrows clients, contracts and projects; client narrows contracts and projects; contract narrows projects.
    (function () {
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client'), contract = document.getElementById('f-contract'), project = document.getElementById('f-project');
      function narrow(select, test) {
        [...select.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !test(o); } });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
      }
      function update() {
        narrow(client, o => !agency.value || o.dataset.agency === agency.value);
        const fits = o => (!agency.value || o.dataset.agency === agency.value) && (!client.value || o.dataset.client === client.value);
        narrow(contract, fits);
        narrow(project, o => fits(o) && (!contract.value || o.dataset.contract === contract.value));
      }
      [agency, client, contract].forEach(el => el.addEventListener('change', update)); update();
    })();
  </script>
</x-layouts.app>
