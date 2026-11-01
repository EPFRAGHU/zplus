@props(['label', 'value', 'meta' => null, 'color' => '#e23d4e'])
<div class="col"><div class="z-card z-kpi h-100">
  <div class="l"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $color }}"></span>{{ $label }}</div>
  <div class="v">{{ $value }}</div>
  @if($meta)<div class="small text-secondary">{!! $meta !!}</div>@endif
</div></div>
