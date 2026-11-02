@php($rs = fn (int $paise) => number_format($paise / 100, 2))
@php($k = $sheet->project->contract)
@php($sum = fn (string $key) => $lines->sum($key))
<html><head><meta charset="utf-8">@include('pdf._style')
<style>@page{margin:20px 22px 52px} .grid td,.grid th{padding:4px 4px;font-size:9.5px} .grid td.n{text-align:right;white-space:nowrap}</style></head><body>
@include('pdf._letterhead-footer')
@include('pdf._letterhead')
<div class="c b" style="font-size:13px">SALARY PAYOUT SHEET – {{ strtoupper($sheet->month->format('F Y')) }}</div>
<table style="font-size:9.5px;margin:4px 0 6px"><tr>
  <td><b>Agency:</b> {{ $agency->name }} ({{ $agency->code }})</td>
  <td class="c"><b>Client:</b> {{ $k->client->name }}</td>
  <td class="c"><b>Project:</b> {{ $sheet->project->title ?: $sheet->project->name }}@if($sheet->project->pi_name) · <b>PI:</b> {{ $sheet->project->pi_name }}@endif</td>
  <td class="r"><b>GeM contract:</b> {{ $k->contract_no }}</td>
</tr></table>
<table class="grid">
  <thead><tr>
    <th>Sl.</th><th>Name of the staff</th><th>Designation</th><th>Paid days</th><th>Rate (₹)</th><th>Earned (₹)</th>
    <th>EPF 12% (₹)</th><th>ESIC 0.75% (₹)</th><th>Net pay (₹)</th><th>Name as per bank</th><th>Bank</th><th>IFSC</th><th>A/c no.</th><th>UAN / ESIC IP</th>
  </tr></thead>
  <tbody>
  @foreach($lines as $i => $l)
    @php($e = $l['employee'])
    <tr><td class="c">{{ $i + 1 }}</td><td>{{ $e->name }}</td><td>{{ $e->post?->designation }}</td>
      <td class="c">{{ rtrim(rtrim($l['paid_days'], '0'), '.') }}</td><td class="n">{{ number_format((float) $l['rate'], 2) }}</td>
      <td class="n">{{ $rs($l['earned']) }}</td><td class="n">{{ $e->pf_applicable ? $rs($l['epf']) : 'Off' }}</td><td class="n">{{ $e->esic_applicable ? $rs($l['esic']) : 'Off' }}</td><td class="n b">{{ $rs($l['net']) }}</td>
      <td>{{ $e->account_holder ?? $e->name }}</td><td>{{ $e->bank_name ?: '—' }}</td><td class="mono">{{ $e->ifsc ?: '—' }}</td><td class="mono">{{ $e->account_number ?: '—' }}</td>
      <td style="font-size:8.5px">{{ $e->uan ?: '—' }}<br>{{ $e->esic_ip ?: '—' }}</td></tr>
  @endforeach
  </tbody>
  <tfoot><tr class="b"><td colspan="5" class="r">Total ({{ $lines->count() }} {{ \Illuminate\Support\Str::plural('person', $lines->count()) }})</td>
    <td class="n">{{ $rs($sum('earned')) }}</td><td class="n">{{ $rs($sum('epf')) }}</td><td class="n">{{ $rs($sum('esic')) }}</td><td class="n">{{ $rs($sum('net')) }}</td><td colspan="5"></td></tr></tfoot>
</table>
<p style="font-size:10px;margin-top:6px"><b>Total net pay to transfer: ₹{{ $rs($sum('net')) }}</b> ({{ \App\Support\AmountInWords::rupees(number_format($sum('net') / 100, 2, '.', '')) }})</p>
<p style="font-size:8.5px;color:#444">Earned = rate × paid days ÷ {{ $sheet->month->daysInMonth }} days (rate × paid days for daily posts), rounded to whole rupees, as on the bill.
  EPF 12% on earned wages up to ₹15,000 and ESIC 0.75% on earned wages (rounded up), deducted only for staff with EPF / ESIC switched on.
  The employer's EPF and ESIC are charged to the client on the bill.</p>
<table class="sign"><tr><td style="vertical-align:bottom">Prepared by: ____________</td><td class="r">For {{ $agency->name }}<br><br><br>Authorised signatory</td></tr></table>
</body></html>
