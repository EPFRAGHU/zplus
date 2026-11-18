@props(['label', 'value', 'meta' => null, 'color' => '#5a6680'])
<div class="col"><div class="z-card z-kpi h-100">
  <div class="l"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $color }}"></span>{{ $label }}</div>
  <div class="v">{{ $value }}</div>
  @if($meta)<div class="small text-secondary">{!! $meta !!}</div>@endif
</div></div>
