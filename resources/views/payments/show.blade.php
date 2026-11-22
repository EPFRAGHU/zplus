<x-layouts.app title="Payment received" :crumb="'Billing / <a href=\''.route('payments.index').'\'>Payments received</a>'" :quiet-title="true">
  @php
    $money = fn ($v) => inr((float) $v, true);
    $gstTds = (float) $totals['tds_cgst'] + (float) $totals['tds_sgst'] + (float) $totals['tds_igst'];
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-cash-coin"></i></div>
      <div class="me-auto"><h1>{{ $money($receipt->amount) }} received · {{ $receipt->credit_date->format('d M Y') }} @include('payments._status', ['r' => $receipt])</h1>
        <p>{{ $receipt->agency?->name }} · client: {{ $receipt->client?->name }} · work order <a href="{{ route('contracts.show', $receipt->gem_contract_id) }}">{{ $receipt->contract?->contract_no }}</a></p></div>
      <div class="wo-actions d-flex gap-2 flex-wrap">
        @if($receipt->status !== 'approved')<a class="btn wo-btn-line" href="{{ route('payments.edit', $receipt) }}"><i class="bi bi-pencil me-1"></i>Change</a>@endif
        <a class="btn btn-link" href="{{ route('payments.index') }}">Back to Payments</a>
      </div>
    </div>
    @error('receipt')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @error('note')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @if($receipt->status === 'returned')<div class="alert alert-warning small"><b>Returned:</b> {{ $receipt->return_note }} — correct it with <b>Change</b>; it then waits for approval again.</div>@endif

    @if($receipt->status === 'pending')
      <div class="wo-card p-3 mb-3 border-warning">
        @if($canApprove)
          <div class="d-flex flex-wrap gap-2 align-items-start">
            <form method="post" action="{{ route('payments.approve', $receipt) }}">@csrf<button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Approve payment</button></form>
            <form method="post" action="{{ route('payments.return', $receipt) }}" class="d-flex gap-2 flex-grow-1">@csrf
              <input name="note" class="form-control" maxlength="500" placeholder="Reason to return (e.g. wrong UTR, TDS differs from the advice)" required>
              <button class="btn btn-outline-danger text-nowrap">Return</button></form>
          </div>
          <div class="small text-secondary mt-2">Check the amount, UTR / cheque and deductions against the bank statement and the client's payment advice. On approval, sub-bills paid in full turn <b>Paid</b> (and their tax invoice when all its sub-bills are paid).</div>
        @else
          <div class="small"><i class="bi bi-hourglass-split me-1"></i>Waiting for a Super Admin's approval{{ \App\Services\Approvals\Approvals::canApprove(auth()->user()) ? ' (another Super Admin: you entered it)' : '' }}. Until then it does not count and the sub-bills stay unpaid.</div>
        @endif
      </div>
    @endif

    <div class="row g-3 mb-3">
      <div class="col-lg-6"><div class="wo-card p-3 h-100"><table class="table table-sm m-0 small">
        <tr><th class="text-secondary fw-normal" style="width:40%">Mode</th><td>{{ \App\Models\PaymentReceipt::MODES[$receipt->mode] ?? $receipt->mode }}</td></tr>
        @if(in_array($receipt->mode, ['cheque', 'dd'], true))
          <tr><th class="text-secondary fw-normal">Cheque / DD no.</th><td class="mono">{{ $receipt->cheque_no }}</td></tr>
          <tr><th class="text-secondary fw-normal">Cheque / DD date</th><td>{{ $receipt->cheque_date?->format('d M Y') }}</td></tr>
          <tr><th class="text-secondary fw-normal">Drawn on</th><td>{{ $receipt->cheque_bank ?: '—' }}</td></tr>
        @else
          <tr><th class="text-secondary fw-normal">UTR number</th><td class="mono">{{ $receipt->utr_no ?: '—' }}</td></tr>
          <tr><th class="text-secondary fw-normal">NEFT / RTGS date</th><td>{{ $receipt->transfer_date?->format('d M Y') ?? '—' }}</td></tr>
        @endif
        <tr><th class="text-secondary fw-normal">Credit date</th><td class="fw-semibold">{{ $receipt->credit_date->format('d M Y') }}</td></tr>
        <tr><th class="text-secondary fw-normal">Account credited</th><td>{{ $receipt->bank_account ?: '—' }}</td></tr>
        <tr><th class="text-secondary fw-normal">Amount credited</th><td class="fw-semibold">{{ $money($receipt->amount) }}</td></tr>
        <tr><th class="text-secondary fw-normal">Payment advice</th><td>@if($receipt->advice_path)<a href="{{ route('payments.advice', $receipt) }}" target="_blank"><i class="bi bi-paperclip me-1"></i>{{ $receipt->advice_name }}</a>@else — @endif</td></tr>
        @if($receipt->remarks)<tr><th class="text-secondary fw-normal">Remarks</th><td>{{ $receipt->remarks }}</td></tr>@endif
      </table></div></div>
      <div class="col-lg-6"><div class="wo-card p-3 h-100"><table class="table table-sm m-0 small">
        <tr><th class="text-secondary fw-normal" style="width:40%">Bill amount (sub-bills)</th><td>{{ $money($totals['bill_amount']) }}</td></tr>
        <tr><th class="text-secondary fw-normal">TDS (income tax)</th><td>{{ $money($totals['tds_it']) }}</td></tr>
        <tr><th class="text-secondary fw-normal">GST TDS</th><td>{{ $money($gstTds) }} <span class="text-secondary">(CGST {{ $money($totals['tds_cgst']) }} · SGST {{ $money($totals['tds_sgst']) }}{{ (float) $totals['tds_igst'] ? ' · IGST '.$money($totals['tds_igst']) : '' }})</span></td></tr>
        <tr><th class="text-secondary fw-normal">Other deductions</th><td>{{ $money($totals['other_deduction']) }}</td></tr>
        <tr><th class="text-secondary fw-normal">Received (net)</th><td class="fw-semibold">{{ $money($totals['received']) }}</td></tr>
        <tr><th class="text-secondary fw-normal">Entered by</th><td>{{ $receipt->enteredBy?->name ?? '—' }}, {{ $receipt->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td></tr>
        <tr><th class="text-secondary fw-normal">Approved by</th><td>{{ $receipt->approvedBy ? $receipt->approvedBy->name.', '.$receipt->approved_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : '—' }}</td></tr>
      </table></div></div>
    </div>

    <form method="post" action="{{ route('payments.checks', $receipt) }}">@csrf
    <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table">
      <thead><tr><th>Sub-bill no.</th><th>Month</th><th class="num">Bill amount</th><th class="num">Before GST</th><th class="num">TDS</th><th class="num">CGST TDS</th><th class="num">SGST TDS</th><th class="num">IGST TDS</th><th class="num">Other</th><th class="num">Received</th><th>Sub-bill now</th>
        @if($receipt->status === 'approved')<th class="text-center">Form 16A / 26AS seen</th><th class="text-center">GSTR-7A accepted</th>@endif</tr></thead>
      <tbody>
      @foreach($receipt->lines as $l)
        @php($due = $receipts->outstanding($l->invoice))
        <tr>
          <td><a class="mono fw-semibold text-decoration-none" href="{{ route('invoices.show', $l->invoice) }}">{{ $l->invoice->invoice_no }}</a></td>
          <td>{{ $l->invoice->month->format('M Y') }}</td><td class="num">{{ $money($l->bill_amount) }}</td><td class="num">{{ $money($l->taxable) }}</td>
          <td class="num">{{ $money($l->tds_it) }}</td><td class="num">{{ $money($l->tds_cgst) }}</td><td class="num">{{ $money($l->tds_sgst) }}</td><td class="num">{{ $money($l->tds_igst) }}</td>
          <td class="num" @if($l->other_reason) title="{{ $l->other_reason }}" @endif>{{ $money($l->other_deduction) }}@if($l->other_reason)<div class="small text-secondary text-wrap">{{ $l->other_reason }}</div>@endif</td>
          <td class="num fw-semibold">{{ $money($l->received) }}</td>
          <td>{{ \App\Models\Invoice::STATUSES[$l->invoice->status] ?? $l->invoice->status }}@if($l->invoice->status !== 'paid' && $due->isPositive())<div class="small text-secondary">₹{{ number_format((float) \App\Support\Exact::money($due), 2) }} still due</div>@endif</td>
          @if($receipt->status === 'approved')
            <td class="text-center">@if((float) $l->tds_it > 0)<input class="form-check-input" type="checkbox" name="form16a[]" value="{{ $l->id }}" @checked($l->form16a_seen)>@else – @endif</td>
            <td class="text-center">@if(((float) $l->tds_cgst + (float) $l->tds_sgst + (float) $l->tds_igst) > 0)<input class="form-check-input" type="checkbox" name="gstr7a[]" value="{{ $l->id }}" @checked($l->gstr7a_accepted)>@else – @endif</td>
          @endif
        </tr>
      @endforeach
      </tbody>
      <tfoot><tr class="bills-grand"><td colspan="2">Total · {{ $receipt->lines->count() }} {{ \Illuminate\Support\Str::plural('sub-bill', $receipt->lines->count()) }}</td><td class="num">{{ $money($totals['bill_amount']) }}</td><td class="num">{{ $money($totals['taxable']) }}</td>
        <td class="num">{{ $money($totals['tds_it']) }}</td><td class="num">{{ $money($totals['tds_cgst']) }}</td><td class="num">{{ $money($totals['tds_sgst']) }}</td><td class="num">{{ $money($totals['tds_igst']) }}</td><td class="num">{{ $money($totals['other_deduction']) }}</td><td class="num">{{ $money($totals['received']) }}</td><td></td>
        @if($receipt->status === 'approved')<td colspan="2"></td>@endif</tr></tfoot>
    </table></div></div>
    @if($receipt->status === 'approved')<div class="mt-2"><button class="btn btn-sm wo-btn-line"><i class="bi bi-check2-square me-1"></i>Save TDS ticks</button>
      <span class="small text-secondary ms-2">Tick when the TDS shows in Form 26AS / 16A and the GST TDS is accepted on the GST portal (GSTR-7A).</span></div>@endif
    </form>

    @can('delete-records')
      <form method="post" action="{{ route('payments.destroy', $receipt) }}" class="mt-4" onsubmit="return confirm('Delete this payment? Sub-bills it had paid in full go back to Submitted.')">@csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete payment (Super Admin)</button>
        <span class="small text-secondary ms-2">For a wrong entry: sub-bills it had paid in full go back to Submitted. Recorded in the Activity log.</span></form>
    @endcan
  </div>
</x-layouts.app>
