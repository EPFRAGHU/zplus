{{-- Agency letterhead for PDFs. Falls back to a plain name block when the agency has no letterhead details. --}}
@php($logo = $agency->logoDataUri())
@if($agency->hasLetterhead())
<table style="width:100%;border-collapse:collapse">
  <tr>
    @if($logo)<td style="width:92px;vertical-align:middle"><img src="{{ $logo }}" style="width:84px;height:84px" alt=""></td>@endif
    <td style="text-align:center;vertical-align:middle">
      <div style="font-size:22px;font-weight:bold;letter-spacing:.5px">{{ strtoupper($agency->name) }}</div>
      @if($agency->tagline)<div style="font-family:'DejaVu Serif',serif;font-style:italic;font-size:12px">({{ $agency->tagline }})</div>@endif
      @if($agency->registrations)<div style="font-size:9px;line-height:1.35;margin-top:2px">{!! nl2br(e($agency->registrations)) !!}</div>@endif
      @if($agency->address)<div style="font-size:9.5px;margin-top:2px"><b>H.O.:</b> {{ $agency->address }}</div>@endif
      @if($agency->gstin)<div style="font-size:10px;font-weight:bold">GSTIN No.-{{ $agency->gstin }}</div>@endif
    </td>
  </tr>
</table>
<div style="border-top:2px solid #111;margin:4px 0 10px"></div>
@else
<div style="margin-bottom:8px"><div style="font-size:16px;font-weight:bold">{{ $agency->name }}</div>
  <div style="font-size:10px;color:#555">{!! nl2br(e($agency->address)) !!}</div>
  @if($agency->gstin)<div style="font-size:10px">GSTIN: {{ $agency->gstin }}</div>@endif</div>
<div style="border-top:1px solid #999;margin:4px 0 10px"></div>
@endif
