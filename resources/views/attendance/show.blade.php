@php($locked = $sheet->isLocked()) @php($days = $sheet->month->daysInMonth)
<x-layouts.app :title="$sheet->project->name.' · '.$sheet->month->format('F Y')" :crumb="'Operations / <a href=\''.route('attendance.index', ['month' => $sheet->month->format('Y-m')]).'\'>Attendance</a>'">
  <x-slot:actions>
    <a class="btn btn-outline-secondary" href="{{ route('attendance.certificate', $sheet) }}" target="_blank"><i class="bi bi-printer me-1"></i>Print certificate for PI</a>
    @if($sheet->certificate_path)<a class="btn btn-outline-secondary" href="{{ route('attendance.scan', $sheet) }}" target="_blank"><i class="bi bi-file-earmark-check me-1"></i>Signed scan</a>@endif
  </x-slot:actions>

  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Status: <x-status :value="$sheet->status" /></span>
    <span>PI: <b>{{ $sheet->project->pi_name ?? '—' }}</b></span>
    <span>Contract: <span class="mono">{{ $sheet->project->contract->bid_no }}</span></span>
    @if($sheet->invoice)<span>Bill: <a class="mono" href="{{ route('invoices.show', $sheet->invoice) }}">{{ $sheet->invoice->invoice_no }}</a></span>@endif
  </div>

  <form method="post" action="{{ route('attendance.update', $sheet) }}">
    @csrf @method('put')
    <div class="z-card mb-3">
      <div class="z-card-h"><h2>Daily codes</h2>
        <div class="small text-secondary">@foreach($codes as $k => $v)<span class="me-2"><b class="mono">{{ $k }}</b> {{ $v }}</span>@endforeach</div></div>
      <div class="table-responsive"><table class="table table-sm att-grid mb-0">
        <thead><tr><th>Staff</th>
          @for($d = 1; $d <= $days; $d++)<th>{{ $d }}<div class="fw-normal text-secondary">{{ substr($sheet->month->setDay($d)->format('D'), 0, 2) }}</div></th>@endfor
          <th class="num">Paid days</th></tr></thead>
        <tbody>
        @foreach($sheet->entries->sortBy('employee.name') as $entry)
          <tr><td><div class="fw-semibold">{{ $entry->employee->name }}</div><div class="small text-secondary">{{ $entry->employee->post?->designation }}</div></td>
            @for($d = 1; $d <= $days; $d++)
              @php($c = $entry->codes[$d] ?? $entry->codes[(string) $d] ?? 'A')
              <td class="c-{{ $c }}">@if($locked)<span class="mono small">{{ $c }}</span>@else
                <select name="codes[{{ $entry->id }}][{{ $d }}]" class="form-select c-{{ $c }}" aria-label="{{ $entry->employee->name }}, day {{ $d }}" onchange="this.className='form-select c-'+this.value;this.parentNode.className='c-'+this.value">
                  @foreach(array_keys($codes) as $k)<option @selected($k === $c)>{{ $k }}</option>@endforeach
                </select>@endif</td>
            @endfor
            <td class="num fw-bold">{{ rtrim(rtrim((string) $entry->paid_days, '0'), '.') }}</td></tr>
        @endforeach
        </tbody></table></div>
      <div class="d-flex gap-2 justify-content-between align-items-center p-3 border-top flex-wrap">
        <span class="small text-secondary">Paid days count every code except A and L. HD counts as half a day. Days before joining or after exit start as A.</span>
        @unless($locked)<button class="btn btn-outline-primary">Save attendance</button>@endunless
      </div>
    </div>
  </form>

  @if(! $locked)
  <form method="post" action="{{ route('attendance.lock', $sheet) }}" enctype="multipart/form-data" class="z-card p-3 d-flex gap-2 align-items-end flex-wrap">
    @csrf
    <div class="flex-grow-1"><label class="form-label small text-secondary mb-1" for="cert">Signed certificate from the PI (PDF or photo, up to 10 MB)</label>
      <input id="cert" type="file" name="certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @required(! $sheet->certificate_path)></div>
    <button class="btn btn-primary" onclick="return confirm('Lock {{ $sheet->month->format('F') }}? Save any changes first.')"><i class="bi bi-lock me-1"></i>Lock month</button>
  </form>
  @else
  <div class="z-card p-3 d-flex gap-2 align-items-center flex-wrap">
    <span><i class="bi bi-lock-fill text-success me-1"></i>Locked {{ $sheet->locked_at?->format('d M Y, H:i') }}</span>
    @if(! $sheet->invoice)
      @can('billing')<form method="post" action="{{ route('invoices.store') }}" class="ms-auto">@csrf<input type="hidden" name="attendance_sheet_id" value="{{ $sheet->id }}"><button class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Make bill</button></form>@endcan
      @can('unlock-attendance')<form method="post" action="{{ route('attendance.unlock', $sheet) }}">@csrf<button class="btn btn-outline-secondary">Unlock</button></form>@endcan
    @endif
  </div>
  @endif
</x-layouts.app>
