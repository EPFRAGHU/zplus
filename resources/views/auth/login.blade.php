<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · ZPlus Manpower</title>
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="{{ asset('css/zplus-theme.css') }}?v={{ \App\Support\Release::version() }}" rel="stylesheet">
<style>body{background:var(--z-ink);min-height:100vh;display:grid;place-items:center;padding:16px}</style>
</head><body>
<form method="post" action="{{ url('/login') }}" class="bg-white p-4" style="width:min(400px,100%);border-radius:var(--z-r-panel)">
  @csrf
  <div class="d-flex align-items-center gap-2 mb-3"><span class="z-mark">Z+</span><div><div class="fw-bold" style="font-family:var(--z-display);font-size:1.15rem">ZPlus Manpower</div><div class="small text-secondary">Sign in to continue</div></div></div>
  <div class="mb-3"><label class="form-label small fw-semibold" for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="form-control @error('email') is-invalid @enderror">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
  <div class="mb-3"><label class="form-label small fw-semibold" for="password">Password</label>
    <input id="password" name="password" type="password" required class="form-control"></div>
  <div class="small text-secondary mb-3"><i class="bi bi-shield-lock me-1"></i>On a shared computer, always click <b>Sign out</b> when you finish.</div>
  <button class="btn btn-primary w-100">Sign in</button>
</form>
<div style="position:fixed;bottom:14px;left:0;right:0;text-align:center;font-size:.72rem;color:#93a0b8">ZPlus Manpower v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::current()['date']->format('d M Y, h:i A') }} IST</div>
</body></html>
