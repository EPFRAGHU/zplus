{{-- Payments received against these sub-bills (owner, 22 Nov 2026): each receipt line, and what is still due. --}}
@can('billing')
@php
  $svc = app(\App\Services\Payments\Receipts::class);
  $ids = $bills->pluck('id');
  $payLines = \App\Models\PaymentReceiptLine::query()->whereIn('invoice_id', $ids)->whereHas('receipt', fn ($q) => $q->whereIn('status', ['approved', 'pending']))
      ->with('receipt', 'invoice:id,invoice_no')->get()->sortBy(fn ($l) => $l->receipt->credit_date->toDateString().'-'.$l->id);
  $due = $bills->reduce(fn ($c, $b) => $c->plus($svc->outstanding($b)), \App\Support\Exact::zero());
  $payable = $bills->whereIn('status', ['approved', 'submitted'])->pluck('id')->all();
@endphp
<div class="z-card mt-3" id="payments">
  <div class="z-card-h"><h2><i class="bi bi-cash-coin me-1"></i>Payments received</h2>
    <span class="small ms-auto {{ $due->isPositive() && $payable ? 'text-danger fw-semibold' : 'text-success' }}">{{ $due->isPositive() && $payable ? 'Still due: '.inr((float) \App\Support\Exact::money($due), true) : 'Nothing due' }}</span>
    @if($payable)<a class="btn btn-sm wo-btn-primary ms-2" href="{{ route('payments.create', ['contract' => $bills->first()->gem_contract_id, 'bills' => $payable]) }}"><i class="bi bi-plus-lg me-1"></i>Enter payment</a>@endif</div>
  @if($payLines->isEmpty())
    <div class="p-3 small text-secondary">No payment entered yet.@if(! $payable && $bills->every(fn ($b) => $b->status === 'draft')) A payment can be entered once the sub-bill is submitted to the client.@endif</div>
  @else
    <div class="table-responsive"><table class="table table-sm m-0 small">
      <thead><tr><th>Credit date</th><th>UTR / cheque</th>@if($bills->count() > 1)<th>Sub-bill</th>@endif<th class="num">TDS</th><th class="num">GST TDS</th><th class="num">Other</th><th class="num">Received</th><th></th></tr></thead>
      <tbody>@foreach($payLines as $l)
        <tr><td><a href="{{ route('payments.show', $l->payment_receipt_id) }}">{{ $l->receipt->credit_date->format('d M Y') }}</a></td><td class="mono">{{ \App\Models\PaymentReceipt::MODES[$l->receipt->mode] ?? '' }} {{ $l->receipt->reference() }}</td>
          @if($bills->count() > 1)<td class="mono">{{ $l->invoice?->invoice_no }}</td>@endif
          <td class="num">{{ inr($l->tds_it, true) }}</td><td class="num">{{ inr((float) $l->tds_cgst + (float) $l->tds_sgst + (float) $l->tds_igst, true) }}</td><td class="num">{{ inr($l->other_deduction, true) }}</td>
          <td class="num fw-semibold">{{ inr($l->received, true) }}</td><td>@include('payments._status', ['r' => $l->receipt])</td></tr>
      @endforeach</tbody>
    </table></div>
  @endif
</div>
@endcan
