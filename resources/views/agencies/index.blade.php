<x-layouts.app title="Agencies" crumb="Masters / <span class='text-danger'>Agencies</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('agencies.create') }}"><i class="bi bi-plus-lg me-1"></i>Add agency</a>@endcan</x-slot:actions>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Agency</th><th>Code</th><th>GSTIN</th><th>Signatory</th><th class="num">Clients</th><th class="num">Contracts</th><th></th></tr></thead>
    <tbody>
    @forelse ($agencies as $a)
      <tr><td class="fw-semibold">{{ $a->name }}</td><td class="mono">{{ $a->code }}</td><td class="mono">{{ $a->gstin ?? '—' }}</td><td>{{ $a->signatory_name ?? '—' }}</td><td class="num">{{ $a->clients_count }}</td><td class="num">{{ $a->contracts_count }}</td>
        <td class="text-end">@can('manage-masters')<a class="btn btn-sm btn-outline-secondary" href="{{ route('agencies.edit', $a) }}">Edit</a>@endcan</td></tr>
    @empty
      <tr><td colspan="7" class="text-center text-secondary py-4">No agencies yet. Add SWACHHA and Z Plus Surakhya Seva, or import your GeM workbook.</td></tr>
    @endforelse
    </tbody></table></div></div>
</x-layouts.app>
