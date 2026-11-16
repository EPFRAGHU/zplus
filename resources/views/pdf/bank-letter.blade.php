{{-- Salary transfer letter to the agency's bank, on the agency's own letterhead (SWACHHA or Z Plus).
     One project, or every project of the agency for the month (Bank Letter module).
     SBI accounts: Emp ID, name, amount. Other banks (NEFT): bank, account no., IFSC, amount. --}}
@php($rs = fn (int $paise) => number_format($paise / 100, 2))
@php($one = $sheets->count() === 1 ? $sheets->first() : null)
@php($contracts = $sheets->map(fn ($s) => $s->project->contract)->unique('id'))
@php($count = $sbi->count() + $neft->count())
<html><head><meta charset="utf-8">@include('pdf._style')
<style>@page{margin:20px 30px 60px} .grid td,.grid th{padding:4px 5px;font-size:9.5px} .grid td.n{text-align:right;white-space:nowrap} p{margin:4px 0}</style></head><body>
@include('pdf._letterhead-footer')
@include('pdf._letterhead')
<table style="font-size:11px;margin:6px 0"><tr><td>Ref. No.: {{ $reference }}</td><td class="r">Date: {{ now()->format('d.m.Y') }}</td></tr></table>
<p>To,<br>The Branch Manager,<br>{{ $agency->bank_name ?: '____________________' }}{{ $agency->bank_branch ? ', '.$agency->bank_branch : '' }}</p>
@if($one)
  <p style="margin-top:8px"><b>Sub: Transfer of salary for the month of {{ $month->format('F Y') }} – {{ $one->project->title ?: $one->project->name }} ({{ $one->project->code }}).</b></p>
@else
  <p style="margin-top:8px"><b>Sub: Transfer of salary of our employees for the month of {{ $month->format('F Y') }} ({{ $count }} {{ \Illuminate\Support\Str::plural('employee', $count) }}, projects: {{ $sheets->map(fn ($s) => $s->project->code)->implode(', ') }}).</b></p>
@endif
<p style="margin-left:28px">{{ $contracts->map(fn ($k) => ($k->contract_type === 'offline' ? 'Work order' : 'GeM contract').' no. '.$k->contract_no)->implode('; ') }}.</p>
<p>Sir / Madam,</p>
<p style="text-align:justify">Kindly debit our current account no. <b>{{ $agency->bank_account_no ?: '____________' }}</b> by <b>₹{{ $rs($total) }}</b> ({{ $totalWords }}) and credit the salaries of our employees as listed below. The amounts are net of employee EPF and ESIC deductions.</p>

@if($sbi->isNotEmpty())
<div class="b" style="margin-top:8px;font-size:11px">A. Accounts in State Bank of India (transfer within the bank)</div>
<table class="grid">
  <thead><tr><th style="width:28px">Sl.</th><th>Emp ID</th><th>Name of the employee</th>@unless($one)<th>Project</th>@endunless<th>Account no.</th><th>Amount (₹)</th></tr></thead>
  <tbody>
  @foreach($sbi as $i => $l)
    <tr><td class="c">{{ $i + 1 }}</td><td class="mono">{{ $l['employee']->emp_code }}</td><td>{{ $l['employee']->account_holder ?: $l['employee']->name }}</td>@unless($one)<td>{{ $l['project']->code }}</td>@endunless<td class="mono">{{ $l['employee']->account_number }}</td><td class="n">{{ $rs($l['net']) }}</td></tr>
  @endforeach
  <tr><td colspan="{{ $one ? 4 : 5 }}" class="r b">Total (SBI, {{ $sbi->count() }})</td><td class="n b">{{ $rs($sbi->sum('net')) }}</td></tr>
  </tbody>
</table>
@endif

@if($neft->isNotEmpty())
<div class="b" style="margin-top:10px;font-size:11px">{{ $sbi->isNotEmpty() ? 'B' : 'A' }}. Accounts in other banks (NEFT)</div>
<table class="grid">
  <thead><tr><th style="width:28px">Sl.</th><th>Emp ID</th><th>Name of the employee</th>@unless($one)<th>Project</th>@endunless<th>Bank</th><th>Account no.</th><th>IFSC</th><th>Amount (₹)</th></tr></thead>
  <tbody>
  @foreach($neft as $i => $l)
    <tr><td class="c">{{ $i + 1 }}</td><td class="mono">{{ $l['employee']->emp_code }}</td><td>{{ $l['employee']->account_holder ?: $l['employee']->name }}</td>@unless($one)<td>{{ $l['project']->code }}</td>@endunless<td>{{ $l['employee']->bank_name }}</td>
      <td class="mono">{{ $l['employee']->account_number }}</td><td class="mono">{{ $l['employee']->ifsc }}</td><td class="n">{{ $rs($l['net']) }}</td></tr>
  @endforeach
  <tr><td colspan="{{ $one ? 6 : 7 }}" class="r b">Total (NEFT, {{ $neft->count() }})</td><td class="n b">{{ $rs($neft->sum('net')) }}</td></tr>
  </tbody>
</table>
@endif

<table class="grid" style="margin-top:8px"><tr><td class="b">Grand total to be debited ({{ $count }} {{ \Illuminate\Support\Str::plural('employee', $count) }})</td><td class="n b" style="width:110px">₹{{ $rs($total) }}</td></tr></table>

@if($missing->isNotEmpty())
<p style="margin-top:8px;font-size:9.5px;color:#a00">Not included – bank details missing in the software: {{ $missing->map(fn ($l) => $l['employee']->emp_code.' '.$l['employee']->name.' (₹'.$rs($l['net']).')')->implode('; ') }}.</p>
@endif

<p style="margin-top:12px">Thanking you,</p>
<table style="margin-top:34px;page-break-inside:avoid"><tr><td></td><td class="r" style="width:45%">For {{ $agency->name }}<br><br><br>{{ $agency->signatory_name ?: 'Authorised Signatory' }}<br>{{ $agency->signatory_title }}</td></tr></table>
</body></html>
