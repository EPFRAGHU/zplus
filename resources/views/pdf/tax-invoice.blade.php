{{-- Tax invoice in the agencies' own format (SWACHHA tax invoice, October 2026): one per work order and month.
     Every figure is exact (as in their Excel) and only shown to 2 decimals; the grand total is rounded at the end.
     $t: BillTotals over all the sub-bills' staff; $p: particulars rows (posts of each project). --}}
@php($k = $tax->contract ?? $tax->subBills->first()?->contract)
@php($regs = (string) $agency->registrations)
@php($reg = fn (string $label) => preg_match('/'.$label.'[^:\-\n]*[:\-]\s*([A-Z0-9\/]+)/i', $regs, $m) ? $m[1] : null)
@php($epfNo = $reg('EPF'))
@php($esicNo = $reg('ESIC'))
@php($phones = $agency->phones ?: $agency->phone)
@php($emails = $agency->letterhead_emails ?: $agency->email)
@php($address = trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)))
@php($first = $tax->subBills->first())
@php($sc = rtrim(rtrim((string) $first?->service_charge_percent, '0'), '.'))
@php($gstHalf = rtrim(rtrim(number_format((float) $first?->gst_percent / 2, 2), '0'), '.'))
<html><head><meta charset="utf-8">
<style>
  @page { margin: 26px 34px 40px; }
  body { font-family: "DejaVu Serif", serif; font-size: 10px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: 3px 5px; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; }
  .r { text-align: right; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .nb td { border: 0; padding: 1px 0; }
  .mid { vertical-align: middle; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:6px 4px">
    <div class="b" style="font-size:20px;letter-spacing:1px">"{{ strtoupper($agency->name) }}"</div>
    <div class="b i">{{ $address }}</div>
    <div class="b i">@if($agency->gstin)GSTIN- {{ $agency->gstin }}@endif @if($agency->pan), PAN NO- {{ $agency->pan }},@endif</div>
    @if($epfNo || $esicNo)<div class="b i">@if($epfNo)EPF NO- {{ $epfNo }}@endif @if($esicNo), ESIC NO- {{ $esicNo }}@endif</div>@endif
    <div class="b i">@if($phones)MOB- {{ $phones }}, @endif @if($emails)&nbsp; Email ID- {{ $emails }}@endif</div>
  </td></tr>
  <tr><td class="c b" style="font-size:14px">TAX INVOICE</td></tr>
</table>
<table>
  <tr><td style="width:60%;border-top:0">Invoice No- {{ $tax->number }}</td><td style="border-top:0;width:16%"></td><td style="border-top:0">Date:- {{ $tax->invoice_date->format('d.m.Y') }}</td></tr>
  <tr><td colspan="3" class="b" style="border-top:0">{{ ($k?->contract_type === 'offline') ? 'WORK ORDER NO-' : 'GEM CONTRACT NO-' }}{{ $k?->contract_no }}@if($k?->contract_date), Dated- {{ $k->contract_date->format('d.m.Y') }}@endif</td></tr>
</table>
<br>
<table>
  <tr><td style="width:55%">Bill for the period from :-</td><td>Month- {{ strtoupper($tax->month->format('M-Y')) }}</td></tr>
  <tr><td class="b i" style="border-bottom:0">FROM</td><td class="b i" style="border-bottom:0">TO</td></tr>
  <tr><td class="b" style="border-top:0">{{ strtoupper($agency->name) }}<br>{!! nl2br(e($agency->address)) !!}@if($agency->gstin)<br>GSTIN- {{ $agency->gstin }}@endif @if($agency->phone)<br>MOB- {{ $agency->phone }}@endif</td>
      <td class="b" style="border-top:0">{{ $client->addressee ?: 'The Administrative Officer' }}<br>{!! nl2br(e($client->billing_address ?: $client->name)) !!}@if($client->gstin)<br>GSTIN- {{ $client->gstin }}@endif</td></tr>
  <tr><td colspan="2" class="c b" style="font-size:12px">Category of Service: Manpower Service</td></tr>
  <tr><td colspan="2" class="b">Bank Detail of Company:- &nbsp;Account Holder- {{ strtoupper($agency->name) }}<br>
    <span style="padding-left:150px">Bank A/C: {{ $agency->bank_account_no ?: '____________' }} &nbsp;Bank IFSC: {{ $agency->bank_ifsc ?: '________' }},</span><br>
    <span style="padding-left:150px">Bank Name: {{ $agency->bank_name ?: '________' }}@if($agency->bank_branch) ,({{ $agency->bank_branch }})@endif</span></td></tr>
  <tr><td colspan="2" class="c">Particulars/Description of Service</td></tr>
</table>
<table>
  <thead><tr><th style="width:6%">Sl No</th><th style="width:9%">HSN<br>CODE</th><th style="width:22%">DES</th><th style="width:12%">No of<br>Manpower</th><th style="width:13%">Total no of<br>Working Days</th><th style="width:15%">Basic<br>Wages</th><th>Amount<br>(In Rs.)</th></tr></thead>
  <tbody>
  @foreach($p['rows'] as $i => $row)
    <tr><td class="c">{{ $i + 1 }}</td>
      @if($i === 0)<td class="c mid" rowspan="{{ count($p['rows']) }}">{{ $k?->sac_code ?: '998514' }}</td>@endif
      <td>{{ strtoupper($row['designation']) }}</td>
      <td class="c">{{ $row['manpower'] }}</td><td class="c">{{ $row['days'] }}</td><td class="c">{{ $row['perDay'] }}</td><td class="r">{{ $row['amount'] }}</td></tr>
  @endforeach
  <tr><td></td><td></td><td></td><td class="c">{{ $p['manpower'] }}</td><td class="c">{{ $p['days'] }}</td><td></td><td class="r b">{{ $t->wages }}</td></tr>
  @if($p['epfSplit'])
    <tr><td></td><td colspan="5">EPF@12%</td><td class="r">{{ $p['epfSplit']['epf'] }}</td></tr>
    <tr><td></td><td colspan="5">EDIL@0.5%</td><td class="r">{{ $p['epfSplit']['edli'] }}</td></tr>
    <tr><td></td><td colspan="5">PF Administrative Charges @ 0.5%</td><td class="r">{{ $p['epfSplit']['admin'] }}</td></tr>
  @else
    <tr><td></td><td colspan="5">EPF{{ '@'.rtrim(rtrim((string) $k?->epf_percent, '0'), '.') }}%</td><td class="r">{{ $t->epf }}</td></tr>
  @endif
  <tr><td></td><td colspan="5">ESI@ {{ rtrim(rtrim((string) $k?->esic_percent, '0'), '.') }}%</td><td class="r">{{ $t->esic }}</td></tr>
  <tr><td></td><td colspan="5">Sub Total</td><td class="r">{{ $t->subtotal }}</td></tr>
  <tr><td>Add</td><td colspan="5">Service Charge @ {{ $sc }}% on sub total{{ $first?->service_charge_includes_gst ? ' (Excluding GST)' : '' }}</td><td class="r">{{ $t->serviceCharge }}</td></tr>
  <tr><td></td><td colspan="5">Total</td><td class="r">{{ $t->taxable }}</td></tr>
  @if((float) $t->igst > 0)
    <tr><td></td><td colspan="5">GST @ {{ rtrim(rtrim((string) $first->gst_percent, '0'), '.') }}% (IGST)</td><td class="r">{{ $t->igst }}</td></tr>
  @else
    <tr><td rowspan="2"></td><td colspan="3" rowspan="2" class="mid">GST @ {{ rtrim(rtrim((string) $first?->gst_percent, '0'), '.') }}%</td><td colspan="2">CGST{{ '@'.$gstHalf }}%</td><td class="r">{{ $t->cgst }}</td></tr>
    <tr><td colspan="2">SGST{{ '@'.$gstHalf }}%</td><td class="r">{{ $t->sgst }}</td></tr>
  @endif
  <tr><td></td><td colspan="5">GRAND TOTAL</td><td class="r b">{{ $t->total }}</td></tr>
  <tr><td></td><td colspan="5">Rounding off</td><td class="r b">{{ $t->roundedTotal }}</td></tr>
  <tr><td></td><td colspan="6" class="b">In Words: ( {{ \App\Support\AmountInWords::rupees($t->roundedTotal) }})</td></tr>
  </tbody>
</table>
@php($leavers = collect($t->lines)->map(fn ($line) => ['line' => $line->ref['line'], 'bill' => $line->ref['bill']])
    ->filter(fn ($r) => $r['line']->employee?->exitIn($r['bill']->month))->values())
@if($leavers->isNotEmpty())
<div style="margin-top:8px;font-size:9px;color:#b02a37"><b>Staff who resigned during {{ $leavers->first()['bill']->month->format('F Y') }} (paid up to the last working day):</b>
  @foreach($leavers as $r){{ $r['line']->employee_name }} – {{ $r['line']->designation }} ({{ $r['bill']->project->code }}), last working day {{ $r['line']->employee->exit_date->format('d.m.Y') }}, {{ rtrim(rtrim((string) $r['line']->paid_days, '0'), '.') }} days paid{{ $loop->last ? '.' : ';' }} @endforeach
</div>
@endif
<table class="nb" style="margin-top:40px;page-break-inside:avoid"><tr><td></td><td class="r b i" style="width:40%">{{ strtoupper($agency->name) }}</td></tr><tr><td></td><td class="r b">Authorized Signatory</td></tr></table>
</body></html>
