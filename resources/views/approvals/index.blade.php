<x-layouts.app title="Approvals" crumb="Approvals (owner)">
  @error('approval')<div class="alert alert-danger small">{{ $message }}</div>@enderror
  @if($canApprove)
    <div class="z-card mb-3">
      <div class="z-card-h"><h2><i class="bi bi-inbox me-1"></i>Waiting for your approval</h2><span class="small text-secondary">{{ $pending->count() }} {{ \Illuminate\Support\Str::plural('item', $pending->count()) }}. Open each to check it, then Approve or Return with a reason.</span></div>
      @php($blk = null)
      @forelse($pending as $a)
        @if($blk !== $a->agency_id)
          @php($blk = $a->agency_id)
          <div class="px-3 py-2 bg-secondary-subtle border-top"><b>{{ $a->agency->name }}</b> <span class="badge text-bg-dark">{{ $a->agency->code }}</span></div>
        @endif
        <div class="px-3 py-2 border-top">
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge text-bg-light border">{{ \App\Models\Approval::TYPES[$a->type] }}</span>
            @if($a->url())<a class="fw-semibold" href="{{ $a->url() }}">{{ $a->label }}</a>@else<span class="fw-semibold">{{ $a->label }}</span>@endif
            <span class="small text-secondary">sent by {{ $a->submitter?->name ?? '—' }}, {{ $a->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
            @if($a->type === 'payout')<a class="small" target="_blank" href="{{ route('bank-letters.pdf', ['agency' => $a->agency_id, 'month' => $a->month->format('Y-m')]) }}">Bank letter PDF</a>@endif
            @if($a->type === 'sub_bill' && $a->invoice_id)<a class="small" target="_blank" href="{{ route('invoices.pdf', $a->invoice_id) }}">PDF</a>@endif
            @if($a->type === 'tax_invoice' && $a->tax_invoice_id)<a class="small" target="_blank" href="{{ route('tax-invoices.pdf', $a->tax_invoice_id) }}">PDF</a>@endif
          </div>
          @include('approvals._decide', ['a' => $a])
        </div>
      @empty
        <div class="p-3 text-secondary small border-top">Nothing is waiting for approval.</div>
      @endforelse
    </div>
  @endif

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
