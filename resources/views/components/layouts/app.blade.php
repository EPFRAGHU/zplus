<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Dashboard' }} · ZPlus Manpower</title>
<meta name="description" content="Manpower records, attendance, GeM billing and payroll.">
@include('partials.favicon')
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="{{ asset('css/app.css') }}?v={{ \App\Support\Release::version() }}" rel="stylesheet">
{{ $head ?? '' }}
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
    <nav class="z-nav">
      <div class="z-label">Overview</div>
      <a class="{{ $is('dashboard') }}" href="{{ route('dashboard') }}"><i class="bi bi-grid"></i>Dashboard</a>
      @php($apCount = \App\Services\Approvals\Approvals::canApprove(auth()->user())
          ? \App\Models\Approval::query()->where('status', 'pending')->where('submitted_by', '<>', auth()->id())->count()
          : \App\Models\Approval::query()->where('status', 'returned')->where('submitted_by', auth()->id())->where('decided_at', '>=', now()->subDays(14))->count())
      @php($apWaiting = \App\Services\Approvals\Approvals::canApprove(auth()->user()) ? 0 : \App\Models\Approval::query()->where('status', 'pending')->count())
      <a class="{{ $is('approvals.*') }}" href="{{ route('approvals.index') }}"><i class="bi bi-person-check"></i>Approvals @if($apWaiting || $apCount)<span class="ms-auto d-inline-flex gap-1">@if($apWaiting)<span class="badge rounded-pill text-bg-secondary" title="Sent, waiting for Super Admin approval">{{ $apWaiting }}</span>@endif @if($apCount)<span class="badge rounded-pill text-bg-danger" title="{{ \App\Services\Approvals\Approvals::canApprove(auth()->user()) ? 'Waiting for your approval' : 'Returned to you' }}">{{ $apCount }}</span>@endif</span>@endif</a>
      <div class="z-label">Masters</div>
      <a class="{{ $is('agencies.*') }}" href="{{ route('agencies.index') }}"><i class="bi bi-building"></i>Agencies</a>
      <a class="{{ $is('clients.*') }}" href="{{ route('clients.index') }}"><i class="bi bi-bank"></i>Clients</a>
      <a class="{{ $is('contracts.*') }}" href="{{ route('contracts.index') }}"><i class="bi bi-file-earmark-text"></i>Work Orders</a>
      <a class="{{ $is('incharges.*') }}" href="{{ route('incharges.index') }}"><i class="bi bi-person-badge"></i>Project In-charge</a>
      <a class="{{ $is('posts.*') }}" href="{{ route('posts.index') }}"><i class="bi bi-diagram-3"></i>Posts</a>
      <a class="{{ $is('employees.*') }}" href="{{ route('employees.index') }}"><i class="bi bi-people"></i>Employees</a>
      <a class="{{ $is('staff.*') }}" href="{{ route('staff.index') }}"><i class="bi bi-person-lines-fill"></i>Employee Management</a>
      <div class="z-label">Operations</div>
      <a class="{{ $is('attendance.*') }}" href="{{ route('attendance.index') }}"><i class="bi bi-calendar-check"></i>Attendance</a>
      <a class="{{ $is('invoices.*') }}" href="{{ route('invoices.index') }}"><i class="bi bi-receipt"></i>Sub-bills</a>
      <a class="{{ $is('tax-invoices.*') }}" href="{{ route('tax-invoices.index') }}"><i class="bi bi-file-earmark-ruled"></i>Tax Invoices</a>
      @can('billing')<a class="{{ $is('payments.*') }}" href="{{ route('payments.index') }}"><i class="bi bi-cash-coin"></i>Payments received</a>@endcan
      @can('view-bank-details')<a class="{{ $is('bank-letters.*') }}" href="{{ route('bank-letters.index') }}"><i class="bi bi-bank"></i>Bank Letter</a>@endcan
      @can('import')
      <div class="z-label">Setup</div>
      <a class="{{ $is('master-import.*') }}" href="{{ route('master-import.index') }}"><i class="bi bi-file-earmark-spreadsheet"></i>Data Upload</a>
      @endcan
      @can('gem-import')
      <a class="{{ $is('import.*') }}" href="{{ route('import.create') }}"><i class="bi bi-file-earmark-arrow-up"></i>GeM Workbook Import</a>
      @endcan
      @can('manage-users')
      <a class="{{ $is('users.*') }}" href="{{ route('users.index') }}"><i class="bi bi-person-gear"></i>Users</a>
      @endcan
      @can('view-activity')
      <a class="{{ $is('activity.*') }}" href="{{ route('activity.index') }}"><i class="bi bi-journal-text"></i>Activity log</a>
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
      @php($workAgency = \App\Support\Agency\AgencyContext::agency())
      <div>{{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}@unless(auth()->user()->isPi()) · {{ $workAgency?->code ?? 'Both agencies' }}@endunless</div>
      @if(\App\Support\Agency\AgencyContext::canSwitch(auth()->user()))
        <a class="d-block small text-decoration-none mt-1" style="color:var(--z-rail-fg)" href="{{ route('agency.choose') }}"><i class="bi bi-arrow-left-right me-1"></i>Change company</a>
      @endif
      <a class="d-block small text-decoration-none mt-1" style="color:var(--z-rail-fg)" href="{{ route('account.password') }}"><i class="bi bi-key me-1"></i>Change password</a>
      @can('delete-masters')
      <form method="post" action="{{ route('delete-mode') }}" class="mt-2">@csrf
        <div class="form-check form-switch small mb-0" style="color:var(--z-rail-fg)" title="Show the Delete buttons on lists and pages (only unused records can be deleted)">
          <input class="form-check-input" type="checkbox" role="switch" id="delete-mode" onchange="this.form.submit()" @checked(session('delete_mode'))>
          <label class="form-check-label" for="delete-mode">Show delete options</label></div></form>
      @endcan
      <form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-sm btn-outline-light w-100">Sign out</button></form>
    </div>
  </aside>
  <main class="z-main">
    <div class="d-flex align-items-start gap-3 flex-wrap mb-3">
      <button class="btn btn-outline-secondary menu-btn" type="button" onclick="document.getElementById('rail').classList.toggle('open')" aria-label="Menu"><i class="bi bi-list"></i></button>
      <div>
        <{{ ($quietTitle ?? false) ? 'div' : 'h1' }} @class(['h4 fw-bold mb-0', 'visually-hidden' => $quietTitle ?? false])>{{ $title ?? 'Dashboard' }}</{{ ($quietTitle ?? false) ? 'div' : 'h1' }}>
        @isset($crumb)<div class="crumb">Dashboard / {!! $crumb !!}</div>@endisset
      </div>
      @unless(auth()->user()->isPi())
        <span class="badge rounded-pill text-bg-light border align-self-center fw-semibold" title="Agency you are working for">
          <i class="bi bi-building me-1"></i>{{ $workAgency?->name ?? 'Both agencies' }}</span>
        @if(\App\Support\Agency\AgencyContext::canSwitch(auth()->user()))<a class="small align-self-center" href="{{ route('agency.choose') }}">Change</a>@endif
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
<script>
  // Click a count (staff on a post, projects of a PI) to see the names; click anywhere else or press Esc to close (owner, 22 Nov 2026).
  (function () {
    const pops = [...document.querySelectorAll('[data-pop]')].map(el => new bootstrap.Popover(el, {
      html: true, trigger: 'click', placement: el.dataset.popPlace || 'bottom', container: 'body', customClass: 'wo-pop'
    }));
    const hideAll = except => pops.forEach(p => { if (p !== except) p.hide(); });
    document.querySelectorAll('[data-pop]').forEach(el => el.addEventListener('show.bs.popover', () => hideAll(bootstrap.Popover.getInstance(el))));
    document.addEventListener('click', e => { if (!e.target.closest('[data-pop]') && !e.target.closest('.popover')) hideAll(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') hideAll(); });
  })();
</script>
@if(! auth()->user()->isPi() && \App\Support\Agency\AgencyContext::id())
<script>
  // Single-agency login (or a both-agency login that chose one company): every agency dropdown in a form is filled with the user's own agency and locked.
  (function () {
    const mine = '{{ \App\Support\Agency\AgencyContext::id() }}';
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
    // Filters (Agency on list pages): show the login's own agency instead of "All agencies"; the lists are that agency's anyway.
    const fill = sel => {
      if (!sel || sel.dataset.agencyLocked || ![...sel.options].some(o => o.value === mine)) return;
      [...sel.options].forEach(o => { if (o.value !== mine) o.remove(); });
      sel.value = mine;
      sel.dataset.agencyLocked = '1'; sel.tabIndex = -1; sel.style.pointerEvents = 'none'; sel.classList.add('bg-light');
      sel.title = 'You work for this agency';
    };
    const run = () => { document.querySelectorAll('form select[name="agency_id"]').forEach(lock); document.querySelectorAll('form select[name="agency"]').forEach(fill); };
    run();
    document.addEventListener('shown.bs.modal', run);
  })();
</script>
@endif
</body>
</html>
