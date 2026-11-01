<x-layouts.app title="Attendance" crumb="Operations / <span class='text-danger'>Attendance</span>">
  <form class="z-card p-3 mb-3 d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small text-secondary mb-1" for="m">Month</label><input id="m" type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control"></div>
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Show</button>
    <div class="small text-secondary w-100">Print the certificate, get it signed by the PI, upload the scan, then lock the month. Bills use locked months only.</div>
  </form>
  <div class="z-card">
    <div class="z-card-h"><h2>{{ $month->format('F Y') }}</h2><span class="small text-secondary">{{ $sheets->where('status', 'locked')->count() }} of {{ $projects->count() }} projects locked</span></div>
    <div class="table-responsive"><table class="table align-middle">
      <thead><tr><th>Agency</th><th>Client</th><th>Project</th><th>PI</th><th>GeM contract</th><th class="num">Staff</th><th>Status</th><th style="min-width:330px"></th></tr></thead>
      <tbody>
      @forelse($projects as $p)
        @php($sheet = $sheets[$p->id] ?? null)
        <tr>
          <td><span class="badge text-bg-light border" title="{{ $p->contract->agency->name }}">{{ $p->contract->agency->code }}</span></td>
          <td>{{ $p->contract->client->name }}</td>
          <td class="fw-semibold">{{ $p->name }}</td><td>{{ $p->pi_name ?? '—' }}</td><td class="mono">{{ $p->contract->bid_no }}</td><td class="num">{{ $sheet?->entries_count ?? $p->employees_count }}</td>
          <td>@if(! $sheet)<span class="badge text-bg-light border">Not started</span>@elseif($sheet->invoice)<span class="badge text-bg-success">Billed</span>@else<x-status :value="$sheet->status" />@endif</td>
          <td>
            @if($sheet)
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('attendance.show', $sheet) }}">Open sheet</a>
            @else
              <form method="post" action="{{ route('attendance.open') }}" class="d-flex gap-2">@csrf
                <input type="hidden" name="project_id" value="{{ $p->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input name="holidays" class="form-control form-control-sm" placeholder="Holidays, e.g. 2, 19, 20" aria-label="Holiday dates for {{ $p->name }}" style="max-width:190px">
                <button class="btn btn-sm btn-primary text-nowrap" @disabled($p->employees_count === 0) title="{{ $p->employees_count === 0 ? 'No employees on this project yet' : '' }}">Start sheet</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">No projects match. Change the filters, or add GeM contracts first.</td></tr>
      @endforelse
      </tbody></table></div>
  </div>
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
