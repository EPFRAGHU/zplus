@php($locked = $sheet->isLocked())
<x-layouts.app :title="$sheet->project->name.' · '.$sheet->month->format('F Y')" :crumb="'Operations / <a href=\''.route('attendance.index', ['month' => $sheet->month->format('Y-m')]).'\'>Attendance</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-calendar-check"></i></div>
    <div class="me-auto"><h1>{{ $sheet->project->name }} <span class="text-secondary fw-normal">· {{ $sheet->month->format('F Y') }}</span></h1>
      <p>Agency: <b class="text-body">{{ $sheet->project->contract->agency->name }}</b> · Client: <b class="text-body">{{ $sheet->project->contract->client->name }}</b>
        · PI: <b class="text-body">{{ $sheet->project->pi_name ?? '—' }}</b> · work order <span class="mono">{{ $sheet->project->contract->contract_no }}</span></p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    <a class="btn btn-outline-secondary" href="{{ route('attendance.certificate', $sheet) }}" target="_blank"><i class="bi bi-printer me-1"></i>Print certificate for PI</a>
    @if($locked)
      @can('view-bank-details')
        @if(\App\Services\Approvals\Approvals::canApprove(auth()->user()) || app(\App\Services\Approvals\Approvals::class)->payoutClearedFor($sheet))
        <a class="btn btn-outline-secondary" href="{{ route('attendance.payouts', $sheet) }}" target="_blank"><i class="bi bi-cash-stack me-1"></i>Salary payout sheet</a>
        <a class="btn btn-outline-secondary" href="{{ route('attendance.bank-letter', $sheet) }}" target="_blank"><i class="bi bi-bank me-1"></i>Bank letter</a>
        @else
        <a class="btn btn-outline-secondary" href="{{ route('bank-letters.index', ['agency' => $sheet->project->contract->agency_id, 'month' => $sheet->month->format('Y-m')]) }}" title="The owner must approve the month's salary payout first"><i class="bi bi-lock me-1"></i>Payout: owner approval needed</a>
        @endif
      @endcan
    @endif
    @if($sheet->certificate_path)<a class="btn btn-outline-secondary" href="{{ route('attendance.scan', $sheet) }}" target="_blank"><i class="bi bi-file-earmark-check me-1"></i>Signed scan</a>@endif
    @can('reset-month')<button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#reset-month"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset this month</button>@endcan
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-people"></i><div><div class="l">Staff</div><div class="v">{{ $sheet->entries->count() }}</div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-flag"></i><div><div class="l">Status</div><div class="v"><span style="font-size:1rem"><x-status :value="$sheet->status" /></span></div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-person-check"></i><div><div class="l">Submitted by PI</div><div class="v"><span style="font-size:1rem">{{ $sheet->submitted_at ? $sheet->submitted_at->timezone('Asia/Kolkata')->format('d M Y') : 'Not yet' }}</span></div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-pencil-square"></i><div><div class="l">Changed after PI</div><div class="v">{{ $sheet->changes->count() }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-receipt"></i><div><div class="l">Sub-bill</div><div class="v"><span style="font-size:1rem">@if($sheet->invoice)<a class="mono" href="{{ route('invoices.show', $sheet->invoice) }}">{{ $sheet->invoice->invoice_no }}</a>@else Not made yet @endif</span></div></div></div>
  </div>

  @if($locked)
  @include('partials.approval-box', ['type' => 'attendance', 'subject' => $sheet, 'sendAction' => route('approvals.send.attendance', $sheet), 'canSend' => ! auth()->user()->isPi()])
  @endif
  @can('reset-month')
  @php($resetBill = $sheet->invoice)
  <div class="collapse mb-3 @if($errors->has('confirm') || $errors->has('reset')) show @endif" id="reset-month"><div class="alert alert-danger">
    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Reset {{ $sheet->project->name }} for {{ $sheet->month->format('F Y') }}</div>
    <div class="small">This deletes, for this project and month only:
      <ul class="mb-1"><li>the attendance of all {{ $sheet->entries->count() }} employees, the corrections and the uploaded PDFs</li>
        @if($resetBill)<li>sub-bill <b class="mono">{{ $resetBill->invoice_no }}</b> ({{ inr($resetBill->rounded_total) }}, {{ $resetBill->status }})@if($resetBill->taxInvoice) – it is taken out of tax invoice <b class="mono">{{ $resetBill->taxInvoice->number }}</b>@endif</li>@endif</ul>
      @if($resetBill && $resetBill->status === 'submitted')<div class="fw-semibold">This sub-bill was already submitted to the client. Tell them it is withdrawn.</div>@endif
      Paid bills are never reset. The month's bill number is kept for the next sub-bill. The reset is recorded in the Activity log and cannot be undone.</div>
    @error('reset')<div class="small fw-semibold mt-1">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('attendance.reset', $sheet) }}" class="d-flex gap-2 align-items-center mt-2 flex-wrap">@csrf
      <label class="small" for="reset-confirm">Type <b>RESET</b> to confirm</label>
      <input id="reset-confirm" name="confirm" class="form-control form-control-sm @error('confirm') is-invalid @enderror" style="max-width:140px" autocomplete="off" required>
      <button class="btn btn-sm btn-danger">Reset this month</button>
      @error('confirm')<div class="small text-danger w-100">{{ $message }}</div>@enderror
    </form>
  </div></div>
  @endcan

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
  </div>
</x-layouts.app>
