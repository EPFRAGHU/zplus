{{-- Post-wise summary of a work order's sub-bills for GeM returns (owner, 22 Nov 2026). Landscape; no cell spans rows, so
     columns stay in place on a next page. Read from the sub-bills as made. --}}
@php
  $m = fn ($v) => number_format((float) $v, 2, '.', '');
  $agency = $contract->agency;
  $address = $agency ? trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)) : null;
  $igst = $data['igst'];
  $cgst = ! $igst || (float) $data['total']['cgst'] != 0;
  $cols = array_keys(array_filter($headings, fn ($h, $k) => ($k !== 'igst' || $igst) && (! in_array($k, ['cgst', 'sgst']) || $cgst), ARRAY_FILTER_USE_BOTH));
  $span = 5 + count($cols);
@endphp
<html><head><meta charset="utf-8">
<style>
  @page { margin: 20px 24px 24px; }
  body { font-family: "DejaVu Serif", serif; font-size: 8.5px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: 3px 4px; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; background: #f1f1f1; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .grand td { font-weight: bold; background: #ddd; border-top: 1.5px solid #111; }
  .stored td { font-style: italic; color: #444; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:5px 4px">
    <div class="b" style="font-size:16px;letter-spacing:1px">"{{ strtoupper($agency?->name ?? '') }}"</div>
    @if($address)<div class="b i">{{ $address }}</div>@endif
    @if($agency?->gstin)<div class="b i">GSTIN- {{ $agency->gstin }}</div>@endif
  </td></tr>
  <tr><td class="c b" style="font-size:12px">POST-WISE SUMMARY OF SUB-BILLS – {{ strtoupper($month->format('F Y')) }}</td></tr>
  <tr><td><b>Client:</b> {{ $contract->client?->name }} &nbsp;&nbsp; <b>GeM contract no.:</b> {{ $contract->contract_no }}@if($contract->bid_no) &nbsp;&nbsp; <b>Bid no.:</b> {{ $contract->bid_no }}@endif</td></tr>
  <tr><td><b>Sub-bill:</b> {{ $bill?->invoice_no ?? 'All ('.$data['bills']->count().'): '.$data['bills']->pluck('invoice_no')->implode(', ') }} &nbsp;&nbsp; <b>Post:</b> {{ $post ?: 'All' }}
    &nbsp;&nbsp; <span class="i">For filing GeM returns. Printed {{ now()->timezone('Asia/Kolkata')->format('d.m.Y h:i A') }}</span></td></tr>
</table>
<br>
<table>
  <thead><tr><th style="width:3%">Sl</th><th>Post</th><th style="width:12%">Sub-bill(s)</th><th style="width:4%">Staff</th><th style="width:5%">Days paid</th>
    @foreach($cols as $k)<th>{{ $headings[$k] }} (Rs.)</th>@endforeach</tr></thead>
  <tbody>
  @foreach($data['rows'] as $n => $r)
    <tr><td class="c">{{ $n + 1 }}</td><td>{{ $r['post'] }}</td><td>{{ $r['bills'] }}</td><td class="c">{{ $r['staff'] }}</td><td class="c">{{ $r['days'] }}</td>
      @foreach($cols as $k)<td class="r">{{ $m($r[$k]) }}</td>@endforeach</tr>
  @endforeach
  <tr class="grand"><td colspan="3">TOTAL · {{ count($data['rows']) }} {{ count($data['rows']) === 1 ? 'post' : 'posts' }}</td><td class="c">{{ $data['total']['staff'] }}</td><td class="c">{{ $data['total']['days'] }}</td>
    @foreach($cols as $k)<td class="r">{{ $m($data['total'][$k]) }}</td>@endforeach</tr>
  @unless($post)
    <tr class="stored"><td colspan="5">As on the sub-bill{{ $data['bills']->count() === 1 ? '' : 's' }} (stored figures)</td>@foreach($cols as $k)<td class="r">{{ $m($data['stored'][$k]) }}</td>@endforeach</tr>
  @endunless
  <tr><td colspan="{{ $span }}" class="b">Total with GST in words: ( {{ \App\Support\AmountInWords::rupees($data['total']['total']) }})</td></tr>
  </tbody>
</table>
<p class="i" style="margin-top:6px">Wages, EPF and ESIC are as billed for each person; the service charge and GST are worked out per post with each sub-bill's own rates. The sub-bill rounds only its grand total.</p>
</body></html>
