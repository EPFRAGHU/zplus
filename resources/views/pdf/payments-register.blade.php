{{-- Payments registers (receipts, outstanding, TDS) for printing (owner, 22 Nov 2026). Landscape; no cell spans rows. --}}
@php($address = $agency ? trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)) : null)
<html><head><meta charset="utf-8">
<style>
  @page { margin: 20px 24px 24px; }
  body { font-family: "DejaVu Serif", serif; font-size: 8px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: 2.5px 3px; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; background: #f1f1f1; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .grand td { font-weight: bold; background: #ddd; border-top: 1.5px solid #111; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:5px 4px">
    <div class="b" style="font-size:15px;letter-spacing:1px">"{{ strtoupper($agency?->name ?? 'Both agencies') }}"</div>
    @if($address)<div class="b i">{{ $address }}</div>@endif
    @if($agency?->gstin)<div class="b i">GSTIN- {{ $agency->gstin }}</div>@endif
  </td></tr>
  <tr><td class="c b" style="font-size:12px">{{ strtoupper($title) }} – {{ strtoupper($period) }}</td></tr>
  <tr><td>@if($contract)<b>Work order:</b> {{ $contract->contract_no }} &nbsp;&nbsp; @endif<span class="i">Rows: {{ count($rows) }}. Printed {{ now()->timezone('Asia/Kolkata')->format('d.m.Y h:i A') }}</span></td></tr>
</table>
<br>
<table>
  <thead><tr>@foreach(array_keys($head) as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
  <tbody>
  @forelse($rows as $row)
    <tr>@foreach(array_values($row) as $i => $v)<td class="{{ array_values($head)[$i] === 'money' ? 'r' : '' }}">{{ array_values($head)[$i] === 'money' && $v !== '' ? number_format((float) $v, 2, '.', '') : $v }}</td>@endforeach</tr>
  @empty
    <tr><td colspan="{{ count($head) }}" class="c">Nothing for this period.</td></tr>
  @endforelse
  @if($foot && $rows)
    <tr class="grand">@foreach(array_values($foot) as $i => $v)<td class="{{ array_values($head)[$i] === 'money' ? 'r' : '' }}">{{ array_values($head)[$i] === 'money' && $v !== '' ? number_format((float) $v, 2, '.', '') : $v }}</td>@endforeach</tr>
  @endif
  </tbody>
</table>
</body></html>
