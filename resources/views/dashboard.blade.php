<x-layouts.app title="Dashboard" crumb="Admin dashboard" :quiet-title="true">
  @php($q = array_filter(['agency' => $agencyId, 'client' => $clientId, 'contract' => $contractId, 'project' => $projectId]))
  @php($me = auth()->user())
  @php($myAgency = \App\Support\Agency\AgencyContext::agency())
  <div class="wo">
  {{-- Work Orders style (owner, 26 Nov 2026): heading with the agency you work for, figure boxes and a filter bar. --}}
  <div class="wo-head">
    @if($myAgency && $myAgency->logo_path)<img src="{{ route('agencies.logo', $myAgency) }}" alt="" class="wo-head-icon" style="object-fit:contain;padding:4px">@else<div class="wo-head-icon"><i class="bi bi-grid"></i></div>@endif
    <div class="me-auto">
      <h1>Dashboard</h1>
      @if($myAgency)
        <p>You are working for <b class="text-body">{{ $myAgency->name }}</b> · agency code {{ $myAgency->code }}@if($myAgency->gstin) · GSTIN {{ $myAgency->gstin }}@endif · all data you see and enter is for this agency only</p>
      @else
        <p>You are working for <b class="text-body">Both agencies</b> · {{ $agencies->pluck('name')->implode(' and ') ?: 'No agencies added yet' }} · use the Agency filter below to look at one</p>
      @endif
    </div>
    <div class="text-end small text-secondary d-none d-md-block">{{ $me->name }}<br>{{ \App\Models\User::ROLES[$me->role] ?? $me->role }}</div>
  </div>
  @unless(\App\Services\Approvals\Approvals::canApprove($me))
    @php($mkWaiting = \App\Models\Approval::query()->where('status', 'pending')->count())
    @php($mkReturned = \App\Models\Approval::query()->where('status', 'returned')->where('submitted_by', $me->id)->where('decided_at', '>=', now()->subDays(14))->count())
    @if($mkWaiting || $mkReturned)
    <a href="{{ route('approvals.index') }}" class="wo-card mb-3 p-3 d-flex flex-wrap gap-3 align-items-center text-decoration-none text-body" id="my-approvals">
      <i class="bi bi-person-check fs-4"></i>
      <span class="fw-semibold">Your approvals</span>
      <span class="badge text-bg-secondary">{{ $mkWaiting }} waiting for Super Admin</span>
      <span class="badge {{ $mkReturned ? 'text-bg-danger' : 'text-bg-light border' }}">{{ $mkReturned }} returned to you</span>
      <span class="ms-auto small">Open Approvals <i class="bi bi-chevron-right"></i></span>
    </a>
    @endif
  @endunless
  <form class="wo-bar" method="get" action="{{ route('dashboard') }}">
    @unless($myAgency)
    <label class="wo-pick" for="f-agency"><span>Agency:</span>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
    @endunless
    <label class="wo-pick" for="f-client"><span>Client:</span>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
    <label class="wo-pick" for="f-contract"><span>Work order:</span>
      <select id="f-contract" name="contract" class="form-select"><option value="">All work orders</option>@foreach($contractOptions as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected($contractId === $k->id)>{{ $k->pickLabel() }}</option>@endforeach</select></label>
    <label class="wo-pick" for="f-project"><span>Project:</span>
      <select id="f-project" name="project" class="form-select"><option value="">All projects</option>@foreach($projectOptions as $pp)<option value="{{ $pp->id }}" data-agency="{{ $pp->contract->agency_id }}" data-client="{{ $pp->contract->client_id }}" data-contract="{{ $pp->gem_contract_id }}" @selected($projectId === $pp->id)>{{ $pp->label() }}</option>@endforeach</select></label>
    <button class="btn wo-btn-primary">Show</button>
    @if($q)<a class="btn btn-link px-1" href="{{ route('dashboard') }}">Clear</a>@endif
    <span class="small text-secondary w-100">
      Showing {{ $myAgency ? $myAgency->name : ($agencyId ? $agencies->firstWhere('id', $agencyId)?->name : 'all agencies') }}{{ $clientId ? ' · '.$clients->firstWhere('id', $clientId)?->name : '' }}{{ $contractId ? ' · '.$contractOptions->firstWhere('id', $contractId)?->bid_no : '' }}{{ $projectId ? ' · '.$projectOptions->firstWhere('id', $projectId)?->name : '' }}
    </span>
  </form>
  <div class="wo-kpis wo-kpis-6">
    <div class="wo-kpi k-blue"><i class="bi bi-bank"></i><div><div class="l">Clients</div><div class="v">{{ $stats['clients'] }}</div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-folder2"></i><div><div class="l">Active work orders</div><div class="v">{{ $stats['contracts'] }}</div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-people"></i><div><div class="l">Employees deployed</div><div class="v">{{ $stats['employees'] }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-person-dash"></i><div><div class="l">Posts vacant</div><div class="v">{{ $stats['vacant'] }} <small>of {{ $stats['sanctioned'] }}</small></div></div></div>
    <div class="wo-kpi k-blue"><i class="bi bi-receipt"></i><div><div class="l">Billed this year</div><div class="v" style="font-size:1.2rem">{{ inr($stats['billedThisYear']) }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-hourglass-split"></i><div><div class="l">Awaiting payment</div><div class="v" style="font-size:1.2rem">{{ inr($stats['unpaid']) }}</div></div></div>
  </div>
  {{-- Payments received (owner, 22 Nov 2026): this month's approved credits and what is still due after part payments. --}}
  @can('billing')
    @php($payMonth = \App\Models\PaymentReceiptLine::query()->whereHas('receipt', fn ($q) => $q->where('status', 'approved')->whereBetween('credit_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]))->sum('received'))
    @php($payDue = app(\App\Services\Payments\PaymentReports::class)->outstanding(null))
    @php($payWaiting = \App\Models\PaymentReceipt::query()->where('status', 'pending')->count())
    <div class="wo-card px-3 py-2 mb-3 d-flex flex-wrap gap-3 align-items-center small dash-payments">
      <span><i class="bi bi-cash-coin me-1 text-success"></i>Received in {{ now()->format('F') }}: <b>{{ inr((float) $payMonth, true) }}</b></span>
      <span><i class="bi bi-hourglass-split me-1 text-danger"></i>Still due from the client: <b>{{ inr((float) $payDue['total']['due'], true) }}</b> ({{ $payDue['rows']->count() }} sub-bills{{ (float) $payDue['ages']['90+'] > 0 ? ', '.inr((float) $payDue['ages']['90+'], true).' over 90 days' : '' }})</span>
      @if($payWaiting)<span class="badge text-bg-warning">{{ $payWaiting }} {{ \Illuminate\Support\Str::plural('payment', $payWaiting) }} waiting for approval</span>@endif
      <a class="ms-auto" href="{{ route('payments.index') }}">Payments received →</a>
    </div>
  @endcan
  @include('dashboard._reminders')
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="z-card h-100">
        <div class="z-card-h"><h2>Billed vs paid, {{ now()->year }}</h2><span class="small text-secondary">by bill month</span></div>
        <div class="p-3">
          @php($max = max(1, collect($monthly)->max('billed') ?? 1))
          <div class="d-flex align-items-end gap-2" style="height:200px">
            @foreach (range(1, 12) as $m)
              @php($b = $monthly[$m]['billed'] ?? 0) @php($p = $monthly[$m]['paid'] ?? 0)
              <div class="flex-fill d-flex flex-column align-items-center gap-1 h-100 justify-content-end" title="{{ \Carbon\Carbon::create(null, $m)->format('M') }}: billed {{ inr($b) }}, paid {{ inr($p) }}">
                <div class="d-flex align-items-end gap-1 w-100 justify-content-center" style="height:100%">
                  <div style="width:40%;height:{{ $b / $max * 100 }}%;background:#1d4ed8;border-radius:3px 3px 0 0"></div>
                  <div style="width:40%;height:{{ $p / $max * 100 }}%;background:#1d1a1c;opacity:.75;border-radius:3px 3px 0 0"></div>
                </div>
                <small class="text-secondary">{{ \Carbon\Carbon::create(null, $m)->format('M') }}</small>
              </div>
            @endforeach
          </div>
          <div class="small text-secondary mt-2"><span class="d-inline-block me-1" style="width:10px;height:10px;background:#1d4ed8"></span>Billed <span class="d-inline-block ms-3 me-1" style="width:10px;height:10px;background:#1d1a1c;opacity:.75"></span>Paid</div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="z-card h-100">
        <div class="z-card-h"><h2>{{ $lastMonth->format('F Y') }} billing</h2></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between"><span>Projects</span><b>{{ $projectsTotal }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Attendance locked</span><b>{{ $projectsLocked }}</b></li>
          @php($submittedByPi = \App\Models\AttendanceSheet::query()->where('status', 'submitted')->count())
          <li class="list-group-item d-flex justify-content-between"><span>Submitted by PIs, to check</span><b>@if($submittedByPi)<span class="badge text-bg-info">{{ $submittedByPi }}</span>@else 0 @endif</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Bills made</span><b>{{ $projectsBilled }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Draft bills (not yet sent)</span><b>{{ $drafts }}</b></li>
          <li class="list-group-item d-flex justify-content-between align-items-center"><span>Staff with missing bank details</span>
            <a class="badge text-bg-{{ $bankProblems ? 'danger' : 'success' }} text-decoration-none" href="{{ route('employees.index', ['bank' => 'problem'] + $q) }}">{{ $bankProblems }}</a></li>
        </ul>
        <div class="p-3 d-grid gap-2">
          <a class="btn btn-outline-secondary" href="{{ route('attendance.index', ['month' => $lastMonth->format('Y-m')] + $q) }}"><i class="bi bi-calendar-check me-1"></i>Open attendance</a>
          <a class="btn btn-primary" href="{{ route('invoices.index', $q) }}"><i class="bi bi-receipt me-1"></i>Make bills</a>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="z-card">
        <div class="z-card-h"><h2>By agency and client</h2><span class="small text-secondary">{{ $lastMonth->format('F') }} attendance locked · billed in {{ now()->year }}</span></div>
        <div class="table-responsive"><table class="table table-hover">
          <thead><tr><th>Agency</th><th>Client</th><th class="num">Contracts</th><th class="num">Staff</th><th class="num">Vacant</th><th class="num">{{ $lastMonth->format('M') }} locked</th><th class="num">Billed {{ now()->year }}</th><th class="num">Awaiting payment</th><th></th></tr></thead>
          <tbody>
          @php($blk = false)
          @forelse($summary as $r)
            @if($blk === false || $blk !== $r['client']->agency_id)
              @php($blk = $r['client']->agency_id)
              @php($rows = $summary->filter(fn ($x) => $x['client']->agency_id === $blk))
              <tr class="table-secondary"><td colspan="9" class="py-2">
                <b class="fs-6">{{ $r['client']->agency?->name ?? 'No agency set' }}</b> @if($r['client']->agency)<span class="badge text-bg-dark">{{ $r['client']->agency->code }}</span>@endif
                <span class="small text-secondary ms-2">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('client', $rows->count()) }} · {{ $rows->sum('contracts') }} {{ \Illuminate\Support\Str::plural('contract', $rows->sum('contracts')) }} · {{ $rows->sum('employees') }} staff · {{ inr($rows->sum(fn ($x) => (float) $x['billed'])) }} billed · {{ inr($rows->sum(fn ($x) => (float) $x['unpaid'])) }} awaiting payment</span>
              </td></tr>
            @endif
            @php($c = $r['client'])
            <tr>
              <td><span class="badge text-bg-light border" title="{{ $c->agency?->name }}">{{ $c->agency?->code ?? '—' }}</span></td>
              <td class="fw-semibold">{{ $c->name }}</td>
              <td class="num">{{ $r['contracts'] }}</td>
              <td class="num">{{ $r['employees'] }}</td>
              <td class="num">{{ max(0, $r['sanctioned'] - $r['placed']) }}</td>
              <td class="num">{{ $r['locked'] }} / {{ $r['projects'] }}</td>
              <td class="num">{{ inr($r['billed']) }}</td>
              <td class="num">{{ inr($r['unpaid']) }}</td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard', ['agency' => $c->agency_id, 'client' => $c->id]) }}">View</a></td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-secondary py-4">No clients yet.</td></tr>
          @endforelse
          </tbody></table></div>
      </div>
    </div>
    <div class="col-12">
      <div class="z-card">
        <div class="z-card-h"><h2>By work order and project</h2><span class="small text-secondary">{{ $projectRows->count() }} {{ \Illuminate\Support\Str::plural('project', $projectRows->count()) }} · {{ $lastMonth->format('F') }} status · billed in {{ now()->year }}</span></div>
        <div class="table-responsive" style="max-height:420px"><table class="table table-hover table-sm">
          <thead class="sticky-top"><tr><th>Agency</th><th>Client</th><th>Work order</th><th>Project</th><th class="num">Staff / sanctioned</th><th>{{ $lastMonth->format('M') }} attendance</th><th class="num">Billed {{ now()->year }}</th><th></th></tr></thead>
          <tbody>
          @php($blk = false)
          @forelse($projectRows as $r)
            @if($blk === false || $blk !== $r['project']->contract->agency_id)
              @php($blk = $r['project']->contract->agency_id)
              @php($rows = $projectRows->filter(fn ($x) => $x['project']->contract->agency_id === $blk))
              <tr class="table-secondary"><td colspan="8" class="py-2">
                <b class="fs-6">{{ $r['project']->contract->agency->name }}</b> <span class="badge text-bg-dark">{{ $r['project']->contract->agency->code }}</span>
                <span class="small text-secondary ms-2">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('project', $rows->count()) }} · {{ $rows->sum('staff') }} / {{ $rows->sum('sanctioned') }} staff · {{ $rows->whereIn('last', ['locked', 'billed'])->count() }} locked · {{ $rows->where('last', 'billed')->count() }} billed for {{ $lastMonth->format('F') }}</span>
              </td></tr>
            @endif
            @php($p = $r['project'])
            @php($badge = ['billed' => 'success', 'locked' => 'primary', 'submitted' => 'info', 'draft' => 'warning', 'not started' => 'light border'][$r['last']] ?? 'secondary')
            <tr>
              <td><span class="badge text-bg-light border" title="{{ $p->contract->agency->name }}">{{ $p->contract->agency->code }}</span></td>
              <td class="small">{{ $p->contract->client->name }}</td>
              <td><a class="mono text-decoration-none" href="{{ route('contracts.show', $p->contract) }}">{{ $p->contract->contract_no }}</a>@if($p->contract->bid_no)<div class="small text-secondary">Bid {{ $p->contract->bid_no }}</div>@endif</td>
              <td><span class="fw-semibold">{{ $p->name }}</span>@if($p->pi_name) <div class="small text-secondary">PI: {{ $p->pi_name }}</div> @endif</td>
              <td class="num">{{ $r['staff'] }} / {{ $r['sanctioned'] }}</td>
              <td><span class="badge text-bg-{{ $badge }}">{{ ucfirst($r['last']) }}</span></td>
              <td class="num">{{ inr($r['billed']) }}</td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard', ['agency' => $p->contract->agency_id, 'client' => $p->contract->client_id, 'contract' => $p->gem_contract_id, 'project' => $p->id]) }}">View</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No projects match these filters.</td></tr>
          @endforelse
          </tbody></table></div>
      </div>
    </div>
    <div class="col-12">
      <div class="z-card">
        <div class="z-card-h"><h2>Recent bills</h2><a class="ms-auto small" href="{{ route('invoices.index', $q) }}">View all</a></div>
        <div class="table-responsive"><table class="table table-hover">
          <thead><tr><th>Bill no.</th><th>Agency</th><th>Client</th><th>Work order</th><th>Project</th><th>Month</th><th class="num">Amount</th><th>Status</th></tr></thead>
          <tbody>
          @php($blk = false)
          @forelse ($recent as $i)
            @if($blk === false || $blk !== $i->agency_id)
              @php($blk = $i->agency_id)
              <tr class="table-secondary"><td colspan="8" class="py-2"><b class="fs-6">{{ $i->contract->agency->name }}</b> <span class="badge text-bg-dark">{{ $i->contract->agency->code }}</span></td></tr>
            @endif
            <tr><td><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td><td><span class="badge text-bg-light border">{{ $i->contract->agency->code }}</span></td><td>{{ $i->contract->client->name }}</td><td class="mono">{{ $i->contract->contract_no }}</td><td>{{ $i->project->name }}</td><td>{{ $i->month->format('M Y') }}</td><td class="num">{{ inr($i->rounded_total) }}</td><td><x-status :value="$i->status" /></td></tr>
          @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No bills yet. Lock a month's attendance, then make its bill.</td></tr>
          @endforelse
          </tbody></table></div>
      </div>
    </div>
  </div>
  <script>
    (function () {
      // Agency narrows clients; agency and client narrow contracts; contract narrows projects.
      const agency = document.getElementById('f-agency') || { value: '', addEventListener() {} }, client = document.getElementById('f-client'),
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
  </div>
</x-layouts.app>
