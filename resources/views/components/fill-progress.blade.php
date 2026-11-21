{{-- Posts filled out of sanctioned, as a bar and a percentage (green when full, amber when partly filled). --}}
@props(['filled' => 0, 'total' => 0])
@php($pct = $total ? min(100, $filled * 100 / $total) : 0)
<div {{ $attributes->merge(['class' => 'wo-prog']) }} role="progressbar" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $filled }} of {{ $total }} posts filled">
  <div class="track"><span style="width:{{ $pct }}%;--p:{{ $pct >= 100 ? '#16a34a' : ($pct > 0 ? '#f59e0b' : '#cbd5e1') }}"></span></div>
  <b>{{ rtrim(rtrim(number_format($pct, 1), '0'), '.') }}%</b>
</div>
