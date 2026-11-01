<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · ZPlus Manpower</title>
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#1f1a1d;min-height:100vh;display:grid;place-items:center;padding:16px}.btn-primary{--bs-btn-bg:#e23d4e;--bs-btn-border-color:#e23d4e;--bs-btn-hover-bg:#c92e40;--bs-btn-hover-border-color:#c92e40}</style>
</head><body>
<form method="post" action="{{ url('/login') }}" class="bg-white rounded-3 p-4 shadow" style="width:min(380px,100%)">
  @csrf
  <div class="d-flex align-items-center gap-2 mb-3"><span class="rounded-2 text-white fw-bold d-grid" style="width:34px;height:34px;place-items:center;background:#e23d4e">Z+</span><div><div class="fw-bold">ZPlus Manpower</div><div class="small text-secondary">Sign in to continue</div></div></div>
  <div class="mb-3"><label class="form-label small fw-semibold" for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="form-control @error('email') is-invalid @enderror">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
  <div class="mb-3"><label class="form-label small fw-semibold" for="password">Password</label>
    <input id="password" name="password" type="password" required class="form-control"></div>
  <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label small" for="remember">Keep me signed in</label></div>
  <button class="btn btn-primary w-100">Sign in</button>
</form>
</body></html>
