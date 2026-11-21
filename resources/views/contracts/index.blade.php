<x-layouts.app title="Work Orders" crumb="Masters / <span class='text-danger'>Work Orders</span>">
  <x-slot:actions>
    @can('import')<a class="btn btn-outline-secondary" href="{{ route('import.create') }}"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import from Excel</a>@endcan
    @can('manage-masters')<a class="btn btn-primary" href="{{ route('contracts.create') }}"><i class="bi bi-plus-lg me-1"></i>Add work order</a>@endcan
  </x-slot:actions>
  @php
    $all = $contracts->flatMap(fn ($c) => $c->projects->flatMap->posts);
    $sanctioned = $all->sum('sanctioned_qty'); $filled = $all->sum('active_employees_count');
  @endphp
  <div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    <x-kpi label="Work orders" :value="$contracts->count()" />
    <x-kpi label="Projects" :value="$contracts->sum(fn ($c) => $c->projects->count())" color="#1f63c4" />
    <x-kpi label="Posts sanctioned" :value="$sanctioned" color="#1d1a1c" />
    <x-kpi label="Posts filled" :value="$filled" :meta="max(0, $sanctioned - $filled).' vacant'" color="#1f8a4c" />
  </div>
  <form class="z-card p-3 mb-3 row g-2 mx-0 align-items-end">
    <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" class="form-select" name="agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div class="col-md-7"><label class="form-label small text-secondary mb-1" for="f-q">Search</label><input id="f-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Bid no., contract no., project or PI"></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-secondary flex-fill">Show</button>@if(request()->query())<a class="btn btn-link px-1" href="{{ route('contracts.index') }}">Clear</a>@endif</div>
  </form>
  @php($agencyBlock = null) @php($group = null)
  @forelse ($contracts as $c)
    @if($agencyBlock !== $c->agency_id)
      @php($agencyBlock = $c->agency_id) @php($group = null)
      @php($inAgency = $contracts->where('agency_id', $c->agency_id))
      @php($aPosts = $inAgency->flatMap(fn ($k) => $k->projects->flatMap->posts))
      <div class="d-flex align-items-center gap-2 flex-wrap mt-4 mb-2 px-3 py-2 rounded bg-body-secondary border">
        <b class="fs-6">{{ $c->agency->name }}</b> <span class="badge text-bg-dark">{{ $c->agency->code }}</span>
        <span class="small text-secondary">{{ $inAgency->count() }} {{ \Illuminate\Support\Str::plural('contract', $inAgency->count()) }} · @php($nProj = $inAgency->sum(fn ($k) => $k->projects->count())){{ $nProj }} {{ \Illuminate\Support\Str::plural('project', $nProj) }} · {{ $aPosts->sum('active_employees_count') }} / {{ $aPosts->sum('sanctioned_qty') }} posts filled</span>
        @can('manage-masters')<a class="small ms-auto" href="{{ route('contracts.create', ['agency' => $c->agency_id]) }}"><i class="bi bi-plus"></i>Add contract</a>@endcan
      </div>
    @endif
    @php($posts = $c->projects->flatMap->posts) @php($s = $posts->sum('sanctioned_qty')) @php($f = $posts->sum('active_employees_count'))
    <div class="z-card mb-2">
      <div class="z-card-h">
        <div><a class="fw-bold text-decoration-none mono" href="{{ route('contracts.show', $c) }}">{{ $c->contract_no }}</a>
          <span class="badge {{ $c->contract_type === 'offline' ? 'text-bg-secondary' : 'text-bg-info' }} ms-1">{{ $c->typeLabel() }}</span>
          @include('contracts._expiry', ['c' => $c])
          <div class="mono text-secondary small">{{ $c->bid_no ? 'Bid '.$c->bid_no : '' }}</div></div>
        <div class="small">
          <div><span class="text-secondary">Agency:</span> <span class="fw-semibold">{{ $c->agency->name }}</span> ({{ $c->agency->code }})</div>

        </div>
        <div class="ms-auto d-flex gap-2 align-items-center">
          @if($c->isDaily())<span class="badge text-bg-light border">Daily rate × days + EPF/ESIC</span>@endif
          <span class="badge rounded-pill text-bg-{{ $f < $s ? 'warning' : 'success' }}">{{ $f }}/{{ $s }} filled</span>
          @can('manage-masters')<a class="btn btn-sm btn-outline-primary" href="{{ route('contracts.show', $c) }}#add-project" title="Add another project using this bid and contract number"><i class="bi bi-plus-lg"></i> Add project</a>@endcan
          @can('manage-masters')<a class="btn btn-sm btn-primary" href="{{ route('contracts.edit', $c) }}"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('contracts.show', $c) }}">Open</a>
        </div>
      </div>
      <div class="table-responsive"><table class="table table-sm mb-0 small">
        <thead><tr><th style="width:14%">Project</th><th>Project name</th><th>Project In-charge</th><th class="num">Posts filled</th><th></th></tr></thead>
        <tbody>
        @forelse($c->projects as $p)
          @php($pp = $p->posts)
          <tr><td><span class="badge text-bg-primary mono">{{ $p->code }}</span></td><td>{{ $p->name }}</td><td>{{ $p->incharge?->name ?? $p->pi_name ?? '—' }}</td>
            <td class="num">{{ $pp->sum('active_employees_count') }} / {{ $pp->sum('sanctioned_qty') }}</td>
            <td class="text-end">@can('manage-masters')<a class="btn btn-sm btn-link py-0" href="{{ route('contracts.show', $c) }}#edit-p{{ $p->id }}">Edit project</a>@endcan</td></tr>
        @empty
          <tr><td colspan="5" class="text-secondary">No projects yet. Use <b>Add project</b>.</td></tr>
        @endforelse
        </tbody></table></div>
    </div>
  @empty
    <div class="z-card p-4 text-center text-secondary">No contracts yet. @can('import')<a href="{{ route('import.create') }}">Import your GeM workbook</a> or add one by hand.@endcan</div>
  @endforelse
</x-layouts.app>
