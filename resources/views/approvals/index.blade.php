<x-layouts.app title="Approvals" crumb="Approvals (owner)">
  @error('approval')<div class="alert alert-danger small">{{ $message }}</div>@enderror
  {{-- Payments received waiting for a Super Admin (owner, 22 Nov 2026): approved on each payment's page. --}}
  @php($waitingPayments = \App\Models\PaymentReceipt::query()->where('status', 'pending')->with('contract:id,contract_no', 'agency:id,code', 'enteredBy:id,name')->oldest()->get())
  @if($waitingPayments->isNotEmpty())
    <div class="z-card mb-3">
      <div class="z-card-h"><h2><i class="bi bi-cash-coin me-1"></i>Payments received waiting for approval</h2><span class="small text-secondary">{{ $waitingPayments->count() }}. Open each, check it against the bank statement and payment advice, then Approve or Return.</span></div>
      @foreach($waitingPayments as $p)
        <div class="px-3 py-2 border-top d-flex flex-wrap gap-2 align-items-center"><span class="badge text-bg-dark">{{ $p->agency?->code }}</span>
          <a class="fw-semibold" href="{{ route('payments.show', $p) }}">{{ $p->label() }}</a><span class="small text-secondary">credited {{ $p->credit_date->format('d M Y') }} · work order {{ $p->contract?->contract_no }} · entered by {{ $p->enteredBy?->name ?? '—' }}</span></div>
      @endforeach
    </div>
  @endif
  @if($canApprove)
    <div class="z-card mb-3">
      <div class="z-card-h"><h2><i class="bi bi-inbox me-1"></i>Waiting for your approval</h2><span class="small text-secondary">{{ $pending->count() }} {{ \Illuminate\Support\Str::plural('item', $pending->count()) }}. Open each to check it, then Approve or Return with a reason.</span></div>
      @forelse($pending->groupBy('agency_id') as $group)
        @php($ag = $group->first()->agency)
        <div class="px-3 py-2 bg-body-secondary border-top d-flex align-items-center gap-2"><b>{{ $ag->name }}</b> <span class="badge text-bg-dark">{{ $ag->code }}</span>
          <span class="small text-secondary">{{ $group->count() }} waiting</span></div>
        @php($newStaff = $group->where('type', 'new_employee')->values())
        @if($newStaff->isNotEmpty())
          <div class="px-3 pt-2 small fw-semibold"><i class="bi bi-person-plus me-1"></i>New employees ({{ $newStaff->count() }})</div>
          <div class="px-2 pb-2">@include('approvals._new-staff-table', ['items' => $newStaff])</div>
        @endif
        @foreach($group->where('type', '!=', 'new_employee') as $a)
        <div class="px-3 py-2 border-top">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge text-bg-light border">{{ \App\Models\Approval::TYPES[$a->type] }}</span>
            @if($a->url())<a class="fw-semibold" href="{{ $a->url() }}">{{ $a->label }}</a>@else<span class="fw-semibold">{{ $a->label }}</span>@endif
            <span class="small text-secondary">sent by {{ $a->submitter?->name ?? '—' }}, {{ $a->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
            @if($a->type === 'payout')<a class="small" target="_blank" href="{{ route('bank-letters.pdf', ['agency' => $a->agency_id, 'month' => $a->month->format('Y-m')]) }}">Bank letter PDF</a>@endif
            @if($a->type === 'sub_bill' && $a->invoice_id)<a class="small" target="_blank" href="{{ route('invoices.pdf', $a->invoice_id) }}">PDF</a>@endif
            @if($a->type === 'tax_invoice' && $a->tax_invoice_id)<a class="small" target="_blank" href="{{ route('tax-invoices.pdf', $a->tax_invoice_id) }}">PDF</a>@endif
          </div>
          @if(in_array($a->type, \App\Models\Approval::STAFF_TYPES, true))@include('approvals._changes', ['a' => $a])@endif
          @include('approvals._decide', ['a' => $a])
        </div>
        @endforeach
      @empty
        <div class="p-3 text-secondary small border-top">Nothing is waiting for approval.</div>
      @endforelse
    </div>
  @endif

  @unless($canApprove)
    <div class="z-card mb-3" id="waiting">
      <div class="z-card-h"><h2><i class="bi bi-hourglass-split me-1"></i>Sent – waiting for Super Admin approval</h2><span class="small text-secondary">{{ $waiting->count() }} {{ \Illuminate\Support\Str::plural('item', $waiting->count()) }}. Nothing to do here: a Super Admin will approve it or return it to you with a reason.</span></div>
      @php($blk = null)
      @forelse($waiting as $a)
        @if($blk !== $a->agency_id)
          @php($blk = $a->agency_id)
          <div class="px-3 py-2 bg-secondary-subtle border-top"><b>{{ $a->agency->name }}</b> <span class="badge text-bg-dark">{{ $a->agency->code }}</span></div>
        @endif
        @php($days = (int) $a->submitted_at->copy()->startOfDay()->diffInDays(now()->startOfDay()))
        <div class="px-3 py-2 border-top d-flex flex-wrap gap-2 align-items-center small">
          <span class="badge text-bg-light border">{{ \App\Models\Approval::TYPES[$a->type] }}</span>
          @if($a->url())<a class="fw-semibold" href="{{ $a->url() }}">{{ $a->label }}</a>@else<span class="fw-semibold">{{ $a->label }}</span>@endif
          <span class="text-secondary">sent by {{ $a->submitter?->name ?? '—' }}, {{ $a->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
          @if($a->type === 'payout')<a target="_blank" href="{{ route('bank-letters.pdf', ['agency' => $a->agency_id, 'month' => $a->month->format('Y-m')]) }}">Bank letter PDF</a>@endif
          @if($a->type === 'sub_bill' && $a->invoice_id)<a target="_blank" href="{{ route('invoices.pdf', $a->invoice_id) }}">PDF</a>@endif
          @if($a->type === 'tax_invoice' && $a->tax_invoice_id)<a target="_blank" href="{{ route('tax-invoices.pdf', $a->tax_invoice_id) }}">PDF</a>@endif
          <span class="badge {{ $days >= 3 ? 'text-bg-warning' : 'text-bg-secondary' }} ms-auto">Pending · {{ $days === 0 ? 'sent today' : $days.' '.\Illuminate\Support\Str::plural('day', $days).' waiting' }}</span>
        </div>
      @empty
        <div class="p-3 text-secondary small border-top">Nothing is waiting. Everything you sent has been decided.</div>
      @endforelse
    </div>
  @endunless

  <div class="z-card mb-3">
    <div class="z-card-h"><h2><i class="bi bi-arrow-return-left me-1"></i>Returned to you for correction</h2></div>
    @forelse($returned as $a)
      <div class="px-3 py-2 border-top small"><span class="badge text-bg-light border">{{ \App\Models\Approval::TYPES[$a->type] }}</span>
        @if($a->url())<a class="fw-semibold" href="{{ $a->url() }}">{{ $a->label }}</a>@else{{ $a->label }}@endif
        – returned by {{ $a->decider?->name }} on {{ $a->decided_at->timezone('Asia/Kolkata')->format('d M Y') }}: <b>{{ $a->reason }}</b></div>
    @empty
      <div class="p-3 text-secondary small border-top">Nothing returned to you.</div>
    @endforelse
  </div>

  <div class="z-card">
    <div class="z-card-h"><h2>Recent decisions</h2></div>
    <div class="table-responsive px-2"><table class="table table-sm mb-0">
      <thead><tr><th>Decided</th><th>What</th><th>Sent by</th><th>Decision</th></tr></thead>
      <tbody>
      @forelse($recent as $a)
        <tr><td class="small text-nowrap">{{ $a->decided_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
          <td class="small"><span class="badge text-bg-dark">{{ $a->agency->code }}</span> @if($a->url())<a href="{{ $a->url() }}">{{ $a->label }}</a>@else{{ $a->label }}@endif</td>
          <td class="small">{{ $a->submitter?->name }}</td>
          <td class="small">@if($a->status === 'approved')<span class="badge text-bg-success">Approved</span>@else<span class="badge text-bg-danger">Returned</span> {{ $a->reason }}@endif by {{ $a->decider?->name }}</td></tr>
      @empty
        <tr><td colspan="4" class="text-secondary small">No decisions yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </div>
</x-layouts.app>
