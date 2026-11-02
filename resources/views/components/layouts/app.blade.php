<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Dashboard' }} · ZPlus Manpower</title>
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--z-accent:#e23d4e;--z-rail:#1f1a1d;--z-rail-fg:#cfc6cb;--z-rail-active:#2d2529;--z-bg:#f5f4f6;--bs-body-font-family:"Plus Jakarta Sans",system-ui,sans-serif;--bs-body-bg:var(--z-bg);--bs-primary:#e23d4e;--bs-primary-rgb:226,61,78;--bs-link-color-rgb:200,40,60;--bs-border-radius:.55rem}
body{font-size:.9rem}
.btn-primary{--bs-btn-bg:var(--z-accent);--bs-btn-border-color:var(--z-accent);--bs-btn-hover-bg:#c92e40;--bs-btn-hover-border-color:#c92e40;--bs-btn-active-bg:#b52838;--bs-btn-disabled-bg:#e23d4e}
.mono{font-family:"JetBrains Mono",ui-monospace,monospace;font-size:.8rem}
.num{text-align:right;font-variant-numeric:tabular-nums}
.z-app{display:grid;grid-template-columns:236px minmax(0,1fr);min-height:100vh}
.z-rail{background:var(--z-rail);color:var(--z-rail-fg);padding:18px 12px;position:sticky;top:0;height:100vh;overflow:auto;display:flex;flex-direction:column;gap:14px}
.z-brand{display:flex;gap:10px;align-items:center;color:#fff;padding:4px 8px;text-decoration:none}
.z-brand b{display:block}.z-brand small{color:var(--z-rail-fg);font-size:.7rem}
.z-mark{width:30px;height:30px;border-radius:8px;background:var(--z-accent);display:grid;place-items:center;font-weight:800}
.z-label{font-size:.66rem;text-transform:uppercase;letter-spacing:.09em;color:#8d8288;padding:6px 10px 2px}
.z-nav a{display:flex;gap:10px;align-items:center;padding:7px 10px;border-radius:8px;color:var(--z-rail-fg);text-decoration:none;font-weight:500}
.z-nav a:hover{background:var(--z-rail-active);color:#fff}
.z-nav a.active{background:var(--z-rail-active);color:#fff;box-shadow:inset 3px 0 0 var(--z-accent)}
.z-ver{margin-top:auto;display:block;text-decoration:none;color:var(--z-rail-fg);font-size:.72rem;padding:9px 10px;border:1px solid #3a3136;border-radius:8px;line-height:1.45}
.z-ver:hover{border-color:#5a4e54;color:#fff}
.z-ver .v{display:flex;align-items:center;justify-content:space-between;gap:6px;color:#fff;font-weight:600;font-size:.78rem}
.z-ver .v span.badge{font-family:"JetBrains Mono",ui-monospace,monospace;font-weight:500;background:var(--z-accent);font-size:.68rem}
.z-ver .d{color:#8d8288}
.z-user{font-size:.78rem;padding:10px;border-radius:8px;background:var(--z-rail-active)}
.z-main{min-width:0;padding:22px clamp(16px,2.5vw,28px) 48px}
.z-card{background:#fff;border:1px solid #e7e2e5;border-radius:10px}
.z-card>.z-card-h{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid #e7e2e5}
.z-card-h h2{font-size:.95rem;margin:0;font-weight:700}
.z-kpi{padding:14px 16px}.z-kpi .v{font-size:1.45rem;font-weight:700;font-variant-numeric:tabular-nums}.z-kpi .l{font-size:.78rem;color:#6c6469;font-weight:600}
.table{--bs-table-bg:transparent;margin:0}.table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#6c6469;background:#faf8f9;white-space:nowrap}
.table td{vertical-align:middle}
.crumb{font-size:.8rem;color:#6c6469}
.att-grid select{width:3.4rem;padding:2px 4px;font-size:.72rem;border-radius:4px;text-align:center}
.att-grid td,.att-grid th{padding:3px 2px!important;text-align:center}
.att-grid td:first-child,.att-grid th:first-child{text-align:left;position:sticky;left:0;background:#fff;z-index:1;padding-left:10px!important;min-width:170px}
.c-P{background:#e3f4ea}.c-A,.c-L{background:#fde6e8}.c-CL,.c-EL,.c-HD{background:#fdf1dc}.c-H,.c-RH,.c-CH{background:#e4eefc}.c-SAT,.c-SUN{background:#f1eef0;color:#8a8086}
.menu-btn{display:none}
@media (max-width:900px){.z-app{grid-template-columns:1fr}.z-rail{position:fixed;inset:0 auto 0 0;width:250px;z-index:1040;transform:translateX(-105%);transition:transform .2s}.z-rail.open{transform:none}.menu-btn{display:inline-flex}}
</style>
</head>
<body>
<div class="z-app">
  <aside class="z-rail" id="rail">
    <a class="z-brand" href="{{ route('dashboard') }}"><span class="z-mark">Z+</span><span><b>ZPlus Manpower</b><small>Admin console</small></span></a>
    @php($is = fn ($p) => request()->routeIs($p) ? 'active' : '')
    <nav class="z-nav">
      <div class="z-label">Overview</div>
      <a class="{{ $is('dashboard') }}" href="{{ route('dashboard') }}"><i class="bi bi-grid"></i>Dashboard</a>
      <div class="z-label">Masters</div>
      <a class="{{ $is('agencies.*') }}" href="{{ route('agencies.index') }}"><i class="bi bi-building"></i>Agencies</a>
      <a class="{{ $is('clients.*') }}" href="{{ route('clients.index') }}"><i class="bi bi-bank"></i>Clients</a>
      <a class="{{ $is('contracts.*') }}" href="{{ route('contracts.index') }}"><i class="bi bi-file-earmark-text"></i>GeM Contracts</a>
      <a class="{{ $is('posts.*') }}" href="{{ route('posts.index') }}"><i class="bi bi-diagram-3"></i>Posts</a>
      <a class="{{ $is('employees.*') }}" href="{{ route('employees.index') }}"><i class="bi bi-people"></i>Employees</a>
      <div class="z-label">Operations</div>
      <a class="{{ $is('attendance.*') }}" href="{{ route('attendance.index') }}"><i class="bi bi-calendar-check"></i>Attendance</a>
      <a class="{{ $is('invoices.*') }}" href="{{ route('invoices.index') }}"><i class="bi bi-receipt"></i>Sub-bills</a>
      <a class="{{ $is('tax-invoices.*') }}" href="{{ route('tax-invoices.index') }}"><i class="bi bi-file-earmark-ruled"></i>Tax Invoices</a>
      @can('import')
      <div class="z-label">Setup</div>
      <a class="{{ $is('master-import.*') }}" href="{{ route('master-import.index') }}"><i class="bi bi-file-earmark-spreadsheet"></i>Data Upload</a>
      <a class="{{ $is('import.*') }}" href="{{ route('import.create') }}"><i class="bi bi-file-earmark-arrow-up"></i>GeM Workbook Import</a>
      @endcan
    </nav>
    @php($rel = \App\Support\Release::current())
    <a class="z-ver" href="{{ route('releases') }}" title="Release notes">
      <span class="v">Version <span class="badge">v{{ $rel['version'] }}</span></span>
      <span class="d d-block" title="Indian Standard Time">Updated {{ $rel['date']->format('d M Y · h:i A') }}</span>
      <span class="d d-block">Since {{ \App\Support\Release::started()->format('d M Y') }} · <span class="text-decoration-underline">What's new</span></span>
    </a>
    <div class="z-user">
      <div class="text-white fw-semibold">{{ auth()->user()->name }}</div>
      <div>{{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}</div>
      <form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-light w-100">Sign out</button></form>
    </div>
  </aside>
  <main class="z-main">
    <div class="d-flex align-items-start gap-3 flex-wrap mb-3">
      <button class="btn btn-outline-secondary menu-btn" type="button" onclick="document.getElementById('rail').classList.toggle('open')" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div>
        <h1 class="h4 fw-bold mb-0">{{ $title ?? 'Dashboard' }}</h1>
        @isset($crumb)<div class="crumb">Dashboard / {!! $crumb !!}</div>@endisset
      </div>
      <div class="ms-auto d-flex gap-2 flex-wrap">{{ $actions ?? '' }}</div>
    </div>
    @if (session('status'))<div class="alert alert-success py-2">{{ session('status') }}</div>@endif
    @if ($errors->any())
      <div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    {{ $slot }}
  </main>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
