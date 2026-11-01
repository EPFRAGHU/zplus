<div class="row g-2">
  <div class="col-md-3"><label class="form-label small text-secondary mb-1">Project name *</label><input class="form-control form-control-sm" name="name" value="{{ $p?->name }}" required></div>
  <div class="col-md-2"><label class="form-label small text-secondary mb-1">Code (for bill no.) *</label><input class="form-control form-control-sm mono" name="code" value="{{ $p?->code }}" required maxlength="12"></div>
  <div class="col-md-3"><label class="form-label small text-secondary mb-1">PI name</label><input class="form-control form-control-sm" name="pi_name" value="{{ $p?->pi_name }}"></div>
  <div class="col-md-4"><label class="form-label small text-secondary mb-1">Location</label><input class="form-control form-control-sm" name="location" value="{{ $p?->location }}" placeholder="ICMR-NIHR, Bhubaneswar"></div>
  <div class="col-md-9"><label class="form-label small text-secondary mb-1">Full project title (printed on the covering letter)</label><input class="form-control form-control-sm" name="title" value="{{ $p?->title }}"></div>
  <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="saturday_off" value="1" id="sat{{ $p?->id ?? 'new' }}" @checked($p?->saturday_off ?? true)><label class="form-check-label small" for="sat{{ $p?->id ?? 'new' }}">Saturday is a weekly off</label></div></div>
</div>
