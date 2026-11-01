<x-layouts.app title="Clients" crumb="Masters / <span class='text-danger'>Clients</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('clients.create') }}"><i class="bi bi-plus-lg me-1"></i>Add client</a>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by name or GSTIN" aria-label="Search"><button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Client</th><th>Bill code</th><th>Contact</th><th>District</th><th>GSTIN</th><th class="num">Term</th><th class="num">Contracts</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($clients as $c)
      <tr><td class="fw-semibold">{{ $c->name }}</td><td class="mono">{{ $c->short_code }}</td><td>{{ $c->contact_person ?? '—' }}<div class="small text-secondary">{{ $c->phone }}</div></td><td>{{ $c->district ?? '—' }}</td>
        <td class="mono">{{ $c->gstin ?? '—' }}</td><td class="num">{{ $c->payment_term_days }} d</td><td class="num">{{ $c->contracts_count }}</td><td><x-status :value="$c->status" /></td>
        <td class="text-end">@can('manage-masters')<a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.edit', $c) }}">Edit</a>@endcan</td></tr>
    @empty
      <tr><td colspan="9" class="text-center text-secondary py-4">No clients found.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $clients->links() }}</div>
</x-layouts.app>
