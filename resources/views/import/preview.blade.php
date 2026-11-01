<x-layouts.app title="Check before importing" :crumb="'Setup / <a href=\''.route('import.create').'\'>Excel Import</a>'">
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-3">
    <x-kpi label="GeM contracts" :value="$summary['contracts']" />
    <x-kpi label="Projects" :value="$summary['projects']" color="#1f63c4" />
    <x-kpi label="Posts" :value="$summary['posts']" color="#1d1a1c" />
    <x-kpi label="Sanctioned" :value="$summary['sanctioned']" color="#1d1a1c" />
    <x-kpi label="Staff found" :value="$summary['staff']" color="#1f8a4c" />
    <x-kpi label="Rows to fix" :value="$summary['must_fix']" color="#c42b3b" />
  </div>
  <div class="z-card mb-3">
    <div class="z-card-h"><h2>Problems in {{ $fileName }}</h2><span class="small text-secondary">You can fix these in the app after importing</span></div>
    <div class="accordion accordion-flush" id="issues">
      @forelse($plan->issues as $i => $issue)
        @php($badge = ['bad' => ['danger', 'Must fix'], 'warn' => ['warning', 'Check'], 'info' => ['info', 'FYI']][$issue['level']])
        <div class="accordion-item">
          <h3 class="accordion-header"><button class="accordion-button collapsed gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#i{{ $i }}">
            <span class="badge text-bg-{{ $badge[0] }}">{{ $badge[1] }}</span> {{ $issue['title'] }}</button></h3>
          <div id="i{{ $i }}" class="accordion-collapse collapse" data-bs-parent="#issues"><div class="accordion-body small"><ul class="mb-0">@foreach($issue['details'] as $d)<li>{{ $d }}</li>@endforeach</ul></div></div>
        </div>
      @empty
        <div class="p-3 text-success">No problems found.</div>
      @endforelse
    </div>
  </div>
  <div class="z-card mb-3">
    <div class="z-card-h"><h2>Contracts in this file</h2></div>
    <div class="table-responsive"><table class="table table-sm">
      <thead><tr><th>Sheet</th><th>Bid no.</th><th>Contract no.</th><th>Billing</th><th>Projects</th></tr></thead>
      <tbody>@foreach($plan->contracts as $c)<tr><td>{{ $c['sheet'] }}</td><td class="mono">{{ $c['bid_no'] }}</td><td class="mono">{{ $c['contract_no'] }}</td><td>{{ ucfirst($c['billing_type']) }}</td><td>{{ implode(', ', array_keys($c['projects'])) }}</td></tr>@endforeach</tbody>
    </table></div>
  </div>
  <form method="post" action="{{ route('import.commit') }}" class="z-card p-3 d-flex gap-2 align-items-center flex-wrap">
    @csrf
    <span class="small text-secondary">Contracts already in the app are skipped. Bank account numbers are stored encrypted; invalid IFSC codes are left blank for you to fill in.</span>
    <a class="btn btn-outline-secondary ms-auto" href="{{ route('import.create') }}">Choose another file</a>
    <button class="btn btn-primary">Import into {{ $client->name }}</button>
  </form>
</x-layouts.app>
