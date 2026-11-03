<x-layouts.app title="Agencies" crumb="Masters / <span class='text-danger'>Agencies</span>">
  <x-slot:actions>@can('add-agency')<a class="btn btn-primary" href="{{ route('agencies.create') }}"><i class="bi bi-plus-lg me-1"></i>Add agency</a>@endcan</x-slot:actions>
  @forelse ($agencies as $a)
    <div class="z-card mb-3">
      <div class="d-flex align-items-center gap-2 flex-wrap px-3 py-2 rounded-top bg-body-secondary border-bottom">
        <b class="fs-6">{{ $a->name }}</b> <span class="badge text-bg-dark">{{ $a->code }}</span>
        <span class="small text-secondary">{{ $a->clients_count }} {{ \Illuminate\Support\Str::plural('client', $a->clients_count) }} · {{ $a->contracts_count }} {{ \Illuminate\Support\Str::plural('contract', $a->contracts_count) }} ·
          <a href="{{ route('employees.index', ['agency' => $a->id]) }}">{{ $a->employees_count }} {{ \Illuminate\Support\Str::plural('employee', $a->employees_count) }}</a></span>
        <span class="small text-secondary">· GSTIN <span class="mono">{{ $a->gstin ?? '—' }}</span> · Signatory {{ $a->signatory_name ?? '—' }}</span>
        @can('manage-masters')
          <div class="ms-auto d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('clients.create', ['agency' => $a->id]) }}"><i class="bi bi-plus-lg"></i> Add client</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('agencies.edit', $a) }}">Edit</a>
          </div>
        @endcan
      </div>
      <div class="p-3 pb-1">
        @forelse($a->clients as $c)
          <x-client-block :client="$c" :meta="$c->contracts_count.' '.\Illuminate\Support\Str::plural('contract', $c->contracts_count).' · '.$c->employees_count.' '.\Illuminate\Support\Str::plural('employee', $c->employees_count)">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('contracts.index', ['agency' => $a->id, 'client' => $c->id]) }}">Contracts</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.index', ['agency' => $a->id, 'client' => $c->id]) }}">Employees</a>
          </x-client-block>
        @empty
          <p class="small text-secondary mb-2">No clients yet. Use <b>Add client</b> to add the first one.</p>
        @endforelse
      </div>
    </div>
  @empty
    <div class="z-card p-4 text-center text-secondary">No agencies yet. Add SWACHHA and Z Plus Surakhya Seva, or import your GeM workbook.</div>
  @endforelse
</x-layouts.app>
