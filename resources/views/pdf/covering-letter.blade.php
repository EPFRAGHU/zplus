<html><head><meta charset="utf-8">@include('pdf._style')</head><body class="letter">
@include('pdf._letterhead-footer')
<div style="font-family:'DejaVu Sans',sans-serif">@include('pdf._letterhead')</div>
<table style="font-size:11px;margin-bottom:4px"><tr><td>Ref. No. {{ $invoice->invoice_no }}</td><td class="r">Date: {{ $invoice->invoice_date->format('d/m/Y') }}</td></tr></table>
<p>To,<br>{{ $client->addressee ?? 'The Administrative Officer' }},<br>{!! nl2br(e($client->billing_address ?: $client->name)) !!}</p>
<table style="font-size:11px;margin-bottom:6px"><tr><td><b>Agency:</b> {{ $agency->name }}</td><td class="r"><b>Client:</b> {{ $client->name }}</td></tr></table>
<p><b>Sub:</b> Submission of bill for the Month of {{ $invoice->month->format('M-Y') }}</p>
<p><b>Ref:</b> Your good office work Order No GeM Contract No:- {{ $contract->contract_no }} &amp; Bid No:- {{ $contract->bid_no }}@if($contract->contract_date) Dated :- {{ $contract->contract_date->format('d/m/Y') }}@endif</p>
<p>Sir,</p>
<p style="text-indent:40px;text-align:justify">Here we submit the bill bearing No- {{ $invoice->invoice_no }} on our office letter No. {{ $invoice->invoice_no }} dated {{ $invoice->invoice_date->format('d/m/Y') }} amounting to Rs {{ number_format((float) $invoice->rounded_total, 2, '.', '') }} ({{ $invoice->amountInWords() }}) towards Professional Charges for Manpower Personnel deployed under project entitled "{{ $project->title ?: $project->name }}" at {{ $client->name }} kind control during the month of {{ $invoice->month->format('M-Y') }}.</p>
<p style="text-indent:40px">The above bill amount may kindly be released at an early date.</p>
<p>Thanking you</p>
<p>Yours sincerely</p>
<div class="sign"><b>{{ strtoupper($agency->name) }}</b><br>{{ $agency->signatory_title ?? 'Managing Partner' }}@if($agency->phone)<br>Mob: {{ $agency->phone }}@endif</div>
</body></html>
