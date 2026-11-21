<x-layouts.app title="Work Orders" crumb="Masters / <span class='text-danger'>Work Orders</span>" :quiet-title="true">
  <x-slot:head>@include('contracts._index-style')</x-slot:head>
  @php
    $q = request()->except('page');
    $tabUrl = fn ($t) => route('contracts.index', array_filter(['tab' => $t === 'all' ? null : $t] + $q, fn ($v) => $v !== null && $v !== ''));
    $agencyName = $workingAgency?->name ?? 'Both agencies';
    $pct = fn ($n, $d) => $d ? rtrim(rtrim(number_format($n * 100 / $d, 1), '0'), '.').'%' : '0%';
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-file-earmark-text"></i></div>
      <div class="me-auto"><h1>Contracts – {{ $agencyName }}</h1><p>View and manage work orders, their projects and how many posts are filled.</p></div>
      <div class="wo-actions d-flex gap-2 flex-wrap">
        @can('import')<a class="btn wo-btn-line" href="{{ route('import.create') }}"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import</a>@endcan
        @can('manage-masters')<a class="btn wo-btn-primary" href="{{ route('contracts.create') }}"><i class="bi bi-plus-lg me-1"></i>Add Contract</a>@endcan
        <div class="dropdown">
          <button class="btn wo-btn-line dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Export</button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('contracts.export', $q) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (this list)</a></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="wo-kpis">
      <div class="wo-kpi k-blue"><i class="bi bi-folder2"></i><div><div class="l">Total Contracts</div><div class="v">{{ $kpi['contracts'] }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-stack"></i><div><div class="l">Total Projects</div><div class="v">{{ $kpi['projects'] }}</div></div></div>
      <div class="wo-kpi k-amber"><i class="bi bi-clipboard-check"></i><div><div class="l">Total Posts</div><div class="v">{{ $kpi['posts'] }}</div></div></div>
      <div class="wo-kpi k-green"><i class="bi bi-check2-circle"></i><div><div class="l">Filled Posts</div><div class="v">{{ $kpi['filled'] }} <small>({{ $pct($kpi['filled'], $kpi['posts']) }})</small></div></div></div>
      <div class="wo-kpi k-red"><i class="bi bi-people"></i><div><div class="l">Open Posts</div><div class="v">{{ $kpi['open'] }} <small>({{ $pct($kpi['open'], $kpi['posts']) }})</small></div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('contracts.index') }}" role="search">
      @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
      <nav class="wo-tabs" aria-label="Contract status">
        <a href="{{ $tabUrl('all') }}" @class(['on' => $tab === 'all']) @if($tab === 'all') aria-current="page" @endif>All Contracts ({{ $counts['all'] }})</a>
        <a href="{{ $tabUrl('active') }}" @class(['on' => $tab === 'active'])><span class="wo-dot" style="background:#16a34a"></span>Active ({{ $counts['active'] }})</a>
        <a href="{{ $tabUrl('completed') }}" @class(['on' => $tab === 'completed'])><span class="wo-dot" style="background:#94a3b8"></span>Completed ({{ $counts['completed'] }})</a>
        <a href="{{ $tabUrl('draft') }}" @class(['on' => $tab === 'draft']) title="Work orders with no projects yet"><span class="wo-dot" style="background:#cbd5e1"></span>Draft ({{ $counts['draft'] }})</a>
      </nav>
      <label class="wo-pick"><span>Agency:</span>
        <select name="agency" class="form-select" onchange="this.form.submit()"><option value="">All</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Status:</span>
        <select name="staffing" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="vacant" @selected($staffing === 'vacant')>Has open posts</option>
          <option value="filled" @selected($staffing === 'filled')>Fully filled</option>
          <option value="ending" @selected($staffing === 'ending')>Ending within 30 days</option>
        </select></label>
      <label class="wo-pick"><span>Sort:</span>
        <select name="sort" class="form-select" onchange="this.form.submit()">
          @foreach(['agency' => 'Agency', 'contract' => 'Contract no.', 'end' => 'End date', 'filled' => 'Most filled', 'vacant' => 'Most open posts'] as $k => $v)<option value="{{ $k }}" @selected($sort === $k)>{{ $v }}</option>@endforeach
        </select></label>
      <div class="wo-search"><i class="bi bi-search"></i><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by contract, bid, project, PI…" aria-label="Search contracts"></div>
      <div class="wo-views" role="group" aria-label="View">
        <button type="button" data-view="list" title="List view" aria-label="List view"><i class="bi bi-list-ul"></i></button>
        <button type="button" data-view="grid" title="Grid view" aria-label="Grid view"><i class="bi bi-grid"></i></button>
      </div>
      <button class="visually-hidden">Search</button>
    </form>

    <div class="wo-list" id="wo-list">
      @forelse($contracts as $row)
        @php($c = $row['contract'])
        <article class="wo-card">
          <div class="wo-row">
            <button type="button" class="wo-toggle" data-bs-toggle="collapse" data-bs-target="#wo-{{ $c->id }}" aria-expanded="false" aria-controls="wo-{{ $c->id }}">
              <i class="bi bi-chevron-right chev"></i><i class="bi bi-file-earmark-text doc"></i>
              <span class="min-w-0">
                <span class="d-flex align-items-center gap-2 flex-wrap"><span class="wo-no">{{ $c->contract_no }}</span>
                  <span @class(['wo-type', 'off' => $c->contract_type === 'offline'])>{{ $c->typeLabel() }}</span>
                  @if($row['state'] === 'completed')<span class="badge text-bg-secondary">Completed</span>@elseif($row['state'] === 'draft')<span class="badge text-bg-light border">Draft: no projects</span>@endif
                  @include('contracts._expiry', ['c' => $c])</span>
                <span class="wo-meta d-block">@if($c->bid_no)Bid: <b>{{ $c->bid_no }}</b> <span class="mx-1">|</span>@endif Agency: <b>{{ $c->agency->name }} ({{ $c->agency->code }})</b> <span class="mx-1">|</span> {{ $row['projectCount'] }} {{ \Illuminate\Support\Str::plural('project', $row['projectCount']) }}</span>
              </span>
            </button>
            <div class="wo-right">
              <span @class(['wo-fill', 'full' => $row['sanctioned'] && $row['filled'] >= $row['sanctioned'], 'part' => $row['filled'] < $row['sanctioned'], 'none' => ! $row['sanctioned']])><i class="bi bi-check2 me-1"></i>{{ $row['filled'] }}/{{ $row['sanctioned'] }} Filled</span>
              <x-fill-progress :filled="$row['filled']" :total="$row['sanctioned']" />
              @can('manage-masters')<a class="btn btn-sm wo-btn-blue" href="{{ route('contracts.show', $c) }}#add-project"><i class="bi bi-plus-lg me-1"></i>Add Project</a>@endcan
              <a class="btn btn-sm wo-btn-line" href="{{ route('contracts.show', $c) }}"><i class="bi bi-box-arrow-up-right me-1"></i>Open</a>
            </div>
          </div>
          <div class="collapse" id="wo-{{ $c->id }}">
            <div class="wo-body"><div class="table-responsive"><table class="table table-sm align-middle wo-table mb-0">
              <thead><tr><th style="width:4%">#</th><th style="width:13%">Project Code</th><th>Project Name</th><th>Project In-Charge</th><th class="text-center">Posts (Filled / Total)</th><th style="width:18%">Progress</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
              @forelse($c->projects as $i => $p)
                @php($pf = $p->posts->sum('active_employees_count')) @php($pt = $p->posts->sum('sanctioned_qty'))
                <tr><td>{{ $i + 1 }}</td><td><span class="wo-code">{{ $p->code }}</span></td><td>{{ $p->name }}</td>
                  <td>@if($p->incharge?->name ?? $p->pi_name)<i class="bi bi-person me-1 text-secondary"></i>{{ $p->incharge?->name ?? $p->pi_name }}@else<span class="text-secondary">—</span>@endif</td>
                  <td class="text-center fw-semibold">{{ $pf }} / {{ $pt }}</td>
                  <td><x-fill-progress :filled="$pf" :total="$pt" style="min-width:130px" /></td>
                  <td class="text-end text-nowrap">
                    <a class="wo-icon-btn" href="{{ route('contracts.show', $c) }}" title="View" aria-label="View {{ $p->code }}"><i class="bi bi-eye"></i></a>
                    @can('manage-masters')<a class="wo-icon-btn" href="{{ route('contracts.show', $c) }}#edit-p{{ $p->id }}" title="Edit project" aria-label="Edit {{ $p->code }}"><i class="bi bi-pencil"></i></a>@endcan
                    <span class="dropdown"><button type="button" class="wo-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More for {{ $p->code }}"><i class="bi bi-three-dots"></i></button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('posts.index', ['project' => $p->id]) }}"><i class="bi bi-diagram-3 me-2"></i>Posts</a></li>
                        <li><a class="dropdown-item" href="{{ route('employees.index', ['project' => $p->id]) }}"><i class="bi bi-people me-2"></i>Employees</a></li>
                        <li><a class="dropdown-item" href="{{ route('attendance.index', ['project' => $p->id]) }}"><i class="bi bi-calendar-check me-2"></i>Attendance</a></li>
                        @can('billing')<li><a class="dropdown-item" href="{{ route('invoices.index', ['project' => $p->id]) }}"><i class="bi bi-receipt me-2"></i>Sub-bills</a></li>@endcan
                      </ul></span>
                  </td></tr>
              @empty
                <tr><td colspan="7" class="text-secondary py-3">No projects yet.@can('manage-masters') Use <a href="{{ route('contracts.show', $c) }}#add-project">Add Project</a> to enter them with their PI, posts and staff.@endcan</td></tr>
              @endforelse
              </tbody></table></div>
              @can('manage-masters')<div class="text-end mt-2"><a class="btn btn-sm wo-btn-line" href="{{ route('contracts.edit', $c) }}"><i class="bi bi-pencil me-1"></i>Edit contract</a></div>@endcan
            </div>
          </div>
        </article>
      @empty
        <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No contracts match these filters.@if(request()->query()) <a href="{{ route('contracts.index') }}">Clear filters</a>@endif</div>
      @endforelse
    </div>
    @if($contracts->hasPages())<div class="mt-3">{{ $contracts->links() }}</div>@endif
  </div>
  <script>
    (function () {   // list / grid, remembered on this computer only
      const list = document.getElementById('wo-list'), btns = document.querySelectorAll('.wo-views [data-view]');
      let view = 'list';
      try { view = localStorage.getItem('wo-view') || 'list'; } catch (e) {}
      const apply = v => { list.classList.toggle('grid', v === 'grid'); btns.forEach(b => b.classList.toggle('on', b.dataset.view === v)); };
      btns.forEach(b => b.addEventListener('click', () => { apply(b.dataset.view); try { localStorage.setItem('wo-view', b.dataset.view); } catch (e) {} }));
      apply(view);
    })();
  </script>
</x-layouts.app>
