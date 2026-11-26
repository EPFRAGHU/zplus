<x-layouts.app title="Clients" crumb="Masters / <span class='text-danger'>Clients</span>" :quiet-title="true">
  {{-- Work Orders style (owner, 26 Nov 2026): heading, figure boxes, agency tabs and search; the table below is unchanged. --}}
  @php($tabUrl = fn ($id) => route('clients.index', array_filter(['agency' => $id, 'q' => request('q')])))
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-bank"></i></div>
    <div class="me-auto"><h1>Clients</h1>
      <p>Each client with its work orders, projects and posts. A client served by both agencies is added once under each agency.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap">
      @can('merge-clients')<a class="btn btn-outline-danger" href="{{ route('clients.merge') }}"><i class="bi bi-intersect me-1"></i>Merge into one client</a>@endcan
      @can('manage-masters')<a class="btn wo-btn-line" href="{{ route('clients.create') }}"><i class="bi bi-plus-lg me-1"></i>Add client</a>
        <a class="btn wo-btn-primary" href="{{ route('contracts.create') }}"><i class="bi bi-ui-checks me-1"></i>Add work order, projects and staff</a>@endcan
    </div>
  </div>

  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-bank"></i><div><div class="l">Clients</div><div class="v">{{ $kpi['clients'] }}</div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-file-earmark-text"></i><div><div class="l">Work orders</div><div class="v">{{ $kpi['contracts'] }}</div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-check2-circle"></i><div><div class="l">Active work orders</div><div class="v">{{ $kpi['active'] }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-people"></i><div><div class="l">Employees deployed</div><div class="v">{{ $kpi['employees'] }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-diagram-3"></i><div><div class="l">Posts sanctioned</div><div class="v">{{ $kpi['sanctioned'] }}</div></div></div>
  </div>

  <form class="wo-bar" method="get" action="{{ route('clients.index') }}">
    @if($agencies->count() > 1)
      <nav class="wo-tabs" aria-label="Agency">
        <a href="{{ $tabUrl(null) }}" @class(['on' => ! request('agency')])>All agencies</a>
        @foreach($agencies as $a)<a href="{{ $tabUrl($a->id) }}" @class(['on' => request('agency') == $a->id])>{{ $a->name }} ({{ $blockTotals[$a->id]->clients_count ?? 0 }})</a>@endforeach
      </nav>
      @if(request('agency'))<input type="hidden" name="agency" value="{{ request('agency') }}">@endif
    @endif
    <div class="wo-search"><i class="bi bi-search"></i><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by name or GSTIN" aria-label="Search"></div>
    <button class="btn wo-btn-primary">Show</button>
    @if(request()->hasAny(['q', 'agency']))<a class="btn btn-link px-1" href="{{ route('clients.index') }}">Clear</a>@endif
  </form>
  <div class="wo-card"><div class="table-responsive"><table class="table table-hover align-middle wo-table m-0">
    <thead><tr><th>Work order</th><th>Contract no.</th><th>Billing</th><th class="num">Projects</th><th class="num">Posts filled / sanctioned</th><th>Status</th><th></th></tr></thead>
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
        :meta="$c->contracts_count.' '.\Illuminate\Support\Str::plural('contract', $c->contracts_count).' · '.$c->employees_count.' '.\Illuminate\Support\Str::plural('employee', $c->employees_count).($c->contact_person ? ' · '.$c->contact_person : '').($c->phone ? ' '.$c->phone : '').($c->district ? ' · '.$c->district : '').($c->gstin ? ' · GSTIN '.$c->gstin : '').' · '.$c->payment_term_days.'-day term'">
        <x-status :value="$c->status" />
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.index', ['agency' => $c->agency_id, 'client' => $c->id]) }}" title="Employees of this client">{{ $c->employees_count }} <i class="bi bi-people"></i></a>
        @can('manage-masters')
          <a class="btn btn-sm btn-outline-primary" href="{{ route('contracts.create', ['agency' => $c->agency_id, 'client' => $c->id]) }}"><i class="bi bi-plus-lg"></i> Contract</a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.create', ['copy' => $c->id]) }}" title="Add this client again under the other agency" aria-label="Add {{ $c->name }} for the other agency"><i class="bi bi-copy"></i></a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.edit', $c) }}">Edit</a>
          @can('show-delete')<x-delete-button :action="route('clients.destroy', $c)" :blocked="\App\Support\SafeDelete::client($c)" :confirm="'Delete client '.$c->name.'? It has no work orders, staff or tax invoices.'" />@endcan
        @endcan
      </x-client-block>
      @forelse($c->contracts as $k)
        @php($kPosts = $k->projects->flatMap->posts)
        <tr>
          <td class="ps-5"><a class="mono text-decoration-none" href="{{ route('contracts.show', $k) }}">{{ $k->contract_no }}</a>@if($k->bid_no)<div class="small text-secondary">Bid {{ $k->bid_no }}</div>@endif</td>
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
  </div>
</x-layouts.app>
