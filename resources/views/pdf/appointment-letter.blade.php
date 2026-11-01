<html><head><meta charset="utf-8">@include('pdf._style')</head><body class="letter">
<table><tr><td>Ref:- {{ $number }}</td><td class="r">Date:- {{ ($employee->joining_date ?? now())->format('d/m/Y') }}</td></tr></table>
<table style="font-size:11px;margin:4px 0 6px"><tr><td><b>Agency:</b> {{ $agency->name }} ({{ $agency->code }})</td><td class="r"><b>Client:</b> {{ $contract->client->name }}</td></tr></table>
<p>From,<br>O/o The {{ $agency->name }}<br>{!! nl2br(e($agency->address)) !!}@if($agency->email)<br>Email- {{ $agency->email }}@endif</p>
<p>To,<br>{{ $employee->name }}@if($employee->mobile)<br>Mob.: {{ $employee->mobile }}@endif @if($employee->email)<br>E-mail: {{ $employee->email }}@endif</p>
<p><b>Subject: Appointment as {{ $employee->post->designation }}</b><br><b>Project: "{{ $project->name }}"</b></p>
<p>Dear {{ $employee->name }},</p>
<p style="text-align:justify">We are pleased to appoint you as {{ $employee->post->designation }} with {{ $agency->name }} for the above-mentioned project, subject to the following terms and conditions:</p>
<p style="text-align:justify"><b>Tenure:</b> Your appointment is effective from {{ ($employee->joining_date ?? now())->format('d.m.Y') }} for an initial period of one year, subject to continuation of the project and satisfactory performance. The engagement may be extended or terminated as per project requirements. Either party may terminate the engagement by giving one month's notice.</p>
<p style="text-align:justify"><b>Remuneration:</b> You will receive a consolidated remuneration of Rs. {{ number_format((float) $rate) }}/- ({{ $rateWords }}) per month, subject to applicable statutory deductions.</p>
<p style="text-align:justify"><b>Place of Posting:</b> You are required to report at {{ $project->location ?: $contract->client->name }} on {{ ($employee->joining_date ?? now())->format('d.m.Y') }} or within seven working days of receiving this letter. You may be assigned to any project location as per the requirements of the Project Authority.</p>
<p style="text-align:justify"><b>Medical Fitness:</b> Your appointment is subject to medical fitness and submission of a medical fitness certificate, if required.</p>
<p style="text-align:justify"><b>Confidentiality:</b> You shall maintain strict confidentiality regarding all project-related information, documents, data and research activities. No such information shall be disclosed to any unauthorized person, media or third party without prior written permission.</p>
<p style="text-align:justify"><b>Conduct:</b> You shall follow all applicable rules, instructions and code of conduct and perform your duties sincerely and professionally. Misconduct, negligence, unauthorized absence or breach of confidentiality may lead to termination of the engagement.</p>
<p style="text-align:justify"><b>Nature of Appointment:</b> This is a purely outsourced/project-based engagement and does not confer any right to permanent employment, regularization or absorption in {{ $agency->name }}, {{ $contract->client->name }} or any other organization.</p>
<p>You are requested to sign and return a copy of this letter as acceptance of the above terms and conditions.</p>
<p>We wish you success in your assignment and look forward to your sincere contribution to the project.</p>
<div class="sign">For {{ $agency->name }}<br><br><br>Authorized Signatory<br>{{ $agency->name }}</div>
</body></html>
