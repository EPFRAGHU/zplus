{{-- Forwarding letter to the agency's bank: salary of every employee of one project for one month.
     SBI accounts: Emp ID, name, amount. Other banks (NEFT): name, account no., IFSC, amount. --}}
@php($rs = fn (int $paise) => number_format($paise / 100, 2))
@php($k = $sheet->project->contract)
<html><head><meta charset="utf-8">@include('pdf._style')
<style>@page{margin:20px 30px 60px} .grid td,.grid th{padding:4px 5px;font-size:10px} .grid td.n{text-align:right;white-space:nowrap} p{margin:4px 0}</style></head><body>
@include('pdf._letterhead-footer')
@include('pdf._letterhead')
<table style="font-size:11px;margin:6px 0"><tr><td>Ref. No.: {{ $reference }}</td><td class="r">Date: {{ now()->format('d.m.Y') }}</td></tr></table>
<p>To,<br>The Branch Manager,<br>{{ $agency->bank_name ?: '____________________' }}{{ $agency->bank_branch ? ', '.$agency->bank_branch : '' }}</p>
<p style="margin-top:8px"><b>Sub: Transfer of salary for the month of {{ $sheet->month->format('F Y') }} – {{ $sheet->project->title ?: $sheet->project->name }} ({{ $sheet->project->code }}).</b></p>
<p style="margin-left:28px">{{ $k->contract_type === 'offline' ? 'Work order' : 'GeM contract' }} no. {{ $k->contract_no }}, {{ $k->client->name }}.</p>
<p>Sir / Madam,</p>
<p style="text-align:justify">Kindly debit our current account no. <b>{{ $agency->bank_account_no ?: '____________' }}</b> by <b>₹{{ $rs($total) }}</b> ({{ $totalWords }}) and credit the salaries of our employees as listed below. The amounts are net of employee EPF and ESIC deductions.</p>

@if($sbi->isNotEmpty())
<div class="b" style="margin-top:8px;font-size:11px">A. Accounts in State Bank of India (transfer within the bank)</div>
<table class="grid">
  <thead><tr><th style="width:30px">Sl.</th><th>Emp ID</th><th>Name of the employee</th><th>Account no.</th><th>Amount (₹)</th></tr></thead>
  <tbody>
  @foreach($sbi as $i => $l)
    <tr><td class="c">{{ $i + 1 }}</td><td class="mono">{{ $l['employee']->emp_code }}</td><td>{{ $l['employee']->account_holder ?: $l['employee']->name }}</td><td class="mono">{{ $l['employee']->account_number }}</td><td class="n">{{ $rs($l['net']) }}</td></tr>
  @endforeach
  <tr><td colspan="4" class="r b">Total (SBI)</td><td class="n b">{{ $rs($sbi->sum('net')) }}</td></tr>
  </tbody>
</table>
@endif

@if($neft->isNotEmpty())
<div class="b" style="margin-top:10px;font-size:11px">{{ $sbi->isNotEmpty() ? 'B' : 'A' }}. Accounts in other banks (NEFT)</div>
<table class="grid">
  <thead><tr><th style="width:30px">Sl.</th><th>Emp ID</th><th>Name of the employee</th><th>Bank</th><th>Account no.</th><th>IFSC</th><th>Amount (₹)</th></tr></thead>
  <tbody>
  @foreach($neft as $i => $l)
    <tr><td class="c">{{ $i + 1 }}</td><td class="mono">{{ $l['employee']->emp_code }}</td><td>{{ $l['employee']->account_holder ?: $l['employee']->name }}</td><td>{{ $l['employee']->bank_name }}</td>
      <td class="mono">{{ $l['employee']->account_number }}</td><td class="mono">{{ $l['employee']->ifsc }}</td><td class="n">{{ $rs($l['net']) }}</td></tr>
  @endforeach
  <tr><td colspan="6" class="r b">Total (NEFT)</td><td class="n b">{{ $rs($neft->sum('net')) }}</td></tr>
  </tbody>
</table>
@endif

<table class="grid" style="margin-top:8px"><tr><td class="b">Grand total to be debited ({{ $sbi->count() + $neft->count() }} employees)</td><td class="n b" style="width:110px">₹{{ $rs($total) }}</td></tr></table>

@if($missing->isNotEmpty())
<p style="margin-top:8px;font-size:9.5px;color:#a00">Not included – bank details missing in the software: {{ $missing->map(fn ($l) => $l['employee']->emp_code.' '.$l['employee']->name.' (₹'.$rs($l['net']).')')->implode('; ') }}.</p>
@endif

<p style="margin-top:12px">Thanking you,</p>
<table style="margin-top:34px"><tr><td></td><td class="r" style="width:45%">For {{ $agency->name }}<br><br><br>{{ $agency->signatory_name ?: 'Authorised Signatory' }}<br>{{ $agency->signatory_title }}</td></tr></table>
</body></html>
