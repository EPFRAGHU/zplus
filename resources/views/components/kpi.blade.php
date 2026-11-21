{{-- Figure card: tinted with $color; $icon is an optional Bootstrap icon name (e.g. "people"). --}}
@props(['label', 'value', 'meta' => null, 'color' => '#1d4ed8', 'icon' => null])
<div class="col"><div class="z-card z-kpi h-100" style="--k:{{ $color }}">
  <div class="l">@if($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@else<span class="d-inline-block rounded-circle" style="width:8px;height:8px;background:{{ $color }}"></span>@endif{{ $label }}</div>
  <div class="v">{{ $value }}</div>
  @if($meta)<div class="small text-secondary">{!! $meta !!}</div>@endif
</div></div>
