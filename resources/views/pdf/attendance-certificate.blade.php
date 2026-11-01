@php($days = $sheet->month->daysInMonth)
@php($word = ['SAT' => 'SAT', 'SUN' => 'SUN', 'H' => 'HOL'])
<html><head><meta charset="utf-8">@include('pdf._style')
<style>@page{margin:20px 22px} .grid td,.grid th{padding:3px 2px;font-size:8.5px;text-align:center} .grid td.l{text-align:left;padding-left:4px}</style></head><body>
<div class="c b" style="font-size:12px">{{ strtoupper($sheet->project->contract->client->name) }}</div>
<div class="c">Programme / Project: <b>{{ $sheet->project->title ?: $sheet->project->name }}</b></div>
<div class="c b" style="margin:4px 0 8px">Monthly Attendance and Working Certificate for the Month of {{ $sheet->month->format('F, Y') }}</div>
<table class="grid">
  <thead><tr><th>Sl.</th><th>Name of the Staff</th><th>Designation</th>@for($d = 1; $d <= $days; $d++)<th>{{ $d }}</th>@endfor<th>No. of working days</th></tr></thead>
  <tbody>
  @foreach($sheet->entries->sortBy('employee.name')->values() as $i => $e)
    <tr><td>{{ $i + 1 }}</td><td class="l">{{ $e->employee->name }}</td><td class="l">{{ $e->employee->post?->designation }}</td>
      @for($d = 1; $d <= $days; $d++)@php($c = $e->codes[$d] ?? $e->codes[(string) $d] ?? 'A')<td>{{ $word[$c] ?? $c }}</td>@endfor
      <td class="b">{{ rtrim(rtrim((string) $e->paid_days, '0'), '.') }} days</td></tr>
  @endforeach
  </tbody>
</table>
<p style="font-size:8.5px"># P = Present, CL = Casual Leave, RH = Restricted Holiday, CH = Compensatory Leave/Holiday, A = Absent (unauthorised leave without pay), EL = Earned Leave, T = Tour/Training, L = Leave without pay / leave exhausted, HD = Half day, HOL = Holiday, SAT/SUN = weekly off</p>
@if($showBank)
<table style="margin-top:6px"><tr>
@foreach($sheet->entries->sortBy('employee.name')->values() as $i => $e)
  @php($emp = $e->employee)
  <td style="vertical-align:top;width:33%;padding-right:8px">
    <table class="grid"><tr><th colspan="2" style="text-align:left">BANK DETAILS OF {{ strtoupper($emp->name) }}<br>POST: {{ strtoupper($emp->post?->designation) }}</th></tr>
      <tr><td class="l">Bank name</td><td class="l">{{ $emp->bank_name }}</td></tr>
      <tr><td class="l">IFSC code</td><td class="l">{{ $emp->ifsc }}</td></tr>
      <tr><td class="l">Name as per bank account</td><td class="l">{{ $emp->account_holder ?? $emp->name }}</td></tr>
      <tr><td class="l">A/C no.</td><td class="l">{{ $emp->account_number }}</td></tr></table>
  </td>
  @if(($i + 1) % 3 === 0)</tr><tr>@endif
@endforeach
</tr></table>
@endif
<table class="sign"><tr><td>Date: ____________</td><td class="r">Signature of the PI{{ $sheet->project->pi_name ? ' ('.$sheet->project->pi_name.')' : '' }}</td></tr></table>
</body></html>
