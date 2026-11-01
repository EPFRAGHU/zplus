@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'col' => 'col-md-6', 'placeholder' => null, 'hint' => null])
@php($id = 'f-'.$name)
@php($current = (string) old($name, $value))
<div class="{{ $col }}">
  <label for="{{ $id }}" class="form-label small fw-semibold text-secondary mb-1">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
  <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
    @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
    @foreach($options as $k => $v)<option value="{{ $k }}" @selected($current === (string) $k)>{{ $v }}</option>@endforeach
  </select>
  @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
  @if($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
