@php($pis = $incharges ?? collect())
<div class="row g-2">
  <div class="col-md-3"><label class="form-label small text-secondary mb-1">Project name *</label><input class="form-control form-control-sm" name="name" value="{{ $p?->name }}" required></div>
  <div class="col-md-3"><label class="form-label small text-secondary mb-1" title="Used in sub-bill numbers and appointment letters, e.g. MPH">Short project name *</label><input class="form-control form-control-sm mono" name="code" value="{{ $p?->code }}" required maxlength="20" placeholder="e.g. HTA" title="{{ \App\Support\Codes::MESSAGE }}"></div>
  <div class="col-md-3"><label class="form-label small text-secondary mb-1">Project In-charge</label>
    <select class="form-select form-select-sm" name="project_incharge_id">
      <option value="">{{ $p?->pi_name && ! $p?->project_incharge_id ? $p->pi_name.' (not in Project In-charge list)' : 'Choose PI' }}</option>
      @foreach($pis as $pi)<option value="{{ $pi->id }}" @selected($p?->project_incharge_id === $pi->id)>{{ $pi->label() }}</option>@endforeach
    </select>
    <div class="form-text"><a href="{{ route('incharges.index') }}">Project In-charge</a>: add a new PI there.</div>
    @if($p?->pi_name && ! $p?->project_incharge_id)<input type="hidden" name="pi_name" value="{{ $p->pi_name }}">@endif
  </div>
  <div class="col-md-4"><label class="form-label small text-secondary mb-1">Location</label><input class="form-control form-control-sm" name="location" value="{{ $p?->location }}" placeholder="ICMR-NIHR, Bhubaneswar"></div>
  <div class="col-md-9"><label class="form-label small text-secondary mb-1">Full project title (printed on the covering letter)</label><input class="form-control form-control-sm" name="title" value="{{ $p?->title }}"></div>
  <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="saturday_off" value="1" id="sat{{ $p?->id ?? 'new' }}" @checked($p?->saturday_off ?? true)><label class="form-check-label small" for="sat{{ $p?->id ?? 'new' }}">Saturday is a weekly off</label></div></div>
</div>
