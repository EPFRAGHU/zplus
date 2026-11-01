@php($rs = fn ($v) => 'Rs. '.ltrim(inr($v, true), '₹'))
<html><head><meta charset="utf-8">@include('pdf._style')</head><body>
<table><tr>
  <td><h1>{{ $agency->name }}</h1><div class="muted">{!! nl2br(e($agency->address)) !!}</div>
    @if($agency->gstin)<div>GSTIN: {{ $agency->gstin }}</div>@endif @if($agency->pan)<div>PAN: {{ $agency->pan }}</div>@endif</td>
  <td class="r" style="vertical-align:top"><h2>TAX INVOICE</h2><div>Bill No.: <b>{{ $invoice->invoice_no }}</b></div><div>Date: {{ $invoice->invoice_date->format('d/m/Y') }}</div><div>Month: {{ $invoice->month->format('M-Y') }}</div></td>
</tr></table>
<hr>
<table class="grid" style="margin-bottom:8px">
  <tr>
    <th style="width:12%;text-align:left">Agency</th>
    <td style="width:38%"><b>{{ $agency->name }}</b> ({{ $agency->code }})@if($agency->gstin)<br>GSTIN: {{ $agency->gstin }}@endif</td>
    <th style="width:12%;text-align:left">Client</th>
    <td><b>{{ $client->name }}</b> ({{ $client->short_code }})@if($client->gstin)<br>GSTIN: {{ $client->gstin }}@endif</td>
  </tr>
</table>
<table><tr>
  <td style="width:55%;vertical-align:top"><div class="muted">Bill to</div><div class="b">{{ $client->addressee }}</div><div>{{ $client->name }}</div><div>{!! nl2br(e($client->billing_address)) !!}</div>@if($client->gstin)<div>GSTIN: {{ $client->gstin }}</div>@endif</td>
  <td style="vertical-align:top"><div>GeM Contract No.: {{ $contract->contract_no }}</div><div>GeM Bid No.: {{ $contract->bid_no }}</div><div>Project: {{ $project->title ?: $project->name }}</div>@if($project->pi_name)<div>PI: {{ $project->pi_name }}</div>@endif<div>SAC: 998519 (Manpower supply services)</div></td>
</tr></table>
<br>
<table class="grid">
  <thead><tr><th>Sl.</th><th>Name</th><th>Post</th><th class="r">Rate</th>@if($invoice->billing_type === 'monthly')<th class="c">Days in month</th>@endif<th class="c">Paid days</th><th class="r">Amount</th></tr></thead>
  <tbody>
  @foreach($invoice->lines as $l)
    <tr><td class="c">{{ $loop->iteration }}</td><td>{{ $l->employee_name }}</td><td>{{ $l->designation }}</td><td class="r">{{ $rs($l->rate) }}{{ $l->rate_type === 'daily' ? '/day' : '' }}</td>
      @if($invoice->billing_type === 'monthly')<td class="c">{{ $l->days_in_month }}</td>@endif<td class="c">{{ rtrim(rtrim((string) $l->paid_days, '0'), '.') }}</td><td class="r">{{ $rs($l->amount) }}</td></tr>
  @endforeach
  @php($span = $invoice->billing_type === 'monthly' ? 6 : 5)
  <tr><td colspan="{{ $span }}" class="r">Professional charges</td><td class="r">{{ $rs($invoice->wages) }}</td></tr>
  @if($invoice->billing_type === 'daily')
    <tr><td colspan="{{ $span }}" class="r">Employer EPF</td><td class="r">{{ $rs($invoice->epf) }}</td></tr>
    <tr><td colspan="{{ $span }}" class="r">Employer ESIC</td><td class="r">{{ $rs($invoice->esic) }}</td></tr>
  @endif
  <tr><td colspan="{{ $span }}" class="r">Service charge @ {{ (float) $invoice->service_charge_percent }}%</td><td class="r">{{ $rs($invoice->service_charge) }}</td></tr>
  <tr><td colspan="{{ $span }}" class="r b">Total before GST</td><td class="r b">{{ $rs($invoice->taxable) }}</td></tr>
  @if((float) $invoice->igst > 0)
    <tr><td colspan="{{ $span }}" class="r">IGST @ {{ (float) $invoice->gst_percent }}%</td><td class="r">{{ $rs($invoice->igst) }}</td></tr>
  @else
    <tr><td colspan="{{ $span }}" class="r">CGST @ {{ (float) $invoice->gst_percent / 2 }}%</td><td class="r">{{ $rs($invoice->cgst) }}</td></tr>
    <tr><td colspan="{{ $span }}" class="r">SGST @ {{ (float) $invoice->gst_percent / 2 }}%</td><td class="r">{{ $rs($invoice->sgst) }}</td></tr>
  @endif
  @if($invoice->rounded_total !== $invoice->total)<tr><td colspan="{{ $span }}" class="r">Rounded off</td><td class="r">{{ $rs((float) $invoice->rounded_total - (float) $invoice->total) }}</td></tr>@endif
  <tr><td colspan="{{ $span }}" class="r b">Bill amount</td><td class="r b">{{ $rs($invoice->rounded_total) }}</td></tr>
  </tbody>
</table>
<p><b>Amount in words:</b> {{ $invoice->amountInWords() }}</p>
<table class="sign"><tr><td></td><td class="r" style="width:40%">For {{ $agency->name }}<br><br><br>{{ $agency->signatory_name }}<br>{{ $agency->signatory_title ?? 'Authorised Signatory' }}</td></tr></table>
</body></html>
