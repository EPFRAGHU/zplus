{{-- Consolidated tax invoice statement (owner, 22 Nov 2026): the month's tax invoices of one agency in the agencies' own tax
     invoice layout. Not a separate tax invoice and not numbered: GST is charged on the tax invoices it lists. $c = MonthBill::consolidated(). --}}
@php($t = $c['t'])
@php($regs = (string) $agency->registrations)
@php($reg = fn (string $label) => preg_match('/'.$label.'[^:\-\n]*[:\-]\s*([A-Z0-9\/]+)/i', $regs, $m) ? $m[1] : null)
@php($epfNo = $reg('EPF'))
@php($esicNo = $reg('ESIC'))
@php($phones = $agency->phones ?: $agency->phone)
@php($emails = $agency->letterhead_emails ?: $agency->email)
@php($address = trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)))
@php($gstHalf = rtrim(rtrim(number_format((float) $c['gst'] / 2, 2), '0'), '.'))
@php($client = $c['client'])
@php($lines = count($c['rows']) + $c['taxes']->count())
@php($fit = $lines <= 10 ? ['font' => 10, 'pad' => '3px 5px', 'title' => 20, 'gap' => 34] : ($lines <= 18 ? ['font' => 9, 'pad' => '2px 4px', 'title' => 17, 'gap' => 20] : ['font' => 8, 'pad' => '1px 3px', 'title' => 15, 'gap' => 12]))
<html><head><meta charset="utf-8">
<style>
  @page { margin: {{ $fit['font'] < 10 ? '20px 30px 26px' : '26px 34px 40px' }}; }
  body { font-family: "DejaVu Serif", serif; font-size: {{ $fit['font'] }}px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: {{ $fit['pad'] }}; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; }
  .r { text-align: right; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .nb td { border: 0; padding: 1px 0; }
  .mid { vertical-align: middle; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:6px 4px">
    <div class="b" style="font-size:{{ $fit['title'] }}px;letter-spacing:1px">"{{ strtoupper($agency->name) }}"</div>
    <div class="b i">{{ $address }}</div>
    <div class="b i">@if($agency->gstin)GSTIN- {{ $agency->gstin }}@endif @if($agency->pan), PAN NO- {{ $agency->pan }},@endif</div>
    @if($epfNo || $esicNo)<div class="b i">@if($epfNo)EPF NO- {{ $epfNo }}@endif @if($esicNo), ESIC NO- {{ $esicNo }}@endif</div>@endif
    <div class="b i">@if($phones)MOB- {{ $phones }}, @endif @if($emails)&nbsp; Email ID- {{ $emails }}@endif</div>
  </td></tr>
  <tr><td class="c b" style="font-size:13px">CONSOLIDATED TAX INVOICE STATEMENT – {{ strtoupper($month->format('F Y')) }}</td></tr>
</table>
<table>
  <tr><td style="width:70%;border-top:0">Combines {{ $c['taxes']->count() }} tax {{ \Illuminate\Support\Str::plural('invoice', $c['taxes']->count()) }} of {{ $month->format('F Y') }}</td><td style="border-top:0">Date:- {{ now()->format('d.m.Y') }}</td></tr>
  <tr><td colspan="2" style="border-top:0">@foreach($c['taxes'] as $tax)<b>{{ $tax->number }}</b> ({{ ($tax->contract?->contract_type === 'offline') ? 'Work order' : 'GeM contract' }} {{ $tax->contract?->contract_no }}) – {{ number_format((float) $tax->rounded_total, 2, '.', '') }}@if(! $loop->last); &nbsp;@endif @endforeach</td></tr>
</table>
@if($fit['font'] === 10)<br>@else<div style="height:5px"></div>@endif
<table>
  <tr><td style="width:55%">Bill for the period from :-</td><td>Month- {{ strtoupper($month->format('M-Y')) }}</td></tr>
  <tr><td class="b i" style="border-bottom:0">FROM</td><td class="b i" style="border-bottom:0">TO</td></tr>
  <tr><td class="b" style="border-top:0">{{ strtoupper($agency->name) }}<br>{!! nl2br(e($agency->address)) !!}@if($agency->gstin)<br>GSTIN- {{ $agency->gstin }}@endif @if($agency->phone)<br>MOB- {{ $agency->phone }}@endif</td>
      <td class="b" style="border-top:0">{{ $client?->addressee ?: 'The Administrative Officer' }}<br>{!! nl2br(e($client?->billing_address ?: $client?->name)) !!}@if($client?->gstin)<br>GSTIN- {{ $client->gstin }}@endif</td></tr>
  <tr><td colspan="2" class="c b" style="font-size:12px">Category of Service: Manpower Service</td></tr>
  <tr><td colspan="2" class="b">Bank Detail of Company:- &nbsp;Account Holder- {{ strtoupper($agency->name) }}<br>
    <span style="padding-left:150px">Bank A/C: {{ $agency->bank_account_no ?: '____________' }} &nbsp;Bank IFSC: {{ $agency->bank_ifsc ?: '________' }},</span><br>
    <span style="padding-left:150px">Bank Name: {{ $agency->bank_name ?: '________' }}@if($agency->bank_branch) ,({{ $agency->bank_branch }})@endif</span></td></tr>
  <tr><td colspan="2" class="c">Particulars/Description of Service (all work orders of the month)</td></tr>
</table>
<table>
  <thead><tr><th style="width:6%">Sl No</th><th style="width:9%">HSN<br>CODE</th><th style="width:22%">DES</th><th style="width:12%">No of<br>Manpower</th><th style="width:13%">Total no of<br>Working Days</th><th style="width:15%">Basic<br>Wages</th><th>Amount<br>(In Rs.)</th></tr></thead>
  <tbody>
  @foreach($c['rows'] as $i => $row)
    <tr><td class="c">{{ $i + 1 }}</td>
      {{-- One HSN cell per row (no rowspan: a cell spanning rows shifted every column left once the table ran onto the next page, owner 22 Nov 2026); the code in the first row, inner lines hidden so it reads as one box. --}}
      <td class="c" style="{{ $i > 0 ? 'border-top:0;' : '' }}{{ ! $loop->last ? 'border-bottom:0;' : '' }}">{{ $i === 0 ? ($c['taxes']->first()->contract?->sac_code ?: '998514') : '' }}</td>
      <td>{{ strtoupper($row['designation']) }}</td>
      <td class="c">{{ $row['manpower'] }}</td><td class="c">{{ $row['days'] }}</td><td class="c">{{ $row['perDay'] }}</td><td class="r">{{ $row['amount'] }}</td></tr>
  @endforeach
  <tr><td></td><td></td><td></td><td class="c">{{ $c['manpower'] }}</td><td class="c">{{ $c['days'] }}</td><td></td><td class="r b">{{ $t['wages'] }}</td></tr>
  @if($c['epfSplit'])
    <tr><td></td><td colspan="5">EPF@12%</td><td class="r">{{ $c['epfSplit']['epf'] }}</td></tr>
    <tr><td></td><td colspan="5">EDIL@0.5%</td><td class="r">{{ $c['epfSplit']['edli'] }}</td></tr>
    <tr><td></td><td colspan="5">PF Administrative Charges @ 0.5%</td><td class="r">{{ $c['epfSplit']['admin'] }}</td></tr>
  @else
    <tr><td></td><td colspan="5">EPF</td><td class="r">{{ $t['epf'] }}</td></tr>
  @endif
  <tr><td></td><td colspan="5">ESI</td><td class="r">{{ $t['esic'] }}</td></tr>
  <tr><td></td><td colspan="5">Sub Total</td><td class="r">{{ $t['subtotal'] }}</td></tr>
  <tr><td>Add</td><td colspan="5">Service Charge @ {{ $c['serviceCharge'] }}% on sub total{{ $c['includesGst'] ? ' (Excluding GST)' : '' }}</td><td class="r">{{ $t['serviceCharge'] }}</td></tr>
  <tr><td></td><td colspan="5">Total</td><td class="r">{{ $t['taxable'] }}</td></tr>
  @if($c['igst'])
    <tr><td></td><td colspan="5">GST @ {{ $c['gst'] }}% (IGST)</td><td class="r">{{ $t['igst'] }}</td></tr>
  @else
    <tr><td style="border-bottom:0"></td><td colspan="3" style="border-bottom:0">GST @ {{ $c['gst'] }}%</td><td colspan="2">CGST{{ '@'.$gstHalf }}%</td><td class="r">{{ $t['cgst'] }}</td></tr>
    <tr><td style="border-top:0"></td><td colspan="3" style="border-top:0"></td><td colspan="2">SGST{{ '@'.$gstHalf }}%</td><td class="r">{{ $t['sgst'] }}</td></tr>
  @endif
  <tr><td></td><td colspan="5">GRAND TOTAL</td><td class="r b">{{ $t['total'] }}</td></tr>
  <tr><td></td><td colspan="5">Total of the tax invoices (each rounded off)</td><td class="r b">{{ $t['roundedTotal'] }}</td></tr>
  <tr><td></td><td colspan="6" class="b">In Words: ( {{ \App\Support\AmountInWords::rupees($t['roundedTotal']) }})</td></tr>
  </tbody>
</table>
<div style="margin-top:4px;font-size:{{ $fit['font'] - 1 }}px" class="i">This statement consolidates the tax invoices listed above for {{ $month->format('F Y') }}; it is not a separate tax invoice. GST is charged on those tax invoices.</div>
<table class="nb" style="margin-top:{{ $fit['gap'] }}px;page-break-inside:avoid"><tr><td></td><td class="r b i" style="width:40%">{{ strtoupper($agency->name) }}</td></tr><tr><td></td><td class="r b">Authorized Signatory</td></tr></table>
</body></html>
