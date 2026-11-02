@php($next = ['draft' => ['approved', 'Approve'], 'approved' => ['submitted', 'Mark submitted to client'], 'submitted' => ['paid', 'Mark paid']][$invoice->status] ?? null)
<x-layouts.app :title="'Sub-bill '.$invoice->invoice_no" :crumb="'Billing / <a href=\''.route('invoices.index').'\'>Bills</a>'">
  <x-slot:actions>
    <a class="btn btn-outline-secondary" href="{{ route('invoices.covering-letter', $invoice) }}" target="_blank"><i class="bi bi-envelope-paper me-1"></i>Covering letter</a>
    <a class="btn btn-outline-secondary" href="{{ route('invoices.pdf', $invoice) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Sub-bill PDF</a>
    @if($invoice->sheet->certificate_path)<a class="btn btn-outline-secondary" href="{{ route('attendance.scan', $invoice->sheet) }}" target="_blank"><i class="bi bi-file-earmark-check me-1"></i>PI certificate</a>@endif
  </x-slot:actions>
  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Agency: <b>{{ $invoice->contract->agency->name }}</b></span>
    <span>Client: <b>{{ $invoice->contract->client->name }}</b></span>
    <span>GeM contract: <span class="mono">{{ $invoice->contract->contract_no }}</span></span>
    <span>PI: <b>{{ $invoice->project->pi_name ?? '—' }}</b></span>
    <span>Tax invoice: @if($invoice->taxInvoice)<a class="mono" href="{{ route('tax-invoices.show', $invoice->taxInvoice) }}">{{ $invoice->taxInvoice->number }}</a>@else<span class="text-secondary">not yet</span> @can('billing')<a href="{{ route('tax-invoices.create', ['client' => $invoice->contract->client_id, 'month' => $invoice->month->format('Y-m')]) }}">Make tax invoice</a>@endcan @endif</span>
  </div>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="z-card">
        <div class="z-card-h"><h2>{{ $invoice->project->name }}, {{ $invoice->month->format('F Y') }}</h2><x-status :value="$invoice->status" /><span class="small text-secondary ms-auto">Dated {{ $invoice->invoice_date->format('d/m/Y') }}</span></div>
        <div class="table-responsive"><table class="table">
          <thead><tr><th>Sl.</th><th>Name</th><th>Post</th><th class="num">Rate</th>@if($invoice->billing_type === 'monthly')<th class="num">Days in month</th>@endif<th class="num">Paid days</th><th class="num">Amount</th></tr></thead>
          <tbody>@foreach($invoice->lines as $l)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $l->employee_name }}</td><td>{{ $l->designation }}</td><td class="num">{{ inr($l->rate) }}{{ $l->rate_type === 'daily' ? '/day' : '' }}</td>
              @if($invoice->billing_type === 'monthly')<td class="num">{{ $l->days_in_month }}</td>@endif<td class="num">{{ rtrim(rtrim((string) $l->paid_days, '0'), '.') }}</td><td class="num">{{ inr($l->amount, true) }}</td></tr>
          @endforeach</tbody>
        </table></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="z-card p-3">
        <table class="table table-sm mb-2">
          <tr><td>Wages</td><td class="num">{{ inr($invoice->wages, true) }}</td></tr>
          @if((float) $invoice->epf > 0)<tr><td>Employer EPF</td><td class="num">{{ inr($invoice->epf, true) }}</td></tr>@endif
          @if((float) $invoice->esic > 0)<tr><td>Employer ESIC</td><td class="num">{{ inr($invoice->esic, true) }}</td></tr>@endif
          <tr><td>Service charge @ {{ (float) $invoice->service_charge_percent }}%</td><td class="num">{{ inr($invoice->service_charge, true) }}</td></tr>
          <tr class="fw-semibold"><td>Total before GST</td><td class="num">{{ inr($invoice->taxable, true) }}</td></tr>
          @if((float) $invoice->igst > 0)<tr><td>IGST @ {{ (float) $invoice->gst_percent }}%</td><td class="num">{{ inr($invoice->igst, true) }}</td></tr>
          @else<tr><td>CGST @ {{ (float) $invoice->gst_percent / 2 }}%</td><td class="num">{{ inr($invoice->cgst, true) }}</td></tr><tr><td>SGST @ {{ (float) $invoice->gst_percent / 2 }}%</td><td class="num">{{ inr($invoice->sgst, true) }}</td></tr>@endif
          <tr class="fs-5 fw-bold"><td>Bill amount</td><td class="num">{{ inr($invoice->rounded_total, true) }}</td></tr>
        </table>
        <div class="small text-secondary mb-3">{{ $invoice->amountInWords() }}</div>
        @if($next)
          <form method="post" action="{{ route('invoices.status', $invoice) }}" class="d-grid gap-2">@csrf
            <input type="hidden" name="status" value="{{ $next[0] }}">
            @if($next[0] === 'paid')<label class="small text-secondary" for="paid_on">Payment received on</label><input id="paid_on" type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" required>@endif
            <button class="btn btn-primary">{{ $next[1] }}</button>
          </form>
        @endif
        @if($invoice->status === 'draft')
          @can('billing')<form method="post" action="{{ route('invoices.destroy', $invoice) }}" class="d-grid mt-2" onsubmit="return confirm('Delete this draft bill? Its number will not be reused.')">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">Delete draft</button></form>@endcan
        @endif
      </div>
    </div>
  </div>
</x-layouts.app>
