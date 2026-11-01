{{-- Fixed footer line from the agency letterhead (website, e-mails, mobiles), repeated on every page. --}}
@if($agency->letterhead_style !== 'banner' && ($agency->website || $agency->letterhead_emails || $agency->phones))
<div style="position:fixed;left:0;right:0;bottom:-18px;text-align:center;font-size:9.5px;border-top:2px solid #111;padding-top:3px">
  @if($agency->website)Website: {{ $agency->website }}@endif{{ $agency->website && $agency->letterhead_emails ? ', ' : '' }}@if($agency->letterhead_emails)E-mail: {{ $agency->letterhead_emails }}@endif
  @if($agency->phones)<div style="font-weight:bold">Mob. {{ $agency->phones }}</div>@endif
</div>
@endif
