<x-layouts.app title="Project In-charge" crumb="Masters / <span class='text-danger'>Project In-charge</span>" :quiet-title="true">
  @php
    $q = request()->except('page');
    $tabUrl = fn ($t) => route('incharges.index', array_filter(['tab' => $t === 'all' ? null : $t] + $q, fn ($v) => $v !== null && $v !== ''));
    $projects = $incharges->flatMap->projects;
    $staffCount = $projects->sum(fn ($p) => $p->posts->sum(fn ($post) => $post->activeEmployees->count()));
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-person-badge"></i></div>
      <div class="me-auto"><h1>Project In-charge – {{ $workingAgency?->name ?? 'Both agencies' }}</h1><p>Each Project In-charge with their projects, the staff under them and their sub-bills.</p></div>
      <div class="wo-actions d-flex gap-2 flex-wrap">
        @can('manage-masters')<a class="btn wo-btn-primary" href="{{ route('incharges.create', array_filter(['agency' => $agencyId])) }}"><i class="bi bi-plus-lg me-1"></i>Add Project In-charge</a>@endcan
      </div>
    </div>

    <div class="wo-kpis">
      <div class="wo-kpi k-blue"><i class="bi bi-person-badge"></i><div><div class="l">Project In-charges</div><div class="v">{{ $incharges->count() }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-stack"></i><div><div class="l">Projects allotted</div><div class="v">{{ $projects->count() }}</div></div></div>
      <div class="wo-kpi k-green"><i class="bi bi-people"></i><div><div class="l">Staff under them</div><div class="v">{{ $staffCount }}</div></div></div>
      <div class="wo-kpi k-amber"><i class="bi bi-person-check"></i><div><div class="l">With PI portal login</div><div class="v">{{ $incharges->filter(fn ($i) => $i->user)->count() }}</div></div></div>
      <div class="wo-kpi k-red"><i class="bi bi-exclamation-circle"></i><div><div class="l">Projects without PI</div><div class="v">{{ $unallotted->count() }}</div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('incharges.index') }}" role="search">
      @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
      <nav class="wo-tabs" aria-label="Status">
        <a href="{{ $tabUrl('all') }}" @class(['on' => $tab === 'all'])>All ({{ $counts['all'] }})</a>
        <a href="{{ $tabUrl('active') }}" @class(['on' => $tab === 'active'])><span class="wo-dot" style="background:#16a34a"></span>Active ({{ $counts['active'] }})</a>
        <a href="{{ $tabUrl('inactive') }}" @class(['on' => $tab === 'inactive'])><span class="wo-dot" style="background:#94a3b8"></span>Inactive ({{ $counts['inactive'] }})</a>
      </nav>
      <label class="wo-pick"><span>Agency:</span>
        <select name="agency" class="form-select" onchange="this.form.submit()"><option value="">All</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId == $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Project In-charge:</span>
        <select name="pi" class="form-select" aria-label="Project In-charge" onchange="this.form.submit()"><option value="">All Project In-charges</option>@foreach($piOptions as $o)<option value="{{ $o->id }}" @selected($piId === $o->id)>{{ $o->name }}@unless($agencyId || \App\Support\Agency\AgencyContext::id()) ({{ $o->agency?->code }})@endunless</option>@endforeach</select></label>
      <div class="wo-search"><i class="bi bi-search"></i><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by name, mobile or e-mail" aria-label="Search"></div>
      @if(request()->query())<a class="btn btn-link px-1" href="{{ route('incharges.index') }}">Clear</a>@endif
      <button class="visually-hidden">Search</button>
    </form>

    @if($unallotted->isNotEmpty())
      <div class="alert alert-warning small"><b>{{ $unallotted->count() }} {{ \Illuminate\Support\Str::plural('project', $unallotted->count()) }} without a Project In-charge:</b>
        {{ $unallotted->map(fn ($p) => $p->name.' ('.$p->code.', '.$p->contract->agency->code.')')->implode(', ') }}</div>
    @endif

    @forelse($incharges as $i)
      @php($staff = $i->projects->sum(fn ($p) => $p->posts->sum(fn ($post) => $post->activeEmployees->count())))
      <article class="wo-card">
        <div class="wo-row">
          <button type="button" class="wo-toggle" data-bs-toggle="collapse" data-bs-target="#pi-{{ $i->id }}" aria-expanded="false" aria-controls="pi-{{ $i->id }}">
            <i class="bi bi-chevron-right chev"></i><i class="bi bi-person-circle doc"></i>
            <span class="min-w-0">
              <span class="d-flex align-items-center gap-2 flex-wrap"><span class="wo-no">{{ $i->name }}</span>
                @if($i->designation)<span class="wo-type off">{{ $i->designation }}</span>@endif
                @if($i->status !== 'active')<span class="badge text-bg-secondary">Inactive</span>@endif</span>
              <span class="wo-meta d-block">Agency: <b>{{ $i->agency->name }} ({{ $i->agency->code }})</b>@if($i->mobile) <span class="mx-1">|</span><i class="bi bi-telephone"></i> <b>{{ $i->mobile }}</b>@endif @if($i->email) <span class="mx-1">|</span><i class="bi bi-envelope"></i> <b>{{ $i->email }}</b>@endif @if($i->user) <span class="mx-1">|</span>PI login: <b>{{ $i->user->email }}</b>@endif</span>
            </span>
          </button>
          <div class="wo-right">
            <span @class(['wo-fill', 'full' => $i->projects->isNotEmpty(), 'none' => $i->projects->isEmpty()])><i class="bi bi-stack me-1"></i>{{ $i->projects->count() }} {{ \Illuminate\Support\Str::plural('project', $i->projects->count()) }}</span>
            <span class="wo-fill none"><i class="bi bi-people me-1"></i>{{ $staff }} staff</span>
            @can('manage-masters')<a class="btn btn-sm wo-btn-blue" href="{{ route('incharges.edit', $i) }}"><i class="bi bi-pencil me-1"></i>Edit / allot projects</a>@endcan
            @can('show-delete')<x-delete-button :action="route('incharges.destroy', $i)" :blocked="\App\Support\SafeDelete::incharge($i)" :confirm="'Delete Project In-charge '.$i->name.'? Only this name card is removed; no project is deleted.'" />@endcan
          </div>
        </div>
        <div class="collapse" id="pi-{{ $i->id }}">
          <div class="wo-body">
            @forelse($i->projects as $p)
              @php($rows = $p->posts->flatMap(fn ($post) => $post->activeEmployees->map(fn ($e) => [$e, $post])))
              <div class="d-flex flex-wrap gap-2 align-items-center mt-3 mb-1">
                <span class="wo-code">{{ $p->code }}</span><a class="fw-semibold text-decoration-none" href="{{ route('contracts.show', $p->gem_contract_id) }}">{{ $p->name }}</a>
                <span class="wo-meta">Work order <b>{{ $p->contract->contract_no }}</b> · {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('employee', $rows->count()) }}</span>
              </div>
              <div class="row g-3">
                <div class="col-lg-6"><div class="table-responsive"><table class="table table-sm align-middle wo-table mb-0">
                  <thead><tr><th style="width:22%">Emp ID</th><th>Employee</th><th>Post</th></tr></thead>
                  <tbody>
                  @forelse($rows as [$e, $post])
                    <tr><td class="mono">{{ $e->emp_code }}</td><td><a href="{{ route('staff.index', ['employee' => $e->id]) }}">{{ $e->name }}</a></td><td>{{ $post->designation }}</td></tr>
                  @empty
                    <tr><td colspan="3" class="text-secondary">No employees on this project.</td></tr>
                  @endforelse
                  </tbody></table></div></div>
                <div class="col-lg-6"><div class="table-responsive"><table class="table table-sm align-middle wo-table mb-0">
                  <thead><tr><th>Sub-bill</th><th>Month</th><th class="num">Amount</th><th>Status</th></tr></thead>
                  <tbody>
                  @forelse($p->invoices->take(6) as $b)
                    <tr><td><a class="mono" href="{{ route('invoices.show', $b) }}">{{ $b->invoice_no }}</a></td><td>{{ $b->month->format('M Y') }}</td><td class="num">{{ inr($b->rounded_total) }}</td><td><x-status :value="$b->status" /></td></tr>
                  @empty
                    <tr><td colspan="4" class="text-secondary">No sub-bills yet.</td></tr>
                  @endforelse
                  @if($p->invoices->count() > 6)<tr><td colspan="4"><a href="{{ route('invoices.index', ['project' => $p->id]) }}">All {{ $p->invoices->count() }} sub-bills</a></td></tr>@endif
                  </tbody></table></div></div>
              </div>
            @empty
              <div class="text-secondary small py-3">No project allotted yet.@can('manage-masters') Use <a href="{{ route('incharges.edit', $i) }}">Edit / allot projects</a>.@endcan</div>
            @endforelse
          </div>
        </div>
      </article>
    @empty
      <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No Project In-charges match these filters.@if(request()->query()) <a href="{{ route('incharges.index') }}">Clear filters</a>@endif</div>
    @endforelse
  </div>
</x-layouts.app>
