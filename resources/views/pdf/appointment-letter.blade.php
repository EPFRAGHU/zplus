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
  .hand td{padding:7px 6px;font-size:10px}
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
  <tr><td class="no">{{ ++$n }}.</td><td>The appointee is requested to sign the acceptance letter on the next page and return it to {{ $agency->name }} with the documents listed in it.</td></tr>
</table>
<table class="nb" style="margin-top:4px;page-break-inside:avoid"><tr><td></td><td class="c" style="width:42%">For <b>{{ $agency->name }}</b><br><br>{{ $agency->signatory_name ?: 'Authorised Signatory' }}@if($agency->signatory_title)<br>{{ $agency->signatory_title }}@endif</td></tr></table>
<p style="margin-top:3px"><b>Copy to:</b><br>1. The Project In-charge{{ $project->pi_name ? ', '.$project->pi_name : '' }}, project "{{ $project->name }}", {{ $contract->client->name }}, for information.<br>2. Personal file of the appointee.</p>
{{-- Page 2: the acceptance letter the appointee signs and returns to the agency (owner, 22 Nov 2026), set out like a government letter. --}}
<div style="page-break-before:always;font-size:10.5px;line-height:1.5">
  <div class="heading" style="margin-top:0">ACCEPTANCE OF APPOINTMENT</div>
  <table class="nb" style="margin-bottom:8px"><tr>
    <td style="vertical-align:top;width:55%"><b>From:</b><br>{{ trim($title.' '.$employee->name) }}@if($employee->father_name)<br>{{ $employee->gender === 'female' ? 'D/o' : 'S/o' }} {{ $employee->father_name }}@endif<br>
      @if($employee->address){!! nl2br(e($employee->address)) !!}@else Address: ________________________________ @endif<br>Mobile: {{ $employee->mobile ?: '____________________' }}</td>
    <td class="r" style="vertical-align:top"><b>Employee ID:</b> {{ $employee->emp_code }}<br><b>Date:</b> ______________</td></tr></table>
  <p>To,<br>The {{ $agency->signatory_title ?: 'Proprietor' }},<br><b>{{ $agency->name }}</b>@if($agency->address)<br>{!! nl2br(e($agency->address)) !!}@endif</p>
  <table class="nb" style="margin:6px 0 8px"><tr><td style="width:48px;vertical-align:top"><b><u>Sub:-</u></b></td>
    <td>Acceptance of appointment as <b>{{ $employee->post->designation }}</b> under the project "{{ $project->title ?: $project->name }}" ({{ $project->code }}) – reg.</td></tr>
    <tr><td style="vertical-align:top"><b><u>Ref:-</u></b></td><td>Your Appointment Order No. <b>{{ $number }}</b> dated {{ $joined->format('d.m.Y') }}.</td></tr></table>
  <p>{{ $employee->gender === 'female' ? 'Madam' : 'Sir' }}/{{ $employee->gender === 'female' ? 'Sir' : 'Madam' }},</p>
  <p style="text-indent:40px">With reference to the above cited appointment order, I hereby accept the appointment as <b>{{ $employee->post->designation }}</b> on outsourcing basis under {{ $agency->name }} for deployment in the project "{{ $project->title ?: $project->name }}" at {{ $project->location ?: $contract->client->name }}, on the terms and conditions stated in the order, which I have read and understood.</p>
  <p style="text-indent:40px">I shall report for duty on <b>{{ $joined->format('d.m.Y') }}</b>. I agree to the statutory deductions applicable to me (EPF: {{ $employee->pf_applicable ? 'Yes' : 'No' }}, ESI: {{ $employee->esic_applicable ? 'Yes' : 'No' }}, TDS: {{ ($employee->tds_applicable ?? true) ? 'Yes' : 'No' }}), understand that this is a contractual engagement for the project with no claim to regular employment, and undertake to abide by the rules, maintain confidentiality and give one month's notice before leaving.</p>
  <p style="text-indent:40px">My particulars for payment and statutory records are given below, and self-attested copies of the documents listed are enclosed.</p>
  <table class="pay hand" style="width:100%;margin:4px 0 8px">
    <tr><td style="width:38%">Bank name and branch</td><td>{{ $employee->bank_name ?: '' }}</td></tr>
    <tr><td>Bank account no.</td><td></td></tr>
    <tr><td>IFSC</td><td>{{ $employee->ifsc ?: '' }}</td></tr>
    <tr><td>UAN (EPF), if already allotted</td><td>{{ $employee->uan ?: '' }}</td></tr>
    <tr><td>ESI IP no., if already allotted</td><td>{{ $employee->esic_ip ?: '' }}</td></tr>
    <tr><td>Nominee name and relationship</td><td></td></tr>
  </table>
  <p><b>Enclosures:</b> (1) Aadhaar card (2) PAN card (3) Bank passbook / cancelled cheque (4) Educational and experience certificates (5) Two passport-size photographs (6) Medical fitness certificate, if required.</p>
  <table class="nb" style="margin-top:18px;page-break-inside:avoid"><tr><td></td><td class="c" style="width:45%">Yours faithfully,<br><br><br>(Signature of the appointee)<br>{{ trim($title.' '.$employee->name) }}</td></tr></table>
  <p style="margin-top:10px"><b>Copy to:</b><br>a) The Project In-charge{{ $project->pi_name ? ', '.$project->pi_name : '' }}, project "{{ $project->name }}", for information.<br>b) Personal file.</p>
  <div style="border-top:1px dashed #000;margin-top:10px;padding-top:4px;font-size:9px"><b>For office use ({{ $agency->name }}):</b> Received on ______________ &nbsp;&nbsp; Checked by ______________ &nbsp;&nbsp; Joined on ______________</div>
</div>
</body></html>
