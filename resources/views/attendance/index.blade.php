<x-layouts.app title="Attendance" crumb="Operations / <span class='text-danger'>Attendance</span>">
  <form class="z-card p-3 mb-3 d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small text-secondary mb-1" for="m">Month</label><input id="m" type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control"></div>
    <button class="btn btn-outline-secondary">Show</button>
    <div class="small text-secondary ms-md-3">Print the certificate, get it signed by the PI, upload the scan, then lock the month. Bills use locked months only.</div>
  </form>
  <div class="z-card">
    <div class="z-card-h"><h2>{{ $month->format('F Y') }}</h2><span class="small text-secondary">{{ $sheets->where('status', 'locked')->count() }} of {{ $projects->count() }} projects locked</span></div>
    <div class="table-responsive"><table class="table align-middle">
      <thead><tr><th>Project</th><th>PI</th><th>GeM contract</th><th class="num">Staff</th><th>Status</th><th style="min-width:330px"></th></tr></thead>
      <tbody>
      @forelse($projects as $p)
        @php($sheet = $sheets[$p->id] ?? null)
        <tr>
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
        <tr><td colspan="6" class="text-center text-secondary py-4">No projects yet. Add GeM contracts first.</td></tr>
      @endforelse
      </tbody></table></div>
  </div>
</x-layouts.app>
