<x-layouts.app title="Users" crumb="Setup / Users" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-person-gear"></i></div>
    <div class="me-auto"><h1>Users</h1>
      <p>Who can sign in: Super Admins approve and delete, Accounts enter and send, PIs fill their projects' attendance. Only you (developer) manage logins.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    <a class="btn btn-primary" href="{{ route('users.create') }}"><i class="bi bi-person-plus me-1"></i>Add user</a>
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-people"></i><div><div class="l">Logins</div><div class="v">{{ $users->count() }}</div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-shield-lock"></i><div><div class="l">Super Admins</div><div class="v">{{ $users->where('is_super_admin', true)->count() }}</div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-calculator"></i><div><div class="l">Accounts</div><div class="v">{{ $users->where('role', 'accounts')->count() }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-person-badge"></i><div><div class="l">PI logins</div><div class="v">{{ $users->where('role', 'pi')->count() }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-person-x"></i><div><div class="l">Switched off</div><div class="v">{{ $users->where('active', false)->count() }}</div></div></div>
  </div>

  @php($roleTab = in_array(request('show'), ['super', 'accounts', 'pi', 'off'], true) ? request('show') : null)
  @php($listed = match ($roleTab) { 'super' => $users->where('is_super_admin', true), 'accounts' => $users->where('role', 'accounts'), 'pi' => $users->where('role', 'pi'), 'off' => $users->where('active', false), default => $users })
  <nav class="wo-tabs mb-3" aria-label="Logins">
    <a href="{{ route('users.index') }}" @class(['on' => ! $roleTab])>All ({{ $users->count() }})</a>
    <a href="{{ route('users.index', ['show' => 'super']) }}" @class(['on' => $roleTab === 'super'])>Super Admins ({{ $users->where('is_super_admin', true)->count() }})</a>
    <a href="{{ route('users.index', ['show' => 'accounts']) }}" @class(['on' => $roleTab === 'accounts'])>Accounts ({{ $users->where('role', 'accounts')->count() }})</a>
    <a href="{{ route('users.index', ['show' => 'pi']) }}" @class(['on' => $roleTab === 'pi'])>PIs ({{ $users->where('role', 'pi')->count() }})</a>
    <a href="{{ route('users.index', ['show' => 'off']) }}" @class(['on' => $roleTab === 'off'])>Switched off ({{ $users->where('active', false)->count() }})</a>
  </nav>
  <div class="wo-card mb-3">
    <div class="table-responsive"><table class="table align-middle wo-table m-0">
      <thead><tr><th>Name</th><th>E-mail</th><th>Role</th><th>Agency</th><th>Projects (PI)</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse($listed as $u)
        <tr>
          <td class="fw-semibold">{{ $u->name }}@if($u->is_super_admin) <span class="badge text-bg-dark ms-1">Super Admin</span>@endif @if($u->must_change_password) <span class="badge text-bg-warning ms-1">Temporary password</span>@endif</td>
          <td>{{ $u->email }}</td>
          <td>{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}</td>
          <td>@if($u->agency)<span class="badge text-bg-light border">{{ $u->agency->code }}</span>@else<span class="badge text-bg-dark">Both</span>@endif</td>
          <td class="small">@forelse($u->projects as $p)<div>{{ $p->name }} <span class="text-secondary">· {{ $p->contract->client->short_code }}</span></div>@empty<span class="text-secondary">—</span>@endforelse</td>
          <td class="small">{{ $u->last_login_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?? 'Never' }}</td>
          <td>@if($u->active)<span class="badge text-bg-success">Active</span>@else<span class="badge text-bg-secondary">Switched off</span>@endif</td>
          <td class="text-end">@if(! $u->is_super_admin || auth()->user()->is($u) || auth()->user()->isDeveloper())<a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $u) }}">Edit</a>@endif</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">No logins in this tab.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </div>
  <div class="wo-card">
    <div class="px-3 pt-3 pb-2 d-flex align-items-center gap-2"><i class="bi bi-box-arrow-in-right text-primary"></i><b>Recent sign-ins</b>
      @php($failed = $recentLogins->where('success', false)->count())
      @if($failed)<span class="badge text-bg-danger">{{ $failed }} failed</span>@endif</div>
    <div class="table-responsive"><table class="table align-middle wo-table m-0">
      <thead><tr><th>When</th><th>User</th><th>Result</th><th>IP</th></tr></thead>
      <tbody>
      @forelse($recentLogins as $e)
        <tr><td class="small">{{ $e->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td><td>{{ $e->user?->name ?? $e->email }}</td>
          <td>@if($e->success)<span class="badge text-bg-success">Signed in</span>@else<span class="badge text-bg-danger">Failed</span>@endif</td><td class="mono small">{{ $e->ip }}</td></tr>
      @empty
        <tr><td colspan="4" class="text-secondary text-center py-3">No sign-ins yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </div>
  </div>
</x-layouts.app>
