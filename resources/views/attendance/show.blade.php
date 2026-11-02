@php($locked = $sheet->isLocked())
<x-layouts.app :title="$sheet->project->name.' · '.$sheet->month->format('F Y')" :crumb="'Operations / <a href=\''.route('attendance.index', ['month' => $sheet->month->format('Y-m')]).'\'>Attendance</a>'">
  <x-slot:actions>
    <a class="btn btn-outline-secondary" href="{{ route('attendance.certificate', $sheet) }}" target="_blank"><i class="bi bi-printer me-1"></i>Print certificate for PI</a>
    @if($locked)
      @can('view-bank-details')
        <a class="btn btn-outline-secondary" href="{{ route('attendance.payouts', $sheet) }}" target="_blank"><i class="bi bi-cash-stack me-1"></i>Salary payout sheet</a>
      @endcan
    @endif
    @if($sheet->certificate_path)<a class="btn btn-outline-secondary" href="{{ route('attendance.scan', $sheet) }}" target="_blank"><i class="bi bi-file-earmark-check me-1"></i>Signed scan</a>@endif
  </x-slot:actions>

  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Status: <x-status :value="$sheet->status" /></span>
    <span>Agency: <b>{{ $sheet->project->contract->agency->name }}</b></span>
    <span>Client: <b>{{ $sheet->project->contract->client->name }}</b></span>
    <span>PI: <b>{{ $sheet->project->pi_name ?? '—' }}</b></span>
    <span>Contract: <span class="mono">{{ $sheet->project->contract->bid_no }}</span></span>
    @if($sheet->invoice)<span>Bill: <a class="mono" href="{{ route('invoices.show', $sheet->invoice) }}">{{ $sheet->invoice->invoice_no }}</a></span>@endif
  </div>

  @if($sheet->isSubmitted() || $sheet->submitted_at)
  <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center">
    <div><i class="bi bi-person-check me-1"></i><b>Submitted by the PI</b> ({{ $sheet->submitter?->name ?? 'PI' }}) on {{ $sheet->submitted_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}.
      @if($sheet->isSubmitted()) Check it against the signed copy, correct any day the PI changed by hand, then approve below. @endif</div>
    <div class="ms-auto d-flex gap-2">
      @if($sheet->submitted_pdf_path)<a class="btn btn-sm btn-outline-secondary" href="{{ route('attendance.submitted-pdf', $sheet) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF as submitted</a>@endif
      @if($sheet->certificate_path)<a class="btn btn-sm btn-outline-secondary" href="{{ route('attendance.scan', $sheet) }}" target="_blank"><i class="bi bi-file-earmark-check me-1"></i>Signed copy</a>@endif
    </div>
  </div>
  @endif
  @if($sheet->changes->count())
  <div class="z-card mb-3">
    <div class="z-card-h"><h2>Changed by the agency after the PI submitted</h2><span class="small text-secondary">{{ $sheet->changes->count() }} {{ \Illuminate\Support\Str::plural('change', $sheet->changes->count()) }}; shown on the certificate</span></div>
    <div class="table-responsive"><table class="table table-sm">
      <thead><tr><th>Staff</th><th>Day</th><th>Was</th><th>Now</th><th>Changed by</th><th>When</th></tr></thead>
      <tbody>@foreach($sheet->changes as $c)<tr><td>{{ $c->employee_name }}</td><td>{{ $c->day }} {{ $sheet->month->format('M') }}</td><td class="mono">{{ $c->old_code }}</td><td class="mono fw-bold">{{ $c->new_code }}</td><td>{{ $c->user?->name ?? '—' }}</td><td class="small">{{ $c->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td></tr>@endforeach</tbody>
    </table></div>
  </div>
  @endif

  @include('attendance._grid', ['editable' => ! $locked, 'action' => route('attendance.update', $sheet)])

  @if(! $locked)
  <form method="post" action="{{ route('attendance.lock', $sheet) }}" enctype="multipart/form-data" class="z-card p-3 d-flex gap-2 align-items-end flex-wrap">
    @csrf
    <div class="flex-grow-1"><label class="form-label small text-secondary mb-1" for="cert">Signed certificate from the PI (PDF or photo, up to 10 MB){{ $sheet->certificate_path ? ' · already uploaded; choose a file only to replace it' : '' }}</label>
      <input id="cert" type="file" name="certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @required(! $sheet->certificate_path)></div>
    <button class="btn btn-primary" onclick="return confirm('{{ $sheet->isSubmitted() ? 'Approve' : 'Lock' }} {{ $sheet->month->format('F') }}? Save any changes first. The sub-bill will be made automatically.')"><i class="bi bi-lock me-1"></i>{{ $sheet->isSubmitted() ? 'Approve and lock' : 'Lock month' }}</button>
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
