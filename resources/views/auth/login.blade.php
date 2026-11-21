<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · ZPlus Manpower</title>
<meta name="description" content="Manpower records, attendance, GeM billing and payroll.">
@include('partials.favicon')
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:Inter,system-ui,sans-serif;background:#0f2a5c;min-height:100vh;display:grid;place-items:center;padding:16px}.btn-primary{--bs-btn-bg:#1d4ed8;--bs-btn-border-color:#1d4ed8;--bs-btn-hover-bg:#1e40af;--bs-btn-hover-border-color:#1e40af}</style>
</head><body>
<form method="post" action="{{ url('/login') }}" class="bg-white rounded-3 p-4 shadow" style="width:min(380px,100%)">
  @csrf
  <div class="d-flex align-items-center gap-2 mb-3"><span class="rounded-2 text-white fw-bold d-grid" style="width:34px;height:34px;place-items:center;background:#e23d4e">Z+</span><div><div class="fw-bold">ZPlus Manpower</div><div class="small text-secondary">Sign in to continue</div></div></div>
  <div class="mb-3"><label class="form-label small fw-semibold" for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="form-control @error('email') is-invalid @enderror">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
  <div class="mb-3"><label class="form-label small fw-semibold" for="password">Password</label>
    <input id="password" name="password" type="password" required class="form-control"></div>
  <div class="small text-secondary mb-3"><i class="bi bi-shield-lock me-1"></i>On a shared computer, always click <b>Sign out</b> when you finish.</div>
  <button class="btn btn-primary w-100">Sign in</button>
</form>
<div style="position:fixed;bottom:14px;left:0;right:0;text-align:center;font-size:.72rem;color:#8d8288">ZPlus Manpower v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::current()['date']->format('d M Y, h:i A') }} IST</div>
</body></html>
