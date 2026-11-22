{{-- Post-wise sub-bills (owner, 22 Nov 2026: all posts in one PDF). A summary line per post, then each post's projects and
     sub-bills, a total per post and the grand total. No cell spans rows, so columns stay in place when the table runs onto
     the next page (a spanning cell shifted every column left there). Internal; read from the sub-bills as made. --}}
@php($money = fn ($v) => number_format((float) $v, 2, '.', ''))
@php($address = $agency ? trim(preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address)) : null)
<html><head><meta charset="utf-8">
<style>
  @page { margin: 20px 24px 24px; }
  body { font-family: "DejaVu Serif", serif; font-size: 8.5px; color: #111; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #333; padding: 2.5px 4px; vertical-align: top; }
  th { font-weight: bold; text-align: center; vertical-align: middle; background: #f1f1f1; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: bold; } .i { font-style: italic; }
  .post td { background: #e8eef9; font-weight: bold; }
  .sub td { font-weight: bold; background: #f4f4f4; border-bottom: 1.5px solid #111; }
  .grand td { font-weight: bold; background: #ddd; border-top: 1.5px solid #111; }
  .nb td { border: 0; padding: 1px 0; }
</style></head><body>
<table>
  <tr><td class="c" style="padding:5px 4px">
    <div class="b" style="font-size:16px;letter-spacing:1px">"{{ strtoupper($agency?->name ?? 'Both agencies') }}"</div>
    @if($address)<div class="b i">{{ $address }}</div>@endif
    @if($agency?->gstin)<div class="b i">GSTIN- {{ $agency->gstin }}</div>@endif
  </td></tr>
  <tr><td class="c b" style="font-size:12px">POST-WISE SUB-BILLS – {{ strtoupper($month->format('F Y')) }}{{ $post ? ' – '.strtoupper($post) : ' – ALL POSTS' }}</td></tr>
  <tr><td><b>Posts:</b> {{ $groups->count() }} &nbsp;&nbsp; <b>Staff billed:</b> {{ $staff }} &nbsp;&nbsp; <b>Wages billed:</b> {{ $money($total) }} &nbsp;&nbsp; <span class="i">For internal use only, not for submission. Printed {{ now()->timezone('Asia/Kolkata')->format('d.m.Y h:i A') }}</span></td></tr>
</table>

@if($groups->count() > 1)
<br>
<table>
  <thead><tr><th style="width:4%">Sl</th><th>Post (summary)</th><th style="width:10%">Projects</th><th style="width:10%">Staff</th><th style="width:12%">Days paid</th><th style="width:16%">Wages (Rs.)</th></tr></thead>
  <tbody>
  @foreach($groups as $n => $g)
    <tr><td class="c">{{ $n + 1 }}</td><td>{{ $g['post'] }}</td><td class="c">{{ $g['rows']->count() }}</td><td class="c">{{ $g['staff'] }}</td><td class="c">{{ $g['days'] }}</td><td class="r">{{ $money($g['amount']) }}</td></tr>
  @endforeach
  <tr class="grand"><td colspan="3">Total · {{ $groups->count() }} posts</td><td class="c">{{ $staff }}</td><td></td><td class="r">{{ $money($total) }}</td></tr>
  </tbody>
</table>
@endif

<br>
<table>
  <thead><tr><th style="width:4%">Sl</th><th style="width:6%">Agency</th><th>Project</th><th style="width:16%">Sub-bill no.</th><th style="width:12%">Rate</th><th style="width:7%">Staff</th><th style="width:9%">Days paid</th><th style="width:13%">Wages (Rs.)</th></tr></thead>
  <tbody>
  @forelse($groups as $n => $g)
    <tr class="post"><td colspan="8">{{ $n + 1 }}. {{ $g['post'] }} – {{ $g['rows']->count() }} project{{ $g['rows']->count() === 1 ? '' : 's' }}</td></tr>
    @foreach($g['rows'] as $i => $r)
      <tr><td class="c">{{ $i + 1 }}</td><td class="c">{{ $r['agency']->code }}</td><td>{{ $r['project']->code }} – {{ $r['project']->name }}</td><td>{{ $r['bill']->invoice_no }}</td>
        <td class="r">{{ $r['rates'] }}</td><td class="c">{{ $r['staff'] }}</td><td class="c">{{ $r['days'] }}</td><td class="r">{{ $money($r['amount']) }}</td></tr>
    @endforeach
    <tr class="sub"><td colspan="5">Total for {{ $g['post'] }}</td><td class="c">{{ $g['staff'] }}</td><td class="c">{{ $g['days'] }}</td><td class="r">{{ $money($g['amount']) }}</td></tr>
  @empty
    <tr><td colspan="8" class="c">No sub-bills for this month.</td></tr>
  @endforelse
  @if($groups->isNotEmpty())
    <tr class="grand"><td colspan="5">GRAND TOTAL · {{ $groups->count() }} posts</td><td class="c">{{ $staff }}</td><td></td><td class="r">{{ $money($total) }}</td></tr>
    <tr><td colspan="8" class="b">Wages billed in words: ( {{ \App\Support\AmountInWords::rupees($total) }})</td></tr>
  @endif
  </tbody>
</table>
</body></html>
