<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Dashboard' }} · ZPlus Manpower</title>
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="{{ asset('css/zplus-theme.css') }}?v={{ \App\Support\Release::version() }}" rel="stylesheet">
</head>
<body>
<div class="z-app">
  <aside class="z-rail" id="rail">
    <a class="z-brand" href="{{ auth()->user()->isPi() ? route('pi.home') : route('dashboard') }}"><span class="z-mark">Z+</span><span><b>ZPlus Manpower</b><small>{{ auth()->user()->isPi() ? 'PI portal' : 'Admin console' }}</small></span></a>
    @php($is = fn (...$p) => request()->routeIs(...$p) ? 'active' : '')
    @if(auth()->user()->isPi())
    <nav class="z-nav">
      <div class="z-label">My projects</div>
      <a class="{{ $is('pi.home', 'pi.sheet') }}" href="{{ route('pi.home') }}"><i class="bi bi-calendar-check"></i>Attendance</a>
      <a class="{{ $is('pi.bills') }}" href="{{ route('pi.bills') }}"><i class="bi bi-receipt"></i>Sub-bills</a>
    </nav>
    @else
    <nav class="z-nav" aria-label="Main">
      <a class="{{ $is('dashboard') }}" href="{{ route('dashboard') }}" @if($is('dashboard')) aria-current="page" @endif><i class="bi bi-grid" aria-hidden="true"></i>Dashboard</a>
      @php($apCount = \App\Services\Approvals\Approvals::canApprove(auth()->user())
          ? \App\Models\Approval::query()->where('status', 'pending')->where('submitted_by', '<>', auth()->id())->count()
          : \App\Models\Approval::query()->where('status', 'returned')->where('submitted_by', auth()->id())->where('decided_at', '>=', now()->subDays(14))->count())
      <a class="{{ $is('approvals.*') }}" href="{{ route('approvals.index') }}"><i class="bi bi-patch-check" aria-hidden="true"></i>Approvals @if($apCount)<span class="badge rounded-pill text-bg-danger ms-auto">{{ $apCount }}</span>@endif</a>
      <div class="z-label">Each month</div>
      <a class="{{ $is('attendance.*') }}" href="{{ route('attendance.index') }}"><i class="bi bi-calendar-check" aria-hidden="true"></i>Attendance</a>
      <a class="{{ $is('invoices.*') }}" href="{{ route('invoices.index') }}"><i class="bi bi-receipt" aria-hidden="true"></i>Sub-bills</a>
      <a class="{{ $is('tax-invoices.*') }}" href="{{ route('tax-invoices.index') }}"><i class="bi bi-file-earmark-ruled" aria-hidden="true"></i>Tax Invoices</a>
      @can('view-bank-details')<a class="{{ $is('bank-letters.*') }}" href="{{ route('bank-letters.index') }}"><i class="bi bi-bank" aria-hidden="true"></i>Bank Letter</a>@endcan
      <div class="z-label">Records</div>
      <a class="{{ $is('contracts.*') }}" href="{{ route('contracts.index') }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Work Orders</a>
      <a class="{{ $is('employees.*') }}" href="{{ route('employees.index') }}"><i class="bi bi-people" aria-hidden="true"></i>Employees</a>
      <a class="{{ $is('staff.*') }}" href="{{ route('staff.index') }}"><i class="bi bi-person-lines-fill" aria-hidden="true"></i>Employee Management</a>
      <a class="{{ $is('incharges.*') }}" href="{{ route('incharges.index') }}"><i class="bi bi-person-badge" aria-hidden="true"></i>Project In-charge</a>
      <a class="{{ $is('posts.*') }}" href="{{ route('posts.index') }}"><i class="bi bi-diagram-3" aria-hidden="true"></i>Posts</a>
      <a class="{{ $is('clients.*') }}" href="{{ route('clients.index') }}"><i class="bi bi-briefcase" aria-hidden="true"></i>Clients</a>
      <a class="{{ $is('agencies.*') }}" href="{{ route('agencies.index') }}"><i class="bi bi-building" aria-hidden="true"></i>Agencies</a>
      @if(auth()->user()->can('import') || auth()->user()->can('gem-import') || auth()->user()->can('manage-users') || auth()->user()->can('view-activity'))
      <div class="z-label">Setup</div>
      @endif
      @can('import')
      <a class="{{ $is('master-import.*') }}" href="{{ route('master-import.index') }}"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>Data Upload</a>
      @endcan
      @can('gem-import')
      <a class="{{ $is('import.*') }}" href="{{ route('import.create') }}"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>GeM Workbook Import</a>
      @endcan
      @can('manage-users')
      <a class="{{ $is('users.*') }}" href="{{ route('users.index') }}"><i class="bi bi-person-gear" aria-hidden="true"></i>Users</a>
      @endcan
      @can('view-activity')
      <a class="{{ $is('activity.*') }}" href="{{ route('activity.index') }}"><i class="bi bi-journal-text" aria-hidden="true"></i>Activity log</a>
      @endcan
    </nav>
    @endif
    @php($rel = \App\Support\Release::current())
    <a class="z-ver" href="{{ route('releases') }}" title="Release notes">
      <span class="v">Version <span class="badge">v{{ $rel['version'] }}</span></span>
      <span class="d d-block" title="Indian Standard Time">Updated {{ $rel['date']->format('d M Y · h:i A') }}</span>
      <span class="d d-block">Since {{ \App\Support\Release::started()->format('d M Y') }} · <span class="text-decoration-underline">What's new</span></span>
    </a>
    <div class="z-user">
      <div class="text-white fw-semibold">{{ auth()->user()->name }}</div>
      <div>{{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}@unless(auth()->user()->isPi()) · {{ auth()->user()->agency?->code ?? 'Both agencies' }}@endunless</div>
      <a class="d-block small text-decoration-none mt-1" style="color:var(--z-rail-fg)" href="{{ route('account.password') }}"><i class="bi bi-key me-1"></i>Change password</a>
      @can('delete-records')
      <form method="post" action="{{ route('delete-mode') }}" class="mt-2">@csrf
        <div class="form-check form-switch small mb-0" style="color:var(--z-rail-fg)" title="Super Admin: show the Delete buttons on lists and pages">
          <input class="form-check-input" type="checkbox" role="switch" id="delete-mode" onchange="this.form.submit()" @checked(session('delete_mode'))>
          <label class="form-check-label" for="delete-mode">Show delete options</label></div></form>
      @endcan
      <form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-light w-100">Sign out</button></form>
    </div>
  </aside>
  <main class="z-main">
    <div class="d-flex align-items-start gap-3 flex-wrap mb-3">
      <button class="btn btn-outline-secondary menu-btn" type="button" onclick="document.getElementById('rail').classList.toggle('open')" aria-label="Open menu" aria-controls="rail"><i class="bi bi-list" aria-hidden="true"></i></button>
      <div>
        <h1 class="z-page-title">{{ $title ?? 'Dashboard' }}</h1>
        @isset($crumb)<div class="crumb">Dashboard / {!! $crumb !!}</div>@endisset
      </div>
      @unless(auth()->user()->isPi())
        <span class="badge rounded-pill text-bg-light border align-self-center fw-semibold" title="Agency you are working for">
          <i class="bi bi-building me-1"></i>{{ auth()->user()->agency?->name ?? 'Both agencies' }}</span>
      @endunless
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
@if(! auth()->user()->isPi() && auth()->user()->agency_id)
<script>
  // Single-agency login: every agency dropdown in a form is filled with the user's own agency and locked.
  (function () {
    const mine = '{{ auth()->user()->agency_id }}';
    const lock = sel => {
      if (!sel || sel.dataset.agencyLocked) return;
      [...sel.options].forEach(o => { if (o.value !== mine) o.remove(); });
      if (sel.value !== mine) { sel.value = mine; sel.dispatchEvent(new Event('change', { bubbles: true })); }
      sel.dataset.agencyLocked = '1';
      sel.setAttribute('aria-readonly', 'true');
      sel.tabIndex = -1;
      sel.style.pointerEvents = 'none';
      sel.classList.add('bg-light');
      sel.title = 'You work for this agency';
    };
    const run = () => document.querySelectorAll('form select[name="agency_id"]').forEach(lock);
    run();
    document.addEventListener('shown.bs.modal', run);
  })();
</script>
@endif
</body>
</html>
