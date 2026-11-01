<x-layouts.app title="GeM Contracts" crumb="Masters / <span class='text-danger'>GeM Contracts</span>">
  <x-slot:actions>
    @can('import')<a class="btn btn-outline-secondary" href="{{ route('import.create') }}"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import from Excel</a>@endcan
    @can('manage-masters')<a class="btn btn-primary" href="{{ route('contracts.create') }}"><i class="bi bi-plus-lg me-1"></i>Add contract</a>@endcan
  </x-slot:actions>
  @php
    $all = $contracts->flatMap(fn ($c) => $c->projects->flatMap->posts);
    $sanctioned = $all->sum('sanctioned_qty'); $filled = $all->sum('active_employees_count');
  @endphp
  <div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    <x-kpi label="Contracts" :value="$contracts->count()" />
    <x-kpi label="Projects" :value="$contracts->sum(fn ($c) => $c->projects->count())" color="#1f63c4" />
    <x-kpi label="Posts sanctioned" :value="$sanctioned" color="#1d1a1c" />
    <x-kpi label="Posts filled" :value="$filled" :meta="max(0, $sanctioned - $filled).' vacant'" color="#1f8a4c" />
  </div>
  <form class="z-card p-3 mb-3 row g-2 mx-0 align-items-end">
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" class="form-select" name="agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" class="form-select" name="client"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label small text-secondary mb-1" for="f-q">Search</label><input id="f-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Bid no., contract no., project or PI"></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-secondary flex-fill">Show</button>@if(request()->query())<a class="btn btn-link px-1" href="{{ route('contracts.index') }}">Clear</a>@endif</div>
  </form>
  @php($group = null)
  @forelse ($contracts as $c)
    @if($group !== $c->client_id)
      @php($group = $c->client_id)
      @php($inGroup = $contracts->where('client_id', $c->client_id))
      <div class="d-flex align-items-center gap-2 flex-wrap mt-3 mb-2 px-1">
        <b>{{ $c->agency->name }}</b> <span class="badge text-bg-light border">{{ $c->agency->code }}</span>
        <span class="text-secondary">·</span> <b>{{ $c->client->name }}</b>
        <span class="small text-secondary">{{ $inGroup->count() }} {{ \Illuminate\Support\Str::plural('contract', $inGroup->count()) }}</span>
      </div>
    @endif
    @php($posts = $c->projects->flatMap->posts) @php($s = $posts->sum('sanctioned_qty')) @php($f = $posts->sum('active_employees_count'))
    <div class="z-card mb-2">
      <div class="z-card-h">
        <div><a class="fw-bold text-decoration-none" href="{{ route('contracts.show', $c) }}">{{ $c->bid_no }}</a><div class="mono text-secondary">{{ $c->contract_no }}</div></div>
        <div class="small">
          <div><span class="text-secondary">Agency:</span> <span class="fw-semibold">{{ $c->agency->name }}</span> ({{ $c->agency->code }})</div>
          <div><span class="text-secondary">Client:</span> <span class="fw-semibold">{{ $c->client->name }}</span></div>
          <div class="text-secondary">{{ $c->projects->pluck('name')->join(' · ') }}</div>
        </div>
        <div class="ms-auto d-flex gap-2 align-items-center">
          @if($c->isDaily())<span class="badge text-bg-light border">Daily rate × days + EPF/ESIC</span>@endif
          <span class="badge rounded-pill text-bg-{{ $f < $s ? 'warning' : 'success' }}">{{ $f }}/{{ $s }} filled</span>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('contracts.show', $c) }}">Open</a>
        </div>
      </div>
    </div>
  @empty
    <div class="z-card p-4 text-center text-secondary">No contracts yet. @can('import')<a href="{{ route('import.create') }}">Import your GeM workbook</a> or add one by hand.@endcan</div>
  @endforelse
  <script>
    (function () {
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client');
      function update() {
        [...client.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !!agency.value && o.dataset.agency !== agency.value; } });
        if (client.selectedOptions[0] && client.selectedOptions[0].disabled) client.value = '';
      }
      agency.addEventListener('change', update); update();
    })();
  </script>
</x-layouts.app>
