{{-- Month and year picker that looks the same in every browser. Submits "YYYY-MM" under $name. --}}
@props(['name' => 'month', 'id' => 'm', 'value' => null, 'required' => false, 'clearable' => false])
@php($v = $value ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', substr((string) $value, 0, 7)) : null)
<div class="dropdown z-month" data-month-picker>
  <input type="hidden" name="{{ $name }}" value="{{ $v?->format('Y-m') }}" @required($required) data-mp-value>
  <button id="{{ $id }}" type="button" class="form-select text-start" style="min-width:170px" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-mp-label>
    <i class="bi bi-calendar3 me-2 text-secondary"></i><span>{{ $v?->format('F Y') ?? 'Any month' }}</span>
  </button>
  <div class="dropdown-menu p-2 shadow" style="width:270px">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-mp-year="-1" aria-label="Previous year"><i class="bi bi-chevron-left"></i></button>
      <b data-mp-yeartext>{{ ($v ?? now())->format('Y') }}</b>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-mp-year="1" aria-label="Next year"><i class="bi bi-chevron-right"></i></button>
    </div>
    <div class="d-grid gap-1" style="grid-template-columns:repeat(3,1fr)">
      @foreach(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $i => $m)
        <button type="button" class="btn btn-sm btn-light border" data-mp-month="{{ $i + 1 }}">{{ $m }}</button>
      @endforeach
    </div>
    <div class="d-flex justify-content-between mt-2">
      <button type="button" class="btn btn-sm btn-link px-0" data-mp-this>This month</button>
      @if($clearable)<button type="button" class="btn btn-sm btn-link px-0 text-secondary" data-mp-clear>Any month</button>@endif
    </div>
  </div>
</div>
@once
<script>
  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-month-picker]').forEach(root => {
    const names = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const input = root.querySelector('[data-mp-value]'), label = root.querySelector('[data-mp-label] span'), yearText = root.querySelector('[data-mp-yeartext]');
    let year = parseInt(input.value ? input.value.slice(0, 4) : yearText.textContent, 10);
    const paint = () => {
      yearText.textContent = year;
      root.querySelectorAll('[data-mp-month]').forEach(b => {
        const on = input.value === year + '-' + String(b.dataset.mpMonth).padStart(2, '0');
        b.classList.toggle('btn-primary', on); b.classList.toggle('btn-light', !on);
      });
    };
    const pick = (y, m) => {
      input.value = y + '-' + String(m).padStart(2, '0');
      label.textContent = names[m - 1] + ' ' + y;
      paint();
      bootstrap.Dropdown.getOrCreateInstance(root.querySelector('[data-mp-label]')).hide();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };
    root.querySelectorAll('[data-mp-year]').forEach(b => b.addEventListener('click', () => { year += parseInt(b.dataset.mpYear, 10); paint(); }));
    root.querySelectorAll('[data-mp-month]').forEach(b => b.addEventListener('click', () => pick(year, parseInt(b.dataset.mpMonth, 10))));
    root.querySelector('[data-mp-this]').addEventListener('click', () => { const d = new Date(); year = d.getFullYear(); pick(year, d.getMonth() + 1); });
    const clear = root.querySelector('[data-mp-clear]');
    if (clear) clear.addEventListener('click', () => { input.value = ''; label.textContent = 'Any month'; paint();
      bootstrap.Dropdown.getOrCreateInstance(root.querySelector('[data-mp-label]')).hide(); });
    paint();
  }));
</script>
@endonce
