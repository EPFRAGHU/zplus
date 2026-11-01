<x-layouts.app title="Clients" crumb="Masters / <span class='text-danger'>Clients</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('clients.create') }}"><i class="bi bi-plus-lg me-1"></i>Add client</a>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap">
    <input class="form-control flex-grow-1 w-auto" name="q" value="{{ request('q') }}" placeholder="Search by name or GSTIN" aria-label="Search">
    <select class="form-select w-auto" name="agency" aria-label="Agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(request('agency') == $a->id)>{{ $a->name }}</option>@endforeach</select>
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Client</th><th>Agency</th><th>Bill code</th><th>Contact</th><th>District</th><th>GSTIN</th><th class="num">Term</th><th>GeM contracts</th><th class="num">Employees</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($clients as $c)
      <tr><td class="fw-semibold">{{ $c->name }}</td>
        <td>@if($c->agency)<span class="badge text-bg-light border" title="{{ $c->agency->name }}">{{ $c->agency->code }}</span>@else<span class="badge text-bg-warning">No agency</span>@endif</td>
        <td class="mono">{{ $c->short_code }}</td><td>{{ $c->contact_person ?? '—' }}<div class="small text-secondary">{{ $c->phone }}</div></td><td>{{ $c->district ?? '—' }}</td>
        <td class="mono">{{ $c->gstin ?? '—' }}</td><td class="num">{{ $c->payment_term_days }} d</td><td>
          @forelse($c->contracts as $k)
            <div class="text-nowrap"><a class="mono text-decoration-none" href="{{ route('contracts.show', $k) }}">{{ $k->bid_no }}</a>
              <span class="small text-secondary">· {{ $k->projects_count }} {{ \Illuminate\Support\Str::plural('project', $k->projects_count) }}</span></div>
          @empty
            <span class="text-secondary small">No contracts yet</span>
          @endforelse
          @if($c->contracts_count)<a class="small" href="{{ route('contracts.index', ['agency' => $c->agency_id, 'client' => $c->id]) }}">View all {{ $c->contracts_count }}</a>@endif
        </td>
        <td class="num"><a href="{{ route('employees.index', ['agency' => $c->agency_id, 'client' => $c->id]) }}">{{ $c->employees_count }}</a></td>
        <td><x-status :value="$c->status" /></td>
        <td class="text-end text-nowrap">@can('manage-masters')
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.create', ['copy' => $c->id]) }}" title="Add this client again under the other agency" aria-label="Add {{ $c->name }} for the other agency"><i class="bi bi-copy"></i></a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.edit', $c) }}">Edit</a>@endcan</td></tr>
    @empty
      <tr><td colspan="11" class="text-center text-secondary py-4">No clients found.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $clients->links() }}</div>
</x-layouts.app>
