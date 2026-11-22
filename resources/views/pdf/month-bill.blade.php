{{-- Month bill (owner, 22 Nov 2026): internal summary of one agency's month, one row per work order. Not a GST invoice.
     Text header like the agencies' own tax invoice; landscape A4; figures as stored on the sub-bills and tax invoices. --}}
@php($money = fn ($v) => number_format((float) $v, 2, '.', ''))
@php($igst = (float) $total['igst'] > 0)
@php($address = trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)))
@php($clients = $rows->map(fn ($r) => $r['contract']->client?->name)->filter()->unique()->implode('; '))
@php($small = $rows->count() > 12)
<html><head><meta charset="utf-8">
<style>
  @page { margin: 22px 26px 26px; }
  body { font-family: "DejaVu Serif", serif; font-size: {{ $small ? 7.5 : 8.5 }}px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: {{ $small ? '2px 3px' : '3px 4px' }}; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; background: #f1f1f1; }
  .r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .nb td { border: 0; padding: 1px 0; }
  .tot td { font-weight: bold; background: #e9e9e9; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:5px 4px">
    <div class="b" style="font-size:17px;letter-spacing:1px">"{{ strtoupper($agency->name) }}"</div>
    <div class="b i">{{ $address }}</div>
    <div class="b i">@if($agency->gstin)GSTIN- {{ $agency->gstin }}@endif</div>
  </td></tr>
  <tr><td class="c b" style="font-size:12px">MONTH BILL (SUMMARY OF TAX INVOICES) – {{ strtoupper($month->format('F Y')) }}</td></tr>
  <tr><td><b>Client:</b> {{ $clients }} &nbsp;&nbsp; <b>Work orders:</b> {{ $total['work_orders'] }} &nbsp;&nbsp; <b>Sub-bills:</b> {{ $total['sub_bills'] }} &nbsp;&nbsp; <b>Tax invoices:</b> {{ $total['tax_invoices'] }} &nbsp;&nbsp; <b>Staff billed:</b> {{ $total['staff'] }}</td></tr>
</table>
<br>
<table>
  <thead><tr><th style="width:3%">Sl</th><th style="width:13%">GeM contract / work order</th><th style="width:9%">Tax invoice no.</th><th>Sub-bills</th><th>Staff</th><th>Wages</th><th>EPF</th><th>ESIC</th>
    <th>Service charge</th><th>Before GST</th><th>CGST</th><th>SGST</th>@if($igst)<th>IGST</th>@endif<th>Sub-bills total</th><th>Tax invoice amount</th></tr></thead>
  <tbody>
  @foreach($rows as $n => $r)
    <tr><td class="c">{{ $n + 1 }}</td><td>{{ $r['contract']->contract_no }}</td>
      <td>@forelse($r['tax_invoices'] as $t){{ $t->number }}@if(! $loop->last)<br>@endif @empty <i>not made yet</i>@endforelse</td>
      <td class="c">{{ $r['sub_bills'] }}</td><td class="c">{{ $r['staff'] }}</td>
      <td class="r">{{ $money($r['wages']) }}</td><td class="r">{{ $money($r['epf']) }}</td><td class="r">{{ $money($r['esic']) }}</td>
      <td class="r">{{ $money($r['service_charge']) }}</td><td class="r">{{ $money($r['taxable']) }}</td>
      <td class="r">{{ $money($r['cgst']) }}</td><td class="r">{{ $money($r['sgst']) }}</td>@if($igst)<td class="r">{{ $money($r['igst']) }}</td>@endif
      <td class="r">{{ $money($r['rounded_total']) }}</td><td class="r b">{{ $money($r['tax_amount']) }}</td></tr>
  @endforeach
  <tr class="tot"><td colspan="3">MONTH TOTAL</td><td class="c">{{ $total['sub_bills'] }}</td><td class="c">{{ $total['staff'] }}</td>
    <td class="r">{{ $money($total['wages']) }}</td><td class="r">{{ $money($total['epf']) }}</td><td class="r">{{ $money($total['esic']) }}</td>
    <td class="r">{{ $money($total['service_charge']) }}</td><td class="r">{{ $money($total['taxable']) }}</td>
    <td class="r">{{ $money($total['cgst']) }}</td><td class="r">{{ $money($total['sgst']) }}</td>@if($igst)<td class="r">{{ $money($total['igst']) }}</td>@endif
    <td class="r">{{ $money($total['rounded_total']) }}</td><td class="r">{{ $money($total['tax_amount']) }}</td></tr>
  <tr><td colspan="{{ $igst ? 15 : 14 }}" class="b">Total of the tax invoices of {{ $month->format('F Y') }} in words: ( {{ \App\Support\AmountInWords::rupees($total['tax_amount']) }})</td></tr>
  </tbody>
</table>
@if($total['not_in_tax'])<div style="margin-top:4px" class="i">{{ $total['not_in_tax'] }} sub-bill(s) of this month are not yet in a tax invoice: they are in the sub-bill columns but not in the tax invoice amount.</div>@endif
<div style="margin-top:4px;font-size:7px" class="i">Internal summary of the month's sub-bills and tax invoices; not a tax invoice. Tax invoice amounts are worked out on each work order's combined sub-total, so they can differ by a few paise from the sum of its sub-bills.</div>
<table class="nb" style="margin-top:18px;page-break-inside:avoid"><tr><td></td><td class="r b i" style="width:35%">{{ strtoupper($agency->name) }}</td></tr><tr><td></td><td class="r b">Authorized Signatory</td></tr></table>
</body></html>
