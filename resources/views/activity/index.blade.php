<x-layouts.app title="Activity log" crumb="Setup / Activity log">
  <x-slot:actions>
    <a class="btn btn-outline-secondary" href="{{ route('activity.export', $filters) }}"><i class="bi bi-file-earmark-excel me-1"></i>Download Excel</a>
  </x-slot:actions>
  <form class="z-card p-3 mb-3 row g-2 align-items-end" method="get">
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-user">User</label>
      <select id="f-user" name="user" class="form-select form-select-sm"><option value="">Everyone</option>
        @foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }} ({{ \App\Models\User::ROLES[$u->role] ?? $u->role }})</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-action">Action</label>
      <select id="f-action" name="action" class="form-select form-select-sm"><option value="">All actions</option>
        @foreach(\App\Models\ActivityLog::ACTIONS as $k => [$label])<option value="{{ $k }}" @selected(($filters['action'] ?? '') === $k)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-section">Section</label>
      <select id="f-section" name="section" class="form-select form-select-sm"><option value="">All sections</option>
        @foreach($sections as $s)<option @selected(($filters['section'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-from">From</label><input id="f-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-to">To</label><input id="f-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="f-q">Search</label><input id="f-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, code, bill no." class="form-control form-control-sm"></div>
    <div class="col-12 d-flex gap-2 align-items-center">
      <button class="btn btn-sm btn-primary">Show</button><a class="btn btn-sm btn-outline-secondary" href="{{ route('activity.index') }}">Clear</a>
      <span class="small text-secondary ms-auto">{{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }} · {{ $today }} today · entries cannot be changed or deleted</span>
    </div>
  </form>

  <div class="z-card">
    <div class="table-responsive"><table class="table table-sm align-top">
      <thead><tr><th>When (IST)</th><th>User</th><th>Action</th><th>Section</th><th>Record</th><th>What changed</th><th>IP</th></tr></thead>
      <tbody>
      @forelse($logs as $log)
        @php([$label, $cls] = \App\Models\ActivityLog::ACTIONS[$log->action] ?? [$log->action, 'secondary'])
        <tr>
          <td class="small text-nowrap">{{ $log->created_at->timezone('Asia/Kolkata')->format('d M Y') }}<br><span class="text-secondary">{{ $log->created_at->timezone('Asia/Kolkata')->format('h:i:s A') }}</span></td>
          <td class="small">{{ $log->user_name }}<br><span class="text-secondary">{{ \App\Models\User::ROLES[$log->user_role] ?? '' }}</span></td>
          <td><span class="badge text-bg-{{ $cls }}">{{ $label }}</span></td>
          <td class="small">{{ $log->section }}</td>
          <td class="small">{{ $log->subject_label }}</td>
          <td class="small" style="max-width:520px">
            @if($log->note)<div>{{ $log->note }}</div>@endif
            @if($log->changes)
              <details @if(count($log->changes) <= 4) open @endif><summary class="text-secondary">{{ count($log->changes) }} {{ \Illuminate\Support\Str::plural('field', count($log->changes)) }}</summary>
                <table class="table table-sm table-borderless mb-0 small">
                @foreach($log->changes as $field => $v)
                  <tr><td class="text-secondary text-nowrap pe-2">{{ \App\Http\Controllers\ActivityLogController::field((string) $field) }}</td>
                    <td>@if(is_array($v))<span class="text-decoration-line-through text-secondary">{{ $v[0] ?? '—' }}</span> → <b>{{ $v[1] ?? '—' }}</b>@else{{ $v }}@endif</td></tr>
                @endforeach
                </table></details>
            @endif
          </td>
          <td class="mono small">{{ $log->ip }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-secondary py-4">Nothing logged for these filters.</td></tr>
      @endforelse
      </tbody>
    </table></div>
    <div class="p-2">{{ $logs->links() }}</div>
  </div>
</x-layouts.app>
