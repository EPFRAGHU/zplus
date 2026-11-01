<x-layouts.app title="Dashboard" crumb="Admin dashboard">
  @php($q = array_filter(['agency' => $agencyId, 'client' => $clientId, 'contract' => $contractId, 'project' => $projectId]))
  <form class="z-card p-3 mb-3 d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-contract">GeM contract</label>
      <select id="f-contract" name="contract" class="form-select"><option value="">All contracts</option>@foreach($contractOptions as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected($contractId === $k->id)>{{ $k->bid_no }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-project">Project</label>
      <select id="f-project" name="project" class="form-select"><option value="">All projects</option>@foreach($projectOptions as $pp)<option value="{{ $pp->id }}" data-agency="{{ $pp->contract->agency_id }}" data-client="{{ $pp->contract->client_id }}" data-contract="{{ $pp->gem_contract_id }}" @selected($projectId === $pp->id)>{{ $pp->label() }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Show</button>
    @if($q)<a class="btn btn-link" href="{{ route('dashboard') }}">Clear</a>@endif
    <span class="small text-secondary w-100">
      Showing {{ $agencyId ? $agencies->firstWhere('id', $agencyId)?->name : 'all agencies' }}{{ $clientId ? ' · '.$clients->firstWhere('id', $clientId)?->name : '' }}{{ $contractId ? ' · '.$contractOptions->firstWhere('id', $contractId)?->bid_no : '' }}{{ $projectId ? ' · '.$projectOptions->firstWhere('id', $projectId)?->name : '' }}
    </span>
  </form>
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-3">
    <x-kpi label="Clients" :value="$stats['clients']" />
    <x-kpi label="Active GeM contracts" :value="$stats['contracts']" color="#1f63c4" />
    <x-kpi label="Employees deployed" :value="$stats['employees']" color="#1f8a4c" />
    <x-kpi label="Posts vacant" :value="$stats['vacant']" :meta="'of '.$stats['sanctioned'].' sanctioned'" color="#b26b00" />
    <x-kpi label="Billed this year" :value="inr($stats['billedThisYear'])" color="#1d1a1c" />
    <x-kpi label="Awaiting payment" :value="inr($stats['unpaid'])" meta="Approved and submitted bills" color="#5b3fd1" />
  </div>
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
                  <div style="width:40%;height:{{ $b / $max * 100 }}%;background:#e23d4e;border-radius:3px 3px 0 0"></div>
                  <div style="width:40%;height:{{ $p / $max * 100 }}%;background:#1d1a1c;opacity:.75;border-radius:3px 3px 0 0"></div>
                </div>
                <small class="text-secondary">{{ \Carbon\Carbon::create(null, $m)->format('M') }}</small>
              </div>
            @endforeach
          </div>
          <div class="small text-secondary mt-2"><span class="d-inline-block me-1" style="width:10px;height:10px;background:#e23d4e"></span>Billed <span class="d-inline-block ms-3 me-1" style="width:10px;height:10px;background:#1d1a1c;opacity:.75"></span>Paid</div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="z-card h-100">
        <div class="z-card-h"><h2>{{ $lastMonth->format('F Y') }} billing</h2></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between"><span>Projects</span><b>{{ $projectsTotal }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Attendance locked</span><b>{{ $projectsLocked }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Bills made</span><b>{{ $projectsBilled }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Draft bills to approve</span><b>{{ $drafts }}</b></li>
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
        <div class="z-card-h"><h2>By GeM contract and project</h2><span class="small text-secondary">{{ $projectRows->count() }} {{ \Illuminate\Support\Str::plural('project', $projectRows->count()) }} · {{ $lastMonth->format('F') }} status · billed in {{ now()->year }}</span></div>
        <div class="table-responsive" style="max-height:420px"><table class="table table-hover table-sm">
          <thead class="sticky-top"><tr><th>Agency</th><th>Client</th><th>GeM contract</th><th>Project</th><th class="num">Staff / sanctioned</th><th>{{ $lastMonth->format('M') }} attendance</th><th class="num">Billed {{ now()->year }}</th><th></th></tr></thead>
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
            @php($badge = ['billed' => 'success', 'locked' => 'primary', 'draft' => 'warning', 'not started' => 'light border'][$r['last']])
            <tr>
              <td><span class="badge text-bg-light border" title="{{ $p->contract->agency->name }}">{{ $p->contract->agency->code }}</span></td>
              <td class="small">{{ $p->contract->client->name }}</td>
              <td><a class="mono text-decoration-none" href="{{ route('contracts.show', $p->contract) }}">{{ $p->contract->bid_no }}</a></td>
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
          <thead><tr><th>Bill no.</th><th>Agency</th><th>Client</th><th>GeM contract</th><th>Project</th><th>Month</th><th class="num">Amount</th><th>Status</th></tr></thead>
          <tbody>
          @php($blk = false)
          @forelse ($recent as $i)
            @if($blk === false || $blk !== $i->agency_id)
              @php($blk = $i->agency_id)
              <tr class="table-secondary"><td colspan="8" class="py-2"><b class="fs-6">{{ $i->contract->agency->name }}</b> <span class="badge text-bg-dark">{{ $i->contract->agency->code }}</span></td></tr>
            @endif
            <tr><td><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td><td><span class="badge text-bg-light border">{{ $i->contract->agency->code }}</span></td><td>{{ $i->contract->client->name }}</td><td class="mono">{{ $i->contract->bid_no }}</td><td>{{ $i->project->name }}</td><td>{{ $i->month->format('M Y') }}</td><td class="num">{{ inr($i->rounded_total) }}</td><td><x-status :value="$i->status" /></td></tr>
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
