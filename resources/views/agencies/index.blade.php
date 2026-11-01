<x-layouts.app title="Agencies" crumb="Masters / <span class='text-danger'>Agencies</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('agencies.create') }}"><i class="bi bi-plus-lg me-1"></i>Add agency</a>@endcan</x-slot:actions>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Agency</th><th>Code</th><th>Clients</th><th class="num">Contracts</th><th class="num">Employees</th><th>GSTIN</th><th>Signatory</th><th></th></tr></thead>
    <tbody>
    @forelse ($agencies as $a)
      <tr>
        <td class="fw-semibold">{{ $a->name }}</td><td class="mono">{{ $a->code }}</td>
        <td>
          @forelse($a->clients as $c)
            <div><a href="{{ route('contracts.index', ['agency' => $a->id, 'client' => $c->id]) }}" class="text-decoration-none">{{ $c->name }}</a>
              <span class="small text-secondary">· {{ $c->contracts_count }} {{ \Illuminate\Support\Str::plural('contract', $c->contracts_count) }}</span></div>
          @empty
            <span class="text-secondary">No clients yet</span>
          @endforelse
          @can('manage-masters')<a class="small" href="{{ route('clients.create', ['agency' => $a->id]) }}"><i class="bi bi-plus"></i>Add client</a>@endcan
        </td>
        <td class="num">{{ $a->contracts_count }}</td>
        <td class="num"><a href="{{ route('employees.index', ['agency' => $a->id]) }}">{{ $a->employees_count }}</a></td>
        <td class="mono">{{ $a->gstin ?? '—' }}</td><td>{{ $a->signatory_name ?? '—' }}</td>
        <td class="text-end">@can('manage-masters')<a class="btn btn-sm btn-outline-secondary" href="{{ route('agencies.edit', $a) }}">Edit</a>@endcan</td></tr>
    @empty
      <tr><td colspan="8" class="text-center text-secondary py-4">No agencies yet. Add SWACHHA and Z Plus Surakhya Seva, or import your GeM workbook.</td></tr>
    @endforelse
    </tbody></table></div></div>
</x-layouts.app>
