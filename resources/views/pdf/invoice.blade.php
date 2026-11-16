{{-- Sub-bill in the agencies' own format (SWACHHA sub-bills, October 2026): text header, one row per person,
     totals with working days, service charge @ 3.26% (excluding GST), 18% GST, grand total and round up,
     "Kindly Attention … (P.I)" and the signature box. Figures are the bill's stored figures (exact, 2 decimals). --}}
@php($rs = fn ($v) => number_format((float) $v, 2, '.', ''))
@php($daily = $invoice->billing_type === 'daily')
@php($epfNo = \App\Support\AgencyDetails::epf($agency))
@php($esicNo = \App\Support\AgencyDetails::esic($agency))
@php($clra = \App\Support\AgencyDetails::clra($agency))
@php($phone = $agency->phones ?: $agency->phone)
@php($gst = (float) $invoice->gst_percent)
@php($sc = (float) $invoice->service_charge_percent)
@php($scShown = $invoice->service_charge_includes_gst ? rtrim(rtrim(number_format($sc / (1 + $gst / 100), 2), '0'), '.') : rtrim(rtrim(number_format($sc, 2), '0'), '.'))
@php($days = $invoice->lines->sum(fn ($l) => (float) $l->paid_days))
@php($pi = $project->incharge?->name ?: $project->pi_name)
<html><head><meta charset="utf-8">
<style>
  @page { margin: 30px 40px 40px; }
  body { font-family: "DejaVu Serif", serif; font-size: 10px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
  th { font-weight: normal; text-align: left; vertical-align: top; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
  .r { text-align: right; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .nb td { border: 0; }
  .sans { font-family: "DejaVu Sans", sans-serif; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:6px 4px;border-bottom:0">
    <div class="b" style="font-size:13px">BILL</div>
    <div class="b" style="font-size:12px">{{ strtoupper($agency->name) }}</div>
    <div class="b" style="font-size:11px">{{ strtoupper(\App\Support\AgencyDetails::address($agency)) }}</div>
    <div class="b" style="font-size:11px">{{ collect([$phone ? 'Mob: '.$phone : null, $epfNo ? 'EPF Regd No: '.$epfNo : null])->filter()->implode(',') }}</div>
    <div class="b i sans" style="font-size:9px">{{ collect([$esicNo ? 'ESIC Regd No: '.$esicNo : null, $agency->gstin ? 'GST Regd No: '.$agency->gstin : null])->filter()->implode(', ') }},<br>{{ collect([$agency->pan ? 'PAN No- '.$agency->pan : null, $clra ? 'CL&RA-'.$clra : null])->filter()->implode(',') }}</div>
  </td></tr>
  <tr><td style="border-top:0;padding:0;height:6px"></td></tr>
  <tr><td class="b i" style="font-size:11px">
    <table class="nb"><tr><td style="width:40%">SL No:-{{ $invoice->invoice_no }}</td><td class="c">Month - {{ $invoice->month->format('M-y') }}</td><td class="r">Date:{{ $invoice->invoice_date->format('d/m/Y') }}</td></tr></table>
  </td></tr>
  <tr><td class="b i sans" style="font-size:10px">To,<br>{{ $client->addressee ?: 'The Administrative Officer' }},<br>{!! nl2br(e($client->billing_address ?: $client->name)) !!}</td></tr>
</table>
<table class="sans">
  <thead><tr><th style="width:6%">SL NO</th><th style="width:30%" class="c">DESCRIPTION</th><th style="width:9%">QUANTITY</th><th style="width:11%" class="c">Nos of<br>Working Day</th><th style="width:9%" class="c">Rate<br>per/day</th><th style="width:11%">{{ $daily ? 'Rate per day' : 'Rate per Month/Per Head' }}</th><th class="r">Amount</th></tr></thead>
  <tbody>
    <tr><td></td><td colspan="5" class="i" style="font-size:9px;text-align:justify">Professional Charges for Manpower Personnel deployed under project entitled "{{ $project->title ?: $project->name }}" at {{ $project->location ?: $client->name }} For the month of {{ $invoice->month->format('M-Y') }} vide your office {{ $contract->contract_type === 'offline' ? 'work order' : 'GeM work Order' }} No. <b>{{ $contract->contract_no }}</b>@if($contract->contract_date) Dt- {{ $contract->contract_date->format('d.m.Y') }}@endif under your kind control.</td><td></td></tr>
    @foreach($invoice->lines as $l)
      @php($perDay = $l->rate_type === 'monthly' ? (float) $l->rate / max(1, (int) $l->days_in_month) : (float) $l->rate)
      <tr><td class="c" style="vertical-align:bottom">{{ $loop->iteration }}</td>
        <td class="b i">{{ $l->employee_name }}<br>{{ $l->designation }}</td>
        <td class="c" style="vertical-align:bottom">1</td>
        <td class="c" style="vertical-align:bottom">{{ rtrim(rtrim((string) $l->paid_days, '0'), '.') }}</td>
        <td class="c" style="vertical-align:bottom">{{ $rs($perDay) }}</td>
        <td class="c" style="vertical-align:bottom">{{ rtrim(rtrim(number_format((float) $l->rate, 2, '.', ''), '0'), '.') }}</td>
        <td class="r" style="vertical-align:bottom">{{ $rs($l->amount) }}</td></tr>
    @endforeach
    <tr><td class="c">add</td><td class="b i">Total Monthly Wages</td><td class="c">{{ $invoice->lines->count() }}</td><td class="c">{{ rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.') }}</td><td></td><td></td><td class="r b i">{{ $rs($invoice->wages) }}</td></tr>
    @if((float) $invoice->epf > 0)
      <tr><td class="c">Add</td><td colspan="5">EPF @ {{ (float) $contract->epf_percent }}% (employer share, on wages up to ₹15,000)</td><td class="r">{{ $rs($invoice->epf) }}</td></tr>
    @endif
    @if((float) $invoice->esic > 0)
      <tr><td class="c">Add</td><td colspan="5">ESIC @ {{ (float) $contract->esic_percent }}% (employer share)</td><td class="r">{{ $rs($invoice->esic) }}</td></tr>
    @endif
    <tr><td class="c">Add</td><td colspan="5">Service charge @ {{ $scShown }}% (excluding GST &nbsp;@ {{ rtrim(rtrim(number_format($gst, 2), '0'), '.') }}%)</td><td class="r">{{ $rs($invoice->service_charge) }}</td></tr>
    <tr><td></td><td colspan="5">Total Monthly Wages</td><td class="r b">{{ $rs($invoice->taxable) }}</td></tr>
    <tr><td class="c">Add</td><td colspan="5" style="font-size:11px">{{ rtrim(rtrim(number_format($gst, 2), '0'), '.') }}% GST (as per GST Rule By the GOI){{ (float) $invoice->igst > 0 ? ' – IGST' : '' }}</td><td class="r" style="vertical-align:bottom">{{ $rs((float) $invoice->cgst + (float) $invoice->sgst + (float) $invoice->igst) }}</td></tr>
    <tr><td></td><td colspan="5" class="b i">Grand Total</td><td class="r b i">{{ $rs($invoice->total) }}</td></tr>
    <tr><td class="c">Add</td><td colspan="5" class="b i">Grand Total Round Up</td><td class="r b i">{{ $rs($invoice->rounded_total) }}</td></tr>
  </tbody>
</table>
<div class="c b i sans" style="margin-top:4px">({{ $invoice->amountInWords() }})</div>
<table class="nb" style="margin-top:60px;page-break-inside:avoid"><tr>
  <td style="vertical-align:bottom;font-size:12px">@if($pi)Kindly Attention {{ $pi }} (P.I)@endif</td>
  <td style="width:34%;padding:0"><table><tr><td class="r b i sans" style="border:2px solid #111;padding:6px 8px">
    <span style="font-size:11px">{{ strtoupper($agency->name) }}</span><br><span style="font-size:9px">Authorized Signatory</span>@if($phone)<br><span style="font-size:9px">Mob: {{ $phone }}</span>@endif</td></tr></table></td>
</tr></table>
</body></html>
