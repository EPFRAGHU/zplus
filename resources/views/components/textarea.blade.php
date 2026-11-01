@props(['name', 'label', 'value' => null, 'col' => 'col-12', 'rows' => 2])
<div class="{{ $col }}">
  <label for="f-{{ $name }}" class="form-label small fw-semibold text-secondary mb-1">{{ $label }}</label>
  <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
  @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
