@php($rs = fn ($v) => number_format((float) $v, 2))
@php($inter = (float) $tax->igst > 0)
<html><head><meta charset="utf-8">@include('pdf._style')
<style>.grid td,.grid th{font-size:9.5px;padding:3px 4px} .box td{border:1px solid #555;padding:3px 5px;font-size:10px;vertical-align:top}</style></head><body>
@include('pdf._letterhead-footer')
@include('pdf._letterhead')
<div class="c b" style="font-size:14px;border:1px solid #555;padding:3px;margin-bottom:-1px">TAX INVOICE</div>
<table class="box">
  <tr>
    <td style="width:50%">Invoice No.: <b>{{ $tax->number }}</b><br>Invoice date: {{ $tax->invoice_date->format('d/m/Y') }}<br>Invoice month: {{ $tax->month->format('M-Y') }}<br>Reverse Charge (Y/N): N<br>State: Odisha &nbsp; Code: 21</td>
    <td>Transport Mode: —<br>Vehicle number: —<br>Date of Supply: {{ $tax->month->format('M-Y') }}<br>Place of Supply: {{ $tax->place_of_supply }}</td>
  </tr>
  <tr>
    <td><b>Bill to Party</b><br>Name: {{ $client->name }}<br>{{ $client->addressee ?? 'The Administrative Officer' }},<br>{!! nl2br(e($client->billing_address)) !!}<br>GSTIN: {{ $client->gstin ?? '—' }}<br>State: ODISHA &nbsp; Code: {{ $client->state_code }}</td>
    <td><b>Ship to Party</b><br>Name: {{ $client->name }}<br>Address: as Bill to Party<br>GSTIN: {{ $client->gstin ?? '—' }}<br>State: ODISHA &nbsp; Code: {{ $client->state_code }}</td>
  </tr>
</table>
<br>
<table class="grid">
  <thead>
    <tr><th rowspan="2">S. No.</th><th rowspan="2">Product description</th><th rowspan="2">HSN<br>Code</th><th rowspan="2">Sub-bill no.</th><th rowspan="2">Taxable value</th>
      @if($inter)<th colspan="2">IGST</th>@else<th colspan="2">CGST</th><th colspan="2">SGST</th>@endif<th rowspan="2">Total</th></tr>
    <tr>@if($inter)<th>Rate</th><th>Amount</th>@else<th>Rate</th><th>Amount</th><th>Rate</th><th>Amount</th>@endif</tr>
  </thead>
  <tbody>
  @foreach($tax->subBills as $b)
    @php($half = (float) $b->gst_percent / 2)
    <tr><td class="c">{{ $loop->iteration }}</td><td>Providing Manpower – {{ $b->project->name }}</td><td class="c">{{ $b->contract->sac_code }}</td><td class="c">{{ $b->invoice_no }}</td>
      <td class="r">{{ $rs($b->taxable) }}</td>
      @if($inter)<td class="c">{{ (float) $b->gst_percent }}%</td><td class="r">{{ $rs($b->igst) }}</td>
      @else<td class="c">{{ $half }}%</td><td class="r">{{ $rs($b->cgst) }}</td><td class="c">{{ $half }}%</td><td class="r">{{ $rs($b->sgst) }}</td>@endif
      <td class="r">{{ $rs($b->total) }}</td></tr>
  @endforeach
  <tr class="b"><td colspan="4" class="r">Total</td><td class="r">{{ $rs($tax->taxable) }}</td>
    @if($inter)<td></td><td class="r">{{ $rs($tax->igst) }}</td>@else<td></td><td class="r">{{ $rs($tax->cgst) }}</td><td></td><td class="r">{{ $rs($tax->sgst) }}</td>@endif
    <td class="r">{{ $rs($tax->total) }}</td></tr>
  </tbody>
</table>
<table class="box" style="margin-top:6px">
  <tr>
    <td style="width:55%"><b>Total invoice amount in words</b><br>({{ $tax->amountInWords() }})</td>
    <td style="padding:0"><table style="width:100%">
      <tr><td style="border:0">Total Amount before Tax</td><td class="r" style="border:0">{{ $rs($tax->taxable) }}</td></tr>
      @if($inter)<tr><td style="border:0">Add: IGST</td><td class="r" style="border:0">{{ $rs($tax->igst) }}</td></tr>
      @else<tr><td style="border:0">Add: CGST</td><td class="r" style="border:0">{{ $rs($tax->cgst) }}</td></tr><tr><td style="border:0">Add: SGST</td><td class="r" style="border:0">{{ $rs($tax->sgst) }}</td></tr>@endif
      <tr><td style="border:0">Total Amount after Tax</td><td class="r" style="border:0">{{ $rs($tax->total) }}</td></tr>
      <tr><td style="border:0"><b>Total Amount after Tax: Round Up</b></td><td class="r" style="border:0"><b>{{ $rs($tax->rounded_total) }}</b></td></tr>
    </table></td>
  </tr>
  <tr>
    <td><b>Bank Details</b><br>
      @if($agency->bank_account_no)Bank A/C: {{ $agency->bank_account_no }}<br>Bank IFSC: {{ $agency->bank_ifsc }}@if($agency->bank_name)<br>{{ $agency->bank_name }}{{ $agency->bank_branch ? ', '.$agency->bank_branch : '' }}@endif
      @else<span class="muted">Add the agency's bank details under Agencies → Edit.</span>@endif
      <br><br><b>Terms &amp; conditions</b><br>Payment within {{ $client->payment_term_days }} days of the invoice date.
    </td>
    <td>Certified that the particulars given above are true and correct.<br><b>For {{ strtoupper($agency->name) }}</b>
      <br><br><br><br><table style="width:100%"><tr><td style="border:0">Common Seal</td><td class="r" style="border:0">Authorised signatory</td></tr></table></td>
  </tr>
</table>
</body></html>
