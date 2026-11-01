@php($rs = fn ($v) => number_format((float) $v, 2))
@php($daily = $invoice->billing_type === 'daily')
<html><head><meta charset="utf-8">@include('pdf._style')
<style>.grid td,.grid th{font-size:9.5px;padding:3px 4px}</style></head><body>
@include('pdf._letterhead-footer')
@include('pdf._letterhead')
<div class="c b" style="font-size:15px;letter-spacing:2px">BILL</div>
<table style="margin:4px 0 6px"><tr>
  <td>Date: {{ $invoice->invoice_date->format('d/m/Y') }} &nbsp; Month - {{ $invoice->month->format('M-y') }}</td>
  <td class="r">SL No:- <b>{{ $invoice->invoice_no }}</b></td>
</tr></table>
<table class="grid" style="margin-bottom:6px">
  <tr>
    <th style="width:12%;text-align:left">Agency</th>
    <td style="width:38%"><b>{{ $agency->name }}</b> ({{ $agency->code }})@if($agency->gstin)<br>GSTIN: {{ $agency->gstin }}@endif</td>
    <th style="width:12%;text-align:left">Client</th>
    <td><b>{{ $client->name }}</b> ({{ $client->short_code }})@if($client->gstin)<br>GSTIN: {{ $client->gstin }}@endif</td>
  </tr>
</table>
<p style="margin:0 0 6px">To,<br>{{ $client->addressee ?? 'The Administrative Officer' }},<br>{!! nl2br(e($client->billing_address ?: $client->name)) !!}</p>
<table class="grid">
  <thead><tr><th>SL No</th><th>Description</th><th>Qty</th><th>Nos of working days</th><th>Rate per day</th><th>{{ $daily ? 'Rate per day' : 'Rate per month / head' }}</th><th>Total amount</th><th>Amount</th></tr></thead>
  <tbody>
    <tr><td></td><td colspan="7" style="text-align:justify">Professional Charges for Manpower Personnel deployed under project entitled "{{ $project->title ?: $project->name }}" at {{ $client->name }} for the month of {{ $invoice->month->format('M-Y') }} vide your office GeM work order No. {{ $contract->contract_no }}@if($contract->contract_date) Dt- {{ $contract->contract_date->format('d.m.Y') }}@endif under your kind control.</td></tr>
    @foreach($invoice->lines as $l)
      @php($perDay = $l->rate_type === 'monthly' ? (float) $l->rate / max(1, (int) $l->days_in_month) : (float) $l->rate)
      <tr><td class="c">{{ $loop->iteration }}</td><td>{{ $l->employee_name }}<br><span class="muted">{{ $l->designation }}</span></td><td class="c">1</td>
        <td class="c">{{ rtrim(rtrim((string) $l->paid_days, '0'), '.') }}</td><td class="r">{{ $rs($perDay) }}</td><td class="r">{{ $rs($l->rate) }}</td>
        <td class="r">{{ $rs($l->amount) }}</td><td class="r">{{ $rs($l->amount) }}</td></tr>
    @endforeach
    <tr class="b"><td>Add</td><td colspan="6">Total monthly wages ({{ $invoice->lines->count() }} {{ \Illuminate\Support\Str::plural('person', $invoice->lines->count()) }})</td><td class="r">{{ $rs($invoice->wages) }}</td></tr>
    @if($daily)
      <tr><td>Add</td><td colspan="6">Employer EPF</td><td class="r">{{ $rs($invoice->epf) }}</td></tr>
      <tr><td>Add</td><td colspan="6">Employer ESIC</td><td class="r">{{ $rs($invoice->esic) }}</td></tr>
    @endif
    <tr><td>Add</td><td colspan="6">Service charge @ {{ (float) $invoice->service_charge_percent }}% (excluding GST @ {{ (float) $invoice->gst_percent }}%)</td><td class="r">{{ $rs($invoice->service_charge) }}</td></tr>
    <tr class="b"><td></td><td colspan="6">Total</td><td class="r">{{ $rs($invoice->taxable) }}</td></tr>
    <tr><td>Add</td><td colspan="6">{{ (float) $invoice->gst_percent }}% GST (as per GST Rule by the GOI){{ (float) $invoice->igst > 0 ? ' – IGST' : ' – CGST '.$rs($invoice->cgst).' + SGST '.$rs($invoice->sgst) }}</td><td class="r">{{ $rs((float) $invoice->cgst + (float) $invoice->sgst + (float) $invoice->igst) }}</td></tr>
    <tr class="b"><td></td><td colspan="6">Grand total</td><td class="r">{{ $rs($invoice->total) }}</td></tr>
    <tr class="b"><td>Add</td><td colspan="6">Grand total round up</td><td class="r">{{ $rs($invoice->rounded_total) }}</td></tr>
  </tbody>
</table>
<p class="b" style="margin-top:6px">({{ $invoice->amountInWords() }})</p>
<table style="margin-top:6px"><tr>
  <td style="vertical-align:top" class="small">GeM Contract: {{ $contract->contract_no }} · Bid: {{ $contract->bid_no }} · SAC: {{ $contract->sac_code }}@if($project->pi_name) · PI: {{ $project->pi_name }}@endif</td>
  <td class="r" style="width:40%">For {{ strtoupper($agency->name) }}<br><br><br>{{ $agency->signatory_name }}<br>{{ $agency->signatory_title ?? 'Managing Partner' }}</td>
</tr></table>
</body></html>
