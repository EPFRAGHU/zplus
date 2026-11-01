<x-layouts.app title="Clients" crumb="Masters / <span class='text-danger'>Clients</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('clients.create') }}"><i class="bi bi-plus-lg me-1"></i>Add client</a>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap">
    <input class="form-control flex-grow-1 w-auto" name="q" value="{{ request('q') }}" placeholder="Search by name or GSTIN" aria-label="Search">
    <select class="form-select w-auto" name="agency" aria-label="Agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(request('agency') == $a->id)>{{ $a->name }}</option>@endforeach</select>
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button></form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>GeM contract</th><th>Contract no.</th><th>Billing</th><th class="num">Projects</th><th class="num">Posts filled / sanctioned</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @php($block = false)
    @forelse ($clients as $c)
      @if($block === false || $block !== $c->agency_id)
        @php($block = $c->agency_id)
        @php($t = $blockTotals[$c->agency_id] ?? null)
        <tr class="table-secondary"><td colspan="7" class="py-2">
          @if($c->agency)
            <b class="fs-6">{{ $c->agency->name }}</b> <span class="badge text-bg-dark">{{ $c->agency->code }}</span>
            <span class="small text-secondary ms-2">{{ $t->clients_count }} {{ \Illuminate\Support\Str::plural('client', $t->clients_count) }} · {{ $t->contracts_count }} {{ \Illuminate\Support\Str::plural('contract', $t->contracts_count) }} · {{ $t->employees_count }} {{ \Illuminate\Support\Str::plural('employee', $t->employees_count) }}</span>
            @can('manage-masters')<a class="small ms-2" href="{{ route('clients.create', ['agency' => $c->agency_id]) }}"><i class="bi bi-plus"></i>Add client to {{ $c->agency->code }}</a>@endcan
          @else
            <b class="fs-6">No agency set</b>
          @endif
        </td></tr>
      @endif
      <x-client-block :client="$c" colspan="7"
        :meta="'Bill code '.$c->short_code.' · '.$c->contracts_count.' '.\Illuminate\Support\Str::plural('contract', $c->contracts_count).' · '.$c->employees_count.' '.\Illuminate\Support\Str::plural('employee', $c->employees_count).($c->contact_person ? ' · '.$c->contact_person : '').($c->phone ? ' '.$c->phone : '').($c->district ? ' · '.$c->district : '').($c->gstin ? ' · GSTIN '.$c->gstin : '').' · '.$c->payment_term_days.'-day term'">
        <x-status :value="$c->status" />
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.index', ['agency' => $c->agency_id, 'client' => $c->id]) }}" title="Employees of this client">{{ $c->employees_count }} <i class="bi bi-people"></i></a>
        @can('manage-masters')
          <a class="btn btn-sm btn-outline-primary" href="{{ route('contracts.create', ['agency' => $c->agency_id, 'client' => $c->id]) }}"><i class="bi bi-plus-lg"></i> Contract</a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.create', ['copy' => $c->id]) }}" title="Add this client again under the other agency" aria-label="Add {{ $c->name }} for the other agency"><i class="bi bi-copy"></i></a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.edit', $c) }}">Edit</a>
        @endcan
      </x-client-block>
      @forelse($c->contracts as $k)
        @php($kPosts = $k->projects->flatMap->posts)
        <tr>
          <td class="ps-5"><a class="mono text-decoration-none" href="{{ route('contracts.show', $k) }}">{{ $k->bid_no }}</a></td>
          <td class="mono small">{{ $k->contract_no }}</td>
          <td class="small">{{ $k->isDaily() ? 'Daily rate' : 'Monthly rate' }}</td>
          <td class="num">{{ $k->projects_count }}</td>
          <td class="num">{{ $kPosts->sum('active_employees_count') }} / {{ $kPosts->sum('sanctioned_qty') }}</td>
          <td><x-status :value="$k->status" /></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('contracts.show', $k) }}">Open</a></td>
        </tr>
      @empty
        <tr><td colspan="7" class="ps-5 small text-secondary">No contracts yet for this client.</td></tr>
      @endforelse
    @empty
      <tr><td colspan="7" class="text-center text-secondary py-4">No clients found.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $clients->links() }}</div>
</x-layouts.app>
