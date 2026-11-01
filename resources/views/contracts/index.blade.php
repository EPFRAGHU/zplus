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
  <form class="z-card p-3 mb-3 row g-2 mx-0">
    <div class="col-md-6"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Bid no., contract no., project or PI" aria-label="Search"></div>
    <div class="col-md-4"><select class="form-select" name="agency" aria-label="Agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(request('agency') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary">Filter</button></div>
  </form>
  @forelse ($contracts as $c)
    @php($posts = $c->projects->flatMap->posts) @php($s = $posts->sum('sanctioned_qty')) @php($f = $posts->sum('active_employees_count'))
    <div class="z-card mb-2">
      <div class="z-card-h">
        <span class="badge text-bg-light border">{{ $c->agency->code }}</span>
        <div><a class="fw-bold text-decoration-none" href="{{ route('contracts.show', $c) }}">{{ $c->bid_no }}</a><div class="mono text-secondary">{{ $c->contract_no }}</div></div>
        <div class="small"><div class="fw-semibold">{{ $c->client->name }}</div><div class="text-secondary">{{ $c->projects->pluck('name')->join(' · ') }}</div></div>
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
</x-layouts.app>
