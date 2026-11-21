{{-- Appointment order in a government office style (owner, 21 Nov 2026): numbered terms, statutory deductions, acceptance and copy-to. --}}
@php
  $joined = $employee->joining_date ?? now();
  $title = ['male' => 'Shri', 'female' => 'Ms.'][$employee->gender] ?? '';
  $rs = fn ($paise) => number_format($paise / 100);
  $yesNo = fn ($on) => $on ? 'Applicable' : 'Not applicable';
  $n = 0;
@endphp
<html><head><meta charset="utf-8">@include('pdf._style')
<style>
  .gov{font-family:'DejaVu Serif',serif;font-size:9.4px;line-height:1.32}
  .gov p{margin:0 0 4px;text-align:justify}
  .terms td{vertical-align:top;padding:0 0 3px;text-align:justify}
  .terms td.no{width:22px;font-weight:bold}
  .pay td,.pay th{border:1px solid #000;padding:1px 5px;font-size:8.8px}
  .pay th{background:#efefef}
  .terms .pay td.r,.pay th.r{text-align:right} .terms .pay td.c{text-align:center} .terms .pay td{text-align:left;padding:1px 5px}
  .nb td{border:0}
  .heading{text-align:center;font-weight:bold;font-size:12px;text-decoration:underline;letter-spacing:1px;margin:4px 0 5px}
</style></head><body class="gov">
@include('pdf._letterhead-footer')
<div style="font-family:'DejaVu Sans',sans-serif">@include('pdf._letterhead')</div>
<table><tr><td><b>No. {{ $number }}</b></td><td class="r"><b>Dated: {{ $joined->format('d.m.Y') }}</b></td></tr></table>
<div class="heading">APPOINTMENT ORDER</div>
<p>To,<br>{{ trim($title.' '.$employee->name) }}@if($employee->father_name)<br>{{ $employee->gender === 'female' ? 'D/o' : 'S/o' }} {{ $employee->father_name }}@endif @if($employee->address)<br>{!! nl2br(e($employee->address)) !!}@endif @if($employee->mobile)<br>Mobile: {{ $employee->mobile }}@endif</p>
<table class="nb" style="margin:4px 0 8px"><tr><td style="width:48px;vertical-align:top"><b>Sub:</b></td>
  <td><b>Appointment as {{ $employee->post->designation }} under the project "{{ $project->title ?: $project->name }}" ({{ $project->code }}) – reg.</b></td></tr>
  <tr><td style="vertical-align:top"><b>Ref:</b></td><td>{{ $contract->contract_type === 'offline' ? 'Work order' : 'GeM contract' }} No. {{ $contract->contract_no }}@if($contract->contract_date) dated {{ $contract->contract_date->format('d.m.Y') }}@endif of {{ $contract->client->name }}.</td></tr></table>
<p>With reference to the above, {{ trim($title.' '.$employee->name) }} is hereby appointed as <b>{{ $employee->post->designation }}</b> on outsourcing basis by {{ $agency->name }} for deployment in the above project, on the following terms and conditions:</p>
<table class="terms nb">
  <tr><td class="no">{{ ++$n }}.</td><td><b>Period:</b> The appointment is effective from <b>{{ $joined->format('d.m.Y') }}</b> for one year or till the end of the project / contract period, whichever is earlier, subject to satisfactory performance. It may be extended or curtailed as per project requirements. Either side may end the engagement by giving one month's notice.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Remuneration:</b> @if($daily)Rs. {{ number_format((float) $rate, 2) }} ({{ $rateWords }}) per day of attendance certified by the Project In-charge.@else A consolidated remuneration of <b>Rs. {{ number_format((float) $rate) }}/-</b> ({{ $rateWords }}) per month, paid on the basis of attendance certified by the Project In-charge.@endif The statutory deductions are shown below.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Statutory deductions:</b>
    <table class="pay" style="margin-top:3px;width:100%">
      <tr><th style="text-align:left">Particulars</th><th style="width:22%">Status</th><th style="width:22%" class="r">@if($daily)Rate @else Amount per month (Rs.)@endif</th></tr>
      @unless($daily)<tr><td>Consolidated monthly remuneration</td><td class="c">–</td><td class="r">{{ number_format((float) $rate) }}</td></tr>@endunless
      <tr><td>Less: EPF, employee share @ {{ \App\Support\Statutory::EMPLOYEE_EPF_PERCENT }}% (on wages up to Rs. {{ number_format(\App\Support\Statutory::EPF_WAGE_CEILING) }})</td><td class="c">{{ $yesNo($employee->pf_applicable) }}</td><td class="r">@if($daily){{ $employee->pf_applicable ? \App\Support\Statutory::EMPLOYEE_EPF_PERCENT.'%' : '–' }}@else{{ $employee->pf_applicable ? $rs($cuts['epf']) : '–' }}@endif</td></tr>
      <tr><td>Less: ESI, employee share @ {{ \App\Support\Statutory::EMPLOYEE_ESIC_PERCENT }}%</td><td class="c">{{ $yesNo($employee->esic_applicable) }}</td><td class="r">@if($daily){{ $employee->esic_applicable ? \App\Support\Statutory::EMPLOYEE_ESIC_PERCENT.'%' : '–' }}@else{{ $employee->esic_applicable ? $rs($cuts['esic']) : '–' }}@endif</td></tr>
      <tr><td>Less: TDS @ {{ \App\Support\Statutory::TDS_PERCENT }}%</td><td class="c">{{ $yesNo($employee->tds_applicable ?? true) }}</td><td class="r">@if($daily){{ ($employee->tds_applicable ?? true) ? \App\Support\Statutory::TDS_PERCENT.'%' : '–' }}@else{{ ($employee->tds_applicable ?? true) ? $rs($cuts['tds']) : '–' }}@endif</td></tr>
      @unless($daily)<tr><td><b>Net monthly payment (for a full month)</b></td><td class="c">–</td><td class="r"><b>{{ $rs($cuts['net']) }}</b></td></tr>@endunless
    </table>
    <div style="font-size:9px;margin-top:2px">Deductions are worked out every month on the wages actually earned. The employer's EPF ({{ rtrim(rtrim((string) $contract->epf_percent, '0'), '.') }}%) and ESI ({{ rtrim(rtrim((string) $contract->esic_percent, '0'), '.') }}%) contributions, where applicable, are borne separately and are not deducted from the remuneration.@if($employee->uan) UAN: {{ $employee->uan }}.@endif @if($employee->esic_ip) ESI IP No.: {{ $employee->esic_ip }}.@endif</div></td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Place of posting:</b> {{ $project->location ?: $contract->client->name }}. The appointee shall report for duty on {{ $joined->format('d.m.Y') }} or within seven working days of receipt of this order, and may be assigned work at any location of the project as required by the Project In-charge.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Leave and attendance:</b> Attendance will be maintained and certified monthly by the Project In-charge{{ $project->pi_name ? ' ('.$project->pi_name.')' : '' }}. Unauthorised absence will not be paid.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Medical fitness and documents:</b> The appointment is subject to medical fitness and verification of the original certificates, identity proof and bank details.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Confidentiality:</b> All project information, documents and data shall be kept strictly confidential and shall not be disclosed to any unauthorised person, media or third party without prior written permission.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Conduct:</b> The appointee shall abide by the rules and instructions of the project and perform the duties sincerely. Misconduct, negligence, unauthorised absence or breach of confidentiality shall lead to termination without notice.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td><b>Nature of appointment:</b> This is a purely contractual, outsourced engagement for the project and confers no right to regular employment, regularisation or absorption in {{ $agency->name }}, {{ $contract->client->name }} or any other organisation.</td></tr>
  <tr><td class="no">{{ ++$n }}.</td><td>The appointee is requested to sign and return the duplicate copy of this order in token of acceptance of the above terms and conditions.</td></tr>
</table>
<table class="nb" style="margin-top:4px;page-break-inside:avoid"><tr><td></td><td class="c" style="width:42%">For <b>{{ $agency->name }}</b><br><br>{{ $agency->signatory_name ?: 'Authorised Signatory' }}@if($agency->signatory_title)<br>{{ $agency->signatory_title }}@endif</td></tr></table>
<p style="margin-top:3px"><b>Copy to:</b><br>1. The Project In-charge{{ $project->pi_name ? ', '.$project->pi_name : '' }}, project "{{ $project->name }}", {{ $contract->client->name }}, for information.<br>2. Personal file of the appointee.</p>
<div style="border-top:1px dashed #000;margin-top:4px;padding-top:3px;page-break-inside:avoid">
  <div class="c b" style="text-decoration:underline">ACCEPTANCE</div>
  <p>I, {{ $employee->name }}, have read and understood the terms and conditions of this appointment order No. {{ $number }} and accept them.</p>
  <table class="nb"><tr><td>Signature: ____________________</td><td>Date: ______________</td><td class="r">Place: ______________</td></tr></table>
</div>
</body></html>
