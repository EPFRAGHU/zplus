{{-- Sub-bills statement (owner, 22 Nov 2026): every sub-bill of an agency's month in columns, grouped by work order in serial
     order, a total per work order and the month total, as on the Sub-bills page. Internal statement; figures as stored on the bills. --}}
@php($money = fn ($v) => number_format((float) $v, 2, '.', ''))
@php($igst = (float) $total['igst'] > 0)
@php($cols = $igst ? 15 : 14)
@php($address = trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)))
@php($clients = $groups->map(fn ($g) => $g['contract']?->client?->name)->filter()->unique()->implode('; '))
@php($rowsCount = $total['bills'] + $groups->count() * 2)
@php($small = $rowsCount > 28)
<html><head><meta charset="utf-8">
<style>
  @page { margin: 20px 24px 24px; }
  body { font-family: "DejaVu Serif", serif; font-size: {{ $small ? 7 : 8 }}px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: {{ $small ? '1.5px 3px' : '2.5px 4px' }}; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; background: #f1f1f1; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .wo td { background: #e8eef9; font-weight: bold; }
  .sub td { font-weight: bold; background: #f4f4f4; border-bottom: 1.5px solid #111; }
  .grand td { font-weight: bold; background: #ddd; border-top: 1.5px solid #111; }
  .nb td { border: 0; padding: 1px 0; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:5px 4px">
    <div class="b" style="font-size:16px;letter-spacing:1px">"{{ strtoupper($agency->name) }}"</div>
    <div class="b i">{{ $address }}</div>
    <div class="b i">@if($agency->gstin)GSTIN- {{ $agency->gstin }}@endif</div>
  </td></tr>
  <tr><td class="c b" style="font-size:12px">SUB-BILLS STATEMENT – {{ strtoupper($month->format('F Y')) }}</td></tr>
  <tr><td><b>Client:</b> {{ $clients }} &nbsp;&nbsp; <b>Work orders:</b> {{ $groups->count() }} &nbsp;&nbsp; <b>Sub-bills:</b> {{ $total['bills'] }} &nbsp;&nbsp; <b>Staff billed:</b> {{ $total['staff'] }}</td></tr>
</table>
<br>
<table>
  <thead><tr><th style="width:3%">Sl</th><th style="width:12%">Sub-bill no.</th><th>Project</th><th style="width:4%">Staff</th><th>Wages</th><th>EPF</th><th>ESIC</th><th>Service charge</th>
    <th>Before GST</th><th>CGST</th><th>SGST</th>@if($igst)<th>IGST</th>@endif<th>Bill amount</th><th style="width:9%">Tax invoice</th><th style="width:5%">Status</th></tr></thead>
  <tbody>
  @php($sl = 0)
  @foreach($groups as $g)
    <tr class="wo"><td colspan="{{ $cols }}">{{ ($g['contract']?->contract_type === 'offline') ? 'Work order' : 'GeM contract' }} {{ $g['contract']?->contract_no }} – {{ $g['total']['bills'] }} sub-bill{{ $g['total']['bills'] === 1 ? '' : 's' }}</td></tr>
    @foreach($g['bills'] as $b)
      <tr><td class="c">{{ ++$sl }}</td><td>{{ $b->invoice_no }}</td><td>{{ $b->project?->name }}</td><td class="c">{{ $b->lines_count }}</td>
        <td class="r">{{ $money($b->wages) }}</td><td class="r">{{ $money($b->epf) }}</td><td class="r">{{ $money($b->esic) }}</td><td class="r">{{ $money($b->service_charge) }}</td>
        <td class="r">{{ $money($b->taxable) }}</td><td class="r">{{ $money($b->cgst) }}</td><td class="r">{{ $money($b->sgst) }}</td>@if($igst)<td class="r">{{ $money($b->igst) }}</td>@endif
        <td class="r b">{{ $money($b->rounded_total) }}</td><td>{{ $b->taxInvoice?->number ?? '—' }}</td><td class="c">{{ \App\Models\Invoice::STATUSES[$b->status] ?? ucfirst($b->status) }}</td></tr>
    @endforeach
    @php($t = $g['total'])
    <tr class="sub"><td colspan="3">Total {{ $g['contract']?->contract_no }}</td><td class="c">{{ $t['staff'] }}</td>
      <td class="r">{{ $money($t['wages']) }}</td><td class="r">{{ $money($t['epf']) }}</td><td class="r">{{ $money($t['esic']) }}</td><td class="r">{{ $money($t['service_charge']) }}</td>
      <td class="r">{{ $money($t['taxable']) }}</td><td class="r">{{ $money($t['cgst']) }}</td><td class="r">{{ $money($t['sgst']) }}</td>@if($igst)<td class="r">{{ $money($t['igst']) }}</td>@endif
      <td class="r">{{ $money($t['rounded_total']) }}</td><td colspan="2"></td></tr>
  @endforeach
  <tr class="grand"><td colspan="3">MONTH TOTAL · {{ $total['bills'] }} sub-bills</td><td class="c">{{ $total['staff'] }}</td>
    <td class="r">{{ $money($total['wages']) }}</td><td class="r">{{ $money($total['epf']) }}</td><td class="r">{{ $money($total['esic']) }}</td><td class="r">{{ $money($total['service_charge']) }}</td>
    <td class="r">{{ $money($total['taxable']) }}</td><td class="r">{{ $money($total['cgst']) }}</td><td class="r">{{ $money($total['sgst']) }}</td>@if($igst)<td class="r">{{ $money($total['igst']) }}</td>@endif
    <td class="r">{{ $money($total['rounded_total']) }}</td><td colspan="2"></td></tr>
  <tr><td colspan="{{ $cols }}" class="b">Total of the sub-bills of {{ $month->format('F Y') }} in words: ( {{ \App\Support\AmountInWords::rupees($total['rounded_total']) }})</td></tr>
  </tbody>
</table>
<div style="margin-top:4px;font-size:7px" class="i">Internal statement of the month's sub-bills; not a tax invoice. Tax invoices are worked out on each work order's combined sub-total, so they can differ by a few paise from the sum of its sub-bills.</div>
<table class="nb" style="margin-top:16px;page-break-inside:avoid"><tr><td></td><td class="r b i" style="width:35%">{{ strtoupper($agency->name) }}</td></tr><tr><td></td><td class="r b">Authorized Signatory</td></tr></table>
</body></html>
