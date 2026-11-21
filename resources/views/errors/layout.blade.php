{{-- Error pages must render without the database or a signed-in user (a 500 can mean either is unavailable). --}}
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · ZPlus Manpower</title>
<meta name="robots" content="noindex">
@include('partials.favicon')
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,system-ui,sans-serif;background:#0f2a5c;min-height:100vh;display:grid;place-items:center;padding:16px}
.code{font-size:.8rem;font-weight:700;letter-spacing:.08em;color:#1d4ed8}
.btn-primary{--bs-btn-bg:#1d4ed8;--bs-btn-border-color:#1d4ed8;--bs-btn-hover-bg:#1e40af;--bs-btn-hover-border-color:#1e40af}
</style>
</head><body>
<main class="bg-white rounded-3 p-4 shadow" style="width:min(440px,100%)">
  <div class="d-flex align-items-center gap-2 mb-3"><span class="rounded-2 text-white fw-bold d-grid" style="width:34px;height:34px;place-items:center;background:#e23d4e">Z+</span><div class="fw-bold">ZPlus Manpower</div></div>
  <div class="code mb-1">ERROR @yield('code')</div>
  <h1 class="h4 fw-bold mb-2">@yield('title')</h1>
  <p class="text-secondary mb-4">@yield('message')</p>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-primary" href="{{ url('/') }}"><i class="bi bi-house me-1"></i>Go to dashboard</a>
    <a class="btn btn-outline-secondary" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i>Go back</a>
  </div>
</main>
</body></html>
