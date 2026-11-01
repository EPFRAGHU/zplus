@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'col' => 'col-md-6', 'hint' => null])
@php($id = 'f-'.str_replace(['[', ']', '.'], '-', $name))
<div class="{{ $col }}">
  <label for="{{ $id }}" class="form-label small fw-semibold text-secondary mb-1">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
  <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required)
    {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
  @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
  @if($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
