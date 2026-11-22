<x-layouts.app title="Posts" crumb="Masters / <span class='text-danger'>Posts</span>" :quiet-title="true">
  @php
    $q = request()->except('page');
    $tabUrl = fn ($t) => route('posts.index', array_filter(['tab' => $t === 'all' ? null : $t] + $q, fn ($v) => $v !== null && $v !== ''));
    $vacant = $figures['sanctioned'] - $figures['filled'];
    $pct = $figures['sanctioned'] ? (int) round($figures['filled'] * 100 / $figures['sanctioned']) : 0;
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-diagram-3"></i></div>
      <div class="me-auto"><h1>Posts – {{ $workingAgency?->name ?? 'Both agencies' }}</h1><p>Each post sits under a project, and each project under a work order. Use <b>Add post</b> to enter one; you can create the contract and project in the same window.</p></div>
      <div class="wo-actions d-flex gap-2 flex-wrap">
        @can('manage-masters')<button class="btn wo-btn-primary" data-bs-toggle="modal" data-bs-target="#addPostModal"><i class="bi bi-plus-lg me-1"></i>Add post</button>@endcan
        @can('billing')<a class="btn wo-btn-line" href="{{ route('reports.post-wise') }}"><i class="bi bi-receipt me-1"></i>Post-wise sub-bills</a>@endcan
      </div>
    </div>

    <div class="wo-kpis">
      <div class="wo-kpi k-blue"><i class="bi bi-diagram-3"></i><div><div class="l">Total posts</div><div class="v">{{ $figures['posts'] }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-stack"></i><div><div class="l">Projects</div><div class="v">{{ $figures['projects'] }}</div></div></div>
      <div class="wo-kpi k-amber"><i class="bi bi-clipboard-check"></i><div><div class="l">Sanctioned</div><div class="v">{{ $figures['sanctioned'] }}</div></div></div>
      <div class="wo-kpi k-green"><i class="bi bi-check2-circle"></i><div><div class="l">Filled</div><div class="v">{{ $figures['filled'] }} <small>({{ $pct }}%)</small></div>@if($figures['over'])<a class="small fw-semibold text-danger text-decoration-none" href="{{ $tabUrl('over') }}"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $figures['over'] }} more staff than sanctioned</a>@endif</div></div>
      <div class="wo-kpi k-red"><i class="bi bi-person-dash"></i><div><div class="l">Vacant</div><div class="v">{{ $vacant }}</div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('posts.index') }}">
      @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
      <nav class="wo-tabs" aria-label="Filled or vacant">
        <a href="{{ $tabUrl('all') }}" @class(['on' => $tab === 'all'])>All posts ({{ $counts['all'] }})</a>
        <a href="{{ $tabUrl('vacant') }}" @class(['on' => $tab === 'vacant'])><span class="wo-dot" style="background:#f59e0b"></span>Vacant ({{ $counts['vacant'] }})</a>
        <a href="{{ $tabUrl('filled') }}" @class(['on' => $tab === 'filled'])><span class="wo-dot" style="background:#16a34a"></span>Fully filled ({{ $counts['filled'] }})</a>
        <a href="{{ $tabUrl('over') }}" @class(['on' => $tab === 'over'])><span class="wo-dot" style="background:#dc2626"></span>Over-filled ({{ $counts['over'] }})</a>
      </nav>
      <label class="wo-pick"><span>Agency:</span>
        <select id="f-agency" name="agency" class="form-select" onchange="this.form.submit()"><option value="">All</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Client:</span>
        <select id="f-client" name="client" class="form-select" onchange="this.form.submit()"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Work order:</span>
        <select id="f-contract" name="contract" class="form-select" onchange="this.form.submit()"><option value="">All contracts</option>@foreach($pickContracts as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected(request('contract') == $k->id)>{{ $k->pickLabel() }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Project:</span>
        <select id="f-project" name="project" class="form-select" onchange="this.form.submit()"><option value="">All projects</option>@foreach($pickProjects as $pp)<option value="{{ $pp->id }}" data-contract="{{ $pp->gem_contract_id }}" @selected(request('project') == $pp->id)>{{ $pp->name }}{{ $pp->pi_name ? ' · '.$pp->pi_name : '' }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Post:</span>
        <select id="f-post" name="post" class="form-select" onchange="this.form.submit()"><option value="">All posts</option>@foreach($designations as $d)<option value="{{ $d }}" @selected($post && \App\Http\Controllers\PostController::postKey($post) === \App\Http\Controllers\PostController::postKey($d))>{{ $d }}</option>@endforeach</select></label>
      @if(request()->query())<a class="btn btn-link px-1" href="{{ route('posts.index') }}">Clear</a>@endif
      <noscript><button class="btn wo-btn-line">Show</button></noscript>
    </form>

    @if($post)
      @php($inProjects = $posts->map(fn ($p) => $p->project)->unique('id'))
      <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center" id="post-summary">
        <b>{{ $posts->first()?->designation ?? $post }}</b>
        <span>in {{ $inProjects->count() }} {{ \Illuminate\Support\Str::plural('project', $inProjects->count()) }}:</span>
        @foreach($inProjects as $pj)<span class="wo-code">{{ $pj->code }}</span>@endforeach
        <span class="ms-md-2">{{ $posts->sum('sanctioned_qty') }} sanctioned · {{ $posts->sum('active_employees_count') }} filled</span>
        @can('billing')<a class="ms-auto btn btn-sm wo-btn-blue" href="{{ route('reports.post-wise', ['post' => $posts->first()?->designation ?? $post]) }}"><i class="bi bi-receipt me-1"></i>Post-wise sub-bills</a>@endcan
      </div>
    @endif

    {{-- One card per project, open by default, with its posts in a table. --}}
    @forelse($posts->groupBy('project_id') as $projectPosts)
      @php($pj = $projectPosts->first()->project) @php($k = $pj->contract)
      @php($sanc = $projectPosts->sum('sanctioned_qty')) @php($fill = $projectPosts->sum(fn ($p) => min($p->active_employees_count, $p->sanctioned_qty)))
      @php($over = $projectPosts->sum(fn ($p) => max(0, $p->active_employees_count - $p->sanctioned_qty)))
      <article class="wo-card">
        <div class="wo-row">
          <button type="button" class="wo-toggle" data-bs-toggle="collapse" data-bs-target="#pp-{{ $pj->id }}" aria-expanded="true" aria-controls="pp-{{ $pj->id }}">
            <i class="bi bi-chevron-right chev"></i><span class="wo-code">{{ $pj->code }}</span>
            <span class="min-w-0">
              <span class="wo-no d-block">{{ $pj->name }}</span>
              <span class="wo-meta d-block">Work order: <b>{{ $k->contract_no }}</b> <span class="mx-1">|</span> Agency: <b>{{ $k->agency->name }} ({{ $k->agency->code }})</b> <span class="mx-1">|</span> Client: <b>{{ $k->client->name }}</b>@if($pj->pi_name) <span class="mx-1">|</span> PI: <b>{{ $pj->pi_name }}</b>@endif</span>
            </span>
          </button>
          <div class="wo-right">
            <x-names-pop :class="\Illuminate\Support\Arr::toCssClasses(['wo-fill', 'full' => $fill >= $sanc && $sanc > 0, 'part' => $fill > 0 && $fill < $sanc, 'none' => $fill === 0])" :title="'Staff on '.$pj->code"
              :items="$projectPosts->flatMap(fn ($p) => $p->activeEmployees->map(fn ($e) => ['text' => $e->name, 'url' => route('employees.show', $e), 'sub' => $p->designation]))">@if($fill >= $sanc && $sanc > 0)<i class="bi bi-check2 me-1"></i>@endif{{ $fill }}/{{ $sanc }} Filled</x-names-pop>
            @if($over)<span class="wo-fill over" title="More staff placed than sanctioned"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $over }} over</span>@endif
            @can('manage-masters')<button type="button" class="btn btn-sm wo-btn-blue" data-bs-toggle="modal" data-bs-target="#addPostModal" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}"><i class="bi bi-plus-lg me-1"></i>Add post</button>@endcan
            <a class="btn btn-sm wo-btn-line" href="{{ route('contracts.show', $k) }}"><i class="bi bi-box-arrow-up-right me-1"></i>Open work order</a>
          </div>
        </div>
        <div class="collapse show" id="pp-{{ $pj->id }}">
          <div class="wo-body"><div class="table-responsive"><table class="table table-sm align-middle wo-table wo-posts mb-0">
            {{-- Same column widths in every card so the figures line up from project to project. --}}
            <colgroup><col><col style="width:12%"><col style="width:12%"><col style="width:12%"><col style="width:14%"><col style="width:150px"></colgroup>
            <thead><tr><th>Designation</th><th class="num">Sanctioned</th><th class="num">Filled</th><th class="num">Vacant</th><th class="num">Rate</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($projectPosts as $p)
              @php($extra = (int) $p->active_employees_count - (int) $p->sanctioned_qty)
              <tr @class(['wo-over' => $extra > 0, 'wo-full' => $extra === 0 && $p->sanctioned_qty > 0, 'wo-vacant' => $extra < 0])>
                <td class="fw-semibold">{{ $p->designation }}</td>
                <td class="num">{{ $p->sanctioned_qty }}</td>
                <td class="num"><x-names-pop :class="$extra > 0 ? 'n-over' : ($extra < 0 ? 'n-vacant' : 'n-full')" :title="'Staff on '.$p->designation.' ('.$pj->code.')'"
                  :items="$p->activeEmployees->map(fn ($e) => ['text' => $e->name, 'url' => route('employees.show', $e), 'sub' => $e->emp_code])">{{ $p->active_employees_count }}</x-names-pop></td>
                <td class="num">@if($extra > 0)<span class="badge text-bg-danger">{{ $extra }} over</span>@else{{ max(0, -$extra) ?: '—' }}@endif</td>
                <td class="num">{{ inr($p->rate) }}{{ $k->billing_type === 'daily' ? '/day' : '' }}</td>
                <td class="text-end text-nowrap">@can('manage-masters')<a class="btn btn-sm wo-btn-blue" href="{{ route('contracts.show', $k) }}#edit-post{{ $p->id }}"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
                  <a class="btn btn-sm wo-btn-line" href="{{ route('posts.index', ['post' => $p->designation]) }}" title="This post in every project"><i class="bi bi-funnel"></i></a></td>
              </tr>
            @endforeach
            </tbody></table></div></div>
        </div>
      </article>
    @empty
      <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No posts match these filters.@if(request()->query()) <a href="{{ route('posts.index') }}">Clear filters</a>@else Click <b>Add post</b> to enter the first one.@endif</div>
    @endforelse
  </div>
  @can('manage-masters')@include('posts._add-modal', ['onSaved' => 'reload'])@endcan
  <script>
    (function () {
      // Agency narrows clients; agency and client narrow contracts; contract narrows projects.
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client'),
            contract = document.getElementById('f-contract'), project = document.getElementById('f-project');
      function narrow(select, test) {
        [...select.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !test(o); } });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
      }
      function update() {
        narrow(client, o => !agency.value || o.dataset.agency === agency.value);
        narrow(contract, o => (!agency.value || o.dataset.agency === agency.value) && (!client.value || o.dataset.client === client.value));
        const allowed = new Set([...contract.options].filter(o => o.value && !o.disabled).map(o => o.value));
        narrow(project, o => contract.value ? o.dataset.contract === contract.value : allowed.has(o.dataset.contract));
      }
      [agency, client, contract].forEach(el => el.addEventListener('change', update)); update();
    })();
  </script>
</x-layouts.app>
