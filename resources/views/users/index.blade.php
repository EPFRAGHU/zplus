<x-layouts.app title="Users" crumb="Setup / Users">
  <x-slot:actions><a class="btn btn-primary" href="{{ route('users.create') }}"><i class="bi bi-person-plus me-1"></i>Add user</a></x-slot:actions>
  <div class="z-card mb-3">
    <div class="table-responsive"><table class="table">
      <thead><tr><th>Name</th><th>E-mail</th><th>Role</th><th>Agency</th><th>Projects (PI)</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @foreach($users as $u)
        <tr>
          <td class="fw-semibold">{{ $u->name }}@if($u->is_super_admin) <span class="badge text-bg-dark ms-1">Super Admin</span>@endif @if($u->must_change_password) <span class="badge text-bg-warning ms-1">Temporary password</span>@endif</td>
          <td>{{ $u->email }}</td>
          <td>{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}</td>
          <td>@if($u->agency)<span class="badge text-bg-light border">{{ $u->agency->code }}</span>@else<span class="badge text-bg-dark">Both</span>@endif</td>
          <td class="small">@forelse($u->projects as $p)<div>{{ $p->name }} <span class="text-secondary">· {{ $p->contract->client->short_code }}</span></div>@empty<span class="text-secondary">—</span>@endforelse</td>
          <td class="small">{{ $u->last_login_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?? 'Never' }}</td>
          <td>@if($u->active)<span class="badge text-bg-success">Active</span>@else<span class="badge text-bg-secondary">Switched off</span>@endif</td>
          <td class="text-end">@if(! $u->is_super_admin || auth()->user()->is($u))<a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $u) }}">Edit</a>@endif</td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  </div>
  <div class="z-card">
    <div class="z-card-h"><h2>Recent sign-ins</h2></div>
    <div class="table-responsive"><table class="table table-sm">
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
</x-layouts.app>
