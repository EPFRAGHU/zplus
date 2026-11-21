<x-layouts.app title="Attendance" crumb="Operations / <span class='text-danger'>Attendance</span>" :quiet-title="true">
  @php
    $q = request()->except('page');
    $tabUrl = fn ($t) => route('attendance.index', array_filter(['tab' => $t === 'all' ? null : $t] + $q, fn ($v) => $v !== null && $v !== ''));
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-calendar-check"></i></div>
      <div class="me-auto"><h1>Attendance – {{ $month->format('F Y') }}</h1>
        <p>{{ $workingAgency?->name ?? 'Both agencies' }} · {{ $figures['locked'] }} of {{ $figures['projects'] }} projects locked · mark or check the sheet, upload the signed certificate, then lock it: the sub-bill is made automatically.</p></div>
    </div>

    <div class="wo-kpis">
      <div class="wo-kpi k-blue"><i class="bi bi-stack"></i><div><div class="l">Projects</div><div class="v">{{ $figures['projects'] }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-people"></i><div><div class="l">Staff</div><div class="v">{{ $figures['staff'] }}</div></div></div>
      <div class="wo-kpi k-amber"><i class="bi bi-hourglass-split"></i><div><div class="l">Waiting (not locked)</div><div class="v">{{ $figures['projects'] - $figures['locked'] }}</div></div></div>
      <div class="wo-kpi k-green"><i class="bi bi-lock"></i><div><div class="l">Locked</div><div class="v">{{ $figures['locked'] }}</div></div></div>
      <div class="wo-kpi k-red"><i class="bi bi-receipt"></i><div><div class="l">Billed for {{ $month->format('F') }}</div><div class="v">{{ $figures['billed'] }}</div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('attendance.index') }}">
      @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
      <nav class="wo-tabs" aria-label="Sheet status">
        <a href="{{ $tabUrl('all') }}" @class(['on' => $tab === 'all'])>All ({{ $counts['all'] }})</a>
        <a href="{{ $tabUrl('not_started') }}" @class(['on' => $tab === 'not_started'])><span class="wo-dot" style="background:#94a3b8"></span>Not started ({{ $counts['not_started'] }})</a>
        <a href="{{ $tabUrl('draft') }}" @class(['on' => $tab === 'draft'])><span class="wo-dot" style="background:#f59e0b"></span>In progress ({{ $counts['draft'] }})</a>
        <a href="{{ $tabUrl('submitted') }}" @class(['on' => $tab === 'submitted'])><span class="wo-dot" style="background:#0ea5e9"></span>Submitted by PI ({{ $counts['submitted'] }})</a>
        <a href="{{ $tabUrl('locked') }}" @class(['on' => $tab === 'locked'])><span class="wo-dot" style="background:#16a34a"></span>Locked ({{ $counts['locked'] }})</a>
      </nav>
      <div class="wo-pick"><span>Month:</span><x-month-picker :value="$month->format('Y-m')" /></div>
      <label class="wo-pick"><span>Agency:</span>
        <select id="f-agency" name="agency" class="form-select"><option value="">All</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Client:</span>
        <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Work order:</span>
        <select id="f-contract" name="contract" class="form-select"><option value="">All contracts</option>@foreach($contracts as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected(request('contract') == $k->id)>{{ $k->pickLabel() }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Project:</span>
        <select id="f-project" name="project" class="form-select"><option value="">All projects</option>@foreach($allProjects as $pp)<option value="{{ $pp->id }}" data-agency="{{ $pp->contract->agency_id }}" data-client="{{ $pp->contract->client_id }}" data-contract="{{ $pp->gem_contract_id }}" @selected(request('project') == $pp->id)>{{ $pp->label() }}</option>@endforeach</select></label>
      <button class="btn wo-btn-primary">Show</button>
      @if(request()->except('month'))<a class="btn btn-link px-1" href="{{ route('attendance.index', ['month' => $month->format('Y-m')]) }}">Clear</a>@endif
    </form>

    {{-- One card per project: where its sheet stands this month and what to do next. --}}
    @forelse($shown as $p)
      @php($sheet = $sheets[$p->id] ?? null) @php($state = \App\Http\Controllers\AttendanceController::sheetState($sheet))
      <article class="wo-card">
        <div class="wo-row">
          <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0" style="flex-basis:320px">
            <span class="wo-code">{{ $p->code }}</span>
            <span class="min-w-0">
              <span class="d-flex align-items-center gap-2 flex-wrap"><span class="wo-no">{{ $p->name }}</span>
                @if(! $sheet)<span class="badge text-bg-light border">Not started</span>@elseif($sheet->invoice)<span class="badge text-bg-success">Billed</span>@elseif($sheet->isSubmitted())<span class="badge text-bg-info">Submitted by PI · check</span>@else<x-status :value="$sheet->status" />@endif</span>
              <span class="wo-meta d-block">Work order: <a class="mono text-decoration-none" href="{{ route('contracts.show', $p->contract) }}">{{ $p->contract->contract_no }}</a> <span class="mx-1">|</span> Agency: <b>{{ $p->contract->agency->name }} ({{ $p->contract->agency->code }})</b> <span class="mx-1">|</span> Client: <b>{{ $p->contract->client->name }}</b> <span class="mx-1">|</span> PI: <b>{{ $p->pi_name ?? '—' }}</b></span>
            </span>
          </div>
          <div class="wo-right">
            <span class="wo-fill none"><i class="bi bi-people me-1"></i>{{ $sheet?->entries_count ?? $p->employees_count }} staff</span>
            @if($sheet)
              @if($sheet->invoice)<a class="btn btn-sm wo-btn-line" href="{{ route('invoices.show', $sheet->invoice) }}"><i class="bi bi-receipt me-1"></i>{{ $sheet->invoice->invoice_no }}</a>@endif
              <a @class(['btn btn-sm', 'wo-btn-primary' => $state !== 'locked', 'wo-btn-blue' => $state === 'locked']) href="{{ route('attendance.show', $sheet) }}"><i class="bi bi-box-arrow-up-right me-1"></i>Open sheet</a>
            @else
              <form method="post" action="{{ route('attendance.open') }}" class="d-flex gap-2">@csrf
                <input type="hidden" name="project_id" value="{{ $p->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input name="holidays" class="form-control form-control-sm" placeholder="Holidays, e.g. 2, 19, 20" aria-label="Holiday dates for {{ $p->name }}" style="max-width:190px">
                <button class="btn btn-sm wo-btn-primary text-nowrap" @disabled($p->employees_count === 0) title="{{ $p->employees_count === 0 ? 'No employees on this project yet' : '' }}"><i class="bi bi-play-fill me-1"></i>Start sheet</button>
              </form>
              @can('show-delete')@if($p->employees_count === 0 && ! \App\Http\Controllers\ProjectController::cannotDelete($p))
                <form method="post" action="{{ route('projects.destroy', $p) }}" data-confirm="Delete project {{ $p->name }} ({{ $p->code }})? It has no staff, attendance or bills." onsubmit="return confirm(this.dataset.confirm)">@csrf @method('delete')
                  <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i> Delete this empty project</button></form>
              @endif @endcan
            @endif
          </div>
        </div>
      </article>
    @empty
      <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No projects match. Change the filters or the tab, or add work orders first.</div>
    @endforelse
  </div>
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
        const fits = o => (!agency.value || o.dataset.agency === agency.value) && (!client.value || o.dataset.client === client.value);
        narrow(contract, fits);
        narrow(project, o => fits(o) && (!contract.value || o.dataset.contract === contract.value));
      }
      [agency, client, contract].forEach(el => el.addEventListener('change', update)); update();
    })();
  </script>
</x-layouts.app>
