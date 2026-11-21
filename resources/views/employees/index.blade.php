<x-layouts.app title="Employees" crumb="Masters / <span class='text-danger'>Employees</span>" :quiet-title="true">
  @php
    $agencyId = request()->integer('agency') ?: null; $clientId = request()->integer('client') ?: null;
    $status = request('status');
    $q = request()->except('page');
    $tabUrl = fn ($t) => route('employees.index', array_filter(['status' => $t] + $q, fn ($v) => $v !== null && $v !== ''));
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-people"></i></div>
      <div class="me-auto"><h1>Employees – {{ $workingAgency?->name ?? 'Both agencies' }}</h1><p>Every employee with their post, project, work order, EPF / ESIC / TDS switches and bank details.</p></div>
      <div class="wo-actions d-flex gap-2 flex-wrap">
        @can('manage-masters')<a class="btn wo-btn-primary" href="{{ route('employees.create') }}"><i class="bi bi-plus-lg me-1"></i>Add employee</a>@endcan
      </div>
    </div>

    <div class="wo-kpis">
      <div class="wo-kpi k-blue"><i class="bi bi-people"></i><div><div class="l">Total employees</div><div class="v">{{ $figures['total'] }}</div></div></div>
      <div class="wo-kpi k-green"><i class="bi bi-person-check"></i><div><div class="l">Active</div><div class="v">{{ $figures['active'] }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-diagram-3"></i><div><div class="l">Placed on posts</div><div class="v">{{ $figures['placed'] }}</div></div></div>
      <div class="wo-kpi k-amber"><i class="bi bi-hourglass-split"></i><div><div class="l">On notice</div><div class="v">{{ $figures['notice'] }}</div></div></div>
      <div class="wo-kpi k-red"><i class="bi bi-bank"></i><div><div class="l">Bank details missing</div><div class="v">{{ $figures['bank'] }}</div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('employees.index') }}" role="search">
      @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
      <nav class="wo-tabs" aria-label="Status">
        <a href="{{ $tabUrl(null) }}" @class(['on' => ! $status])>All ({{ $figures['total'] }})</a>
        @foreach(\App\Models\Employee::STATUSES as $k => $v)
          <a href="{{ $tabUrl($k) }}" @class(['on' => $status === $k])><span class="wo-dot" style="background:{{ ['active' => '#16a34a', 'notice' => '#f59e0b', 'inactive' => '#94a3b8'][$k] ?? '#94a3b8' }}"></span>{{ $v }} ({{ $statusCounts[$k] ?? 0 }})</a>
        @endforeach
      </nav>
      <label class="wo-pick"><span>Agency:</span>
        <select id="f-agency" class="form-select" name="agency"><option value="">All</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Client:</span>
        <select id="f-client" class="form-select" name="client"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Work order:</span>
        <select id="f-contract" class="form-select" name="contract"><option value="">All contracts</option>@foreach($contracts as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected(request('contract') == $k->id)>{{ $k->pickLabel() }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Project:</span>
        <select id="f-project" class="form-select" name="project"><option value="">All projects</option>@foreach($projects as $p)<option value="{{ $p->id }}" data-agency="{{ $p->contract->agency_id }}" data-client="{{ $p->contract->client_id }}" data-contract="{{ $p->gem_contract_id }}" @selected(request('project') == $p->id)>{{ $p->label() }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Bank details:</span>
        <select id="f-bank" class="form-select" name="bank"><option value="">Any</option><option value="problem" @selected(request('bank') === 'problem')>Missing account or IFSC</option></select></label>
      <div class="wo-search"><i class="bi bi-search"></i><input id="f-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by name, Emp ID or mobile" aria-label="Search"></div>
      <button class="btn wo-btn-primary">Show</button>
      @if(request()->query())<a class="btn btn-link px-1" href="{{ route('employees.index') }}">Clear</a>@endif
    </form>

    <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 wo-list-table">
      <thead><tr><th>Employee</th><th>Post · project</th><th>Work order</th><th class="num">Rate</th><th>EPF / ESIC / TDS</th><th>Bank</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      @php($group = false)
      @forelse ($employees as $e)
        @if($group === false || $group !== $e->agency_id.'-'.$e->client_id)
          @php($starts = $group === false && $carriedAgency !== false && $carriedAgency === $e->agency_id)
          @php($group = $e->agency_id.'-'.$e->client_id)
          @php($cc = $clientCounts[$group] ?? null)
          <tr class="wo-group"><td colspan="8">
            <i class="bi bi-buildings me-1"></i>@if($e->agency)<b>{{ $e->agency->name }}</b> <span class="wo-type off">{{ $e->agency->code }}</span>@else<b>No agency set</b>@endif
            <span class="mx-2 text-secondary">›</span><i class="bi bi-bank me-1 text-secondary"></i><b>{{ $e->client?->name ?? 'Not assigned to a client yet' }}</b>
            @if($cc)<span class="wo-meta ms-2">{{ $cc->total }} {{ \Illuminate\Support\Str::plural('employee', $cc->total) }} · {{ $cc->placed }} placed on posts{{ $starts ? ' · continued from the previous page' : '' }}</span>@endif
          </td></tr>
        @endif
        @php($proj = $e->post?->project)
        <tr>
          <td class="text-nowrap"><a class="fw-semibold text-decoration-none" href="{{ route('employees.show', $e) }}">{{ $e->name }}</a><div class="mono small text-secondary">{{ $e->emp_code }}</div></td>
          <td>@if($proj)<div>{{ $e->post->designation }}</div><div class="small text-secondary" title="{{ $proj->name }}"><span class="wo-code">{{ $proj->code }}</span>@if($proj->pi_name) PI: {{ $proj->pi_name }}@endif</div>@else<span class="text-secondary small">Not placed on a post</span>@endif</td>
          <td>@if($proj)<a class="mono small text-decoration-none text-nowrap" href="{{ route('contracts.show', $proj->contract) }}">{{ $proj->contract->contract_no }}</a>@else<span class="text-secondary small">—</span>@endif</td>
          <td class="num">{{ inr($e->rate()) }}</td>
          <td class="text-nowrap">
            @can('manage-masters')
              <form method="post" action="{{ route('employees.statutory', $e) }}" class="d-inline-flex gap-2">@csrf @method('PATCH')
                @foreach(['pf_applicable' => 'EPF', 'esic_applicable' => 'ESIC', 'tds_applicable' => 'TDS'] as $field => $label)
                  <div class="form-check form-switch mb-0" title="{{ $label }} {{ $e->{$field} ? 'on' : 'off' }}: click to switch {{ $e->{$field} ? 'off' : 'on' }}">
                    <input class="form-check-input" type="checkbox" role="switch" id="s-{{ $field }}-{{ $e->id }}" name="{{ $field }}" value="1" @checked($e->{$field}) onchange="this.form.submit()">
                    <label class="form-check-label small" for="s-{{ $field }}-{{ $e->id }}">{{ $label }}</label>
                  </div>
                @endforeach
                <noscript><button class="btn btn-sm btn-outline-secondary">Save</button></noscript>
              </form>
            @else
              <span class="badge text-bg-{{ $e->pf_applicable ? 'success' : 'light border' }}">EPF {{ $e->pf_applicable ? 'on' : 'off' }}</span>
              <span class="badge text-bg-{{ $e->esic_applicable ? 'success' : 'light border' }}">ESIC {{ $e->esic_applicable ? 'on' : 'off' }}</span>
              <span class="badge text-bg-{{ $e->tds_applicable ? 'success' : 'light border' }}">TDS {{ $e->tds_applicable ? 'on' : 'off' }}</span>
            @endcan
          </td>
          <td>@if($e->account_last4 && $e->ifsc)<span class="mono small">{{ $e->maskedAccount() }}</span> <span class="badge text-bg-{{ $e->bank_verified_at ? 'success' : 'light border' }}">{{ $e->bank_verified_at ? 'Verified' : 'Not verified' }}</span>
              @else<span class="badge text-bg-danger">{{ $e->account_last4 ? 'IFSC missing' : 'No account' }}</span>@endif</td>
          <td><x-status :value="$e->status" /></td>
          <td class="text-end text-nowrap">@can('manage-masters')<a class="btn btn-sm wo-btn-blue" href="{{ route('employees.edit', $e) }}"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
            @can('show-delete')<x-delete-button :action="route('employees.destroy', $e)" :blocked="\App\Support\SafeDelete::employee($e)" :confirm="'Delete employee '.$e->name.' ('.$e->emp_code.')? Only possible while they are on no attendance, bill or payout.'" />@endcan</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">No employees match these filters.</td></tr>
      @endforelse
      </tbody></table></div></div>
  </div>
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
