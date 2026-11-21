<html><head><meta charset="utf-8">@include('pdf._style')
<style>@page{margin:24px 28px 40px} .grid td,.grid th{font-size:9.5px}</style></head><body>
<div class="c b" style="font-size:14px">Post-wise sub-bills – {{ $month->format('F Y') }}</div>
<div class="c">{{ $agency?->name ?? 'Both agencies' }}{{ $post ? ' · Post: '.$post : '' }}</div>
<div class="c muted" style="font-size:9px;margin-bottom:8px">For internal use only. Not for submission. Printed {{ now()->timezone('Asia/Kolkata')->format('d.m.Y h:i A') }}</div>
<table class="grid">
  <thead><tr><th>Post</th><th>Agency</th><th>Project</th><th>Sub-bill no.</th><th>Rate</th><th>Staff</th><th>Days paid</th><th>Wages (Rs.)</th></tr></thead>
  <tbody>
  @forelse($groups as $g)
    @foreach($g['rows'] as $i => $r)
      <tr>@if($i === 0)<td rowspan="{{ $g['rows']->count() + 1 }}" class="b" style="vertical-align:top">{{ $g['post'] }}</td>@endif
        <td class="c">{{ $r['agency']->code }}</td><td>{{ $r['project']->code }} – {{ $r['project']->name }}</td><td>{{ $r['bill']->invoice_no }}</td>
        <td class="r">{{ $r['rates'] }}</td><td class="c">{{ $r['staff'] }}</td><td class="c">{{ $r['days'] }}</td><td class="r">{{ number_format((float) $r['amount'], 2) }}</td></tr>
    @endforeach
    <tr style="background:#f3f3f3"><td colspan="4" class="b">Total for this post</td><td class="c b">{{ $g['staff'] }}</td><td class="c b">{{ $g['days'] }}</td><td class="r b">{{ number_format((float) $g['amount'], 2) }}</td></tr>
  @empty
    <tr><td colspan="8" class="c">No sub-bills for this month.</td></tr>
  @endforelse
  @if($groups->isNotEmpty())<tr><td colspan="5" class="b">Grand total</td><td class="c b">{{ $staff }}</td><td></td><td class="r b">{{ number_format((float) $total, 2) }}</td></tr>@endif
  </tbody>
</table>
</body></html>
