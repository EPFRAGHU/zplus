{{-- Banner letterhead (SWACHHA): logo, name with tagline, registrations column, then the address box. Expects $agency and $logo. --}}
<table style="width:100%;border-collapse:collapse;font-family:'DejaVu Sans',sans-serif">
  <tr>
    @if($logo)<td style="width:86px;vertical-align:middle"><img src="{{ $logo }}" style="width:78px;height:78px" alt=""></td>@endif
    <td style="text-align:center;vertical-align:middle;padding:0 6px">
      <div style="font-size:34px;font-weight:bold;color:#E46C0A;letter-spacing:2px;line-height:1">&ldquo;{{ strtoupper($agency->name) }}&rdquo;</div>
      @if($agency->tagline)<div style="font-size:16px;margin-top:2px;padding-left:70px">{{ $agency->tagline }}</div>@endif
    </td>
    @if($pairs = $agency->registrationPairs())
    <td style="width:230px;vertical-align:middle;border-left:2px solid #4BACC6;padding-left:8px">
      <table style="border-collapse:collapse;font-size:9.5px;font-weight:bold;line-height:1.3">
        @foreach($pairs as [$label, $value])
        <tr><td style="color:#7030A0;padding:0 6px 0 0;white-space:nowrap">{{ $label }}@if($value !== '')-@endif</td><td style="color:#0070C0;padding:0">@if($value !== ''):{{ $value }}@endif</td></tr>
        @endforeach
      </table>
    </td>
    @endif
  </tr>
</table>
<div style="border-top:2px solid #4BACC6;margin:4px 0 3px"></div>
<div style="background:#F9E3E5;border:1px solid #D99594;border-radius:6px;padding:4px 8px;font-family:'DejaVu Serif',serif;font-style:italic;font-size:9.5px;font-weight:bold">
  <table style="width:100%;border-collapse:collapse">
    @if($agency->address)<tr><td style="width:118px;vertical-align:top">Regd. Office Address:</td><td colspan="2">{!! nl2br(e($agency->address)) !!}</td></tr>@endif
    @if($agency->letterhead_emails || $agency->phones)
    <tr><td style="vertical-align:top">@if($agency->letterhead_emails)Email:@endif</td><td>{{ $agency->letterhead_emails }}</td>
      <td style="text-align:right;white-space:nowrap">@if($agency->phones)Mob No: {{ $agency->phones }}@endif</td></tr>
    @endif
  </table>
</div>
<div style="border-top:3px solid #4BACC6;margin:3px 0 10px"></div>
