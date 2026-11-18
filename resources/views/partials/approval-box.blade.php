{{-- Owner approval (maker–checker) box. $type, $subject (model or ['agency'=>, 'month'=>]), $sendAction (route for "Send for approval"), $canSend. --}}
@php($ap = app(\App\Services\Approvals\Approvals::class))
@php($state = $ap->state($type, $subject))
@if($state !== 'not_needed')
@php($history = $ap->history($type, $subject))
@php($latest = $history->first())
@php($isOwner = \App\Services\Approvals\Approvals::canApprove(auth()->user()))
<div class="z-card p-3 mb-3 border-start border-4 {{ ['approved' => 'border-success', 'pending' => 'border-info', 'returned' => 'border-danger', 'changed' => 'border-warning', 'not_sent' => 'border-warning'][$state] }}" id="approval">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <h2 class="h6 fw-bold mb-0"><i class="bi bi-person-check me-1"></i>Owner approval</h2>
    @switch($state)
      @case('approved')<span class="z-stamp z-stamp-approved">Approved</span><span class="small text-secondary">by {{ $latest->decider?->name }}, {{ $latest->decided_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>@break
      @case('pending')<span class="z-stamp z-stamp-pending">Waiting for the owner</span><span class="small text-secondary">sent by {{ $latest->submitter?->name }}, {{ $latest->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>@break
      @case('returned')<span class="z-stamp z-stamp-returned">Returned</span><span class="small">by {{ $latest->decider?->name }}: <b>{{ $latest->reason }}</b></span>@break
      @case('changed')<span class="z-stamp z-stamp-returned">Changed after approval – send again</span>@break
      @default<span class="z-stamp z-stamp-muted">Not sent yet</span>
    @endswitch
    @if($canSend && in_array($state, ['not_sent', 'returned', 'changed'], true))
      <form method="post" action="{{ $sendAction }}" class="ms-auto">@csrf
        @foreach(($sendFields ?? []) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <button class="btn btn-sm btn-primary"><i class="bi bi-send-check me-1"></i>{{ $state === 'not_sent' ? 'Send for approval' : 'Send again for approval' }}</button></form>
    @endif
  </div>
  <div class="small text-secondary mt-1">{{ [
      'attendance' => 'From October 2026 the owner approves each locked month before its sub-bill is made. If returned, the month opens again for correction.',
      'sub_bill' => 'The owner approves the sub-bill before it can be marked submitted, e-mailed or put in the GeM bill pack.',
      'tax_invoice' => 'The owner approves the tax invoice (after its sub-bills) before it can be marked submitted, e-mailed or put in the GeM bill pack.',
      'payout' => 'The owner approves the month\'s salary payout before payout sheets and bank letters can be downloaded. If any figure or bank account changes, it must be approved again.',
    ][$type] }}</div>
  @error('approval')<div class="alert alert-danger small mt-2 mb-0">{{ $message }}</div>@enderror
  @if($isOwner && $state === 'pending')
    @include('approvals._decide', ['a' => $latest])
  @endif
  @if($history->count() > 1 || ($latest && $latest->status !== 'pending'))
    <details class="small mt-2"><summary class="text-secondary">History</summary>
      <ul class="mb-0">@foreach($history as $h)
        <li>{{ $h->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}: sent by {{ $h->submitter?->name ?? '—' }} – <b>{{ \App\Models\Approval::STATUSES[$h->status] }}</b>@if($h->decided_at) by {{ $h->decider?->name }} on {{ $h->decided_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}@endif @if($h->reason) – “{{ $h->reason }}”@endif</li>
      @endforeach</ul></details>
  @endif
</div>
@endif
