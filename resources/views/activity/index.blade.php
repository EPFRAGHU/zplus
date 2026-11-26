<x-layouts.app title="Activity log" crumb="Setup / Activity log" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-journal-text"></i></div>
    <div class="me-auto"><h1>Activity log</h1>
      <p>Every add, change, delete, approval, download and sign-in, with who, when and the old → new values. Developer only; nothing here can be changed.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    <a class="btn wo-btn-green" href="{{ route('activity.export', $filters) }}"><i class="bi bi-file-earmark-excel me-1"></i>Download Excel</a>
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-journal-text"></i><div><div class="l">Entries today</div><div class="v">{{ $today }}</div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-calendar-week"></i><div><div class="l">This week</div><div class="v">{{ \App\Models\ActivityLog::query()->where('created_at', '>=', now()->startOfWeek())->count() }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-trash"></i><div><div class="l">Deleted this week</div><div class="v">{{ \App\Models\ActivityLog::query()->where('created_at', '>=', now()->startOfWeek())->where('action', 'deleted')->count() }}</div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-download"></i><div><div class="l">Downloads this week</div><div class="v">{{ \App\Models\ActivityLog::query()->where('created_at', '>=', now()->startOfWeek())->where('action', 'downloaded')->count() }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-shield-exclamation"></i><div><div class="l">Failed sign-ins this week</div><div class="v">{{ \App\Models\LoginEvent::query()->where('created_at', '>=', now()->startOfWeek())->where('success', false)->count() }}</div></div></div>
  </div>

  <form class="wo-bar" method="get" action="{{ route('activity.index') }}">
    <label class="wo-pick" for="f-user"><span>User:</span>
      <select id="f-user" name="user" class="form-select"><option value="">Everyone</option>
        @foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }} ({{ \App\Models\User::ROLES[$u->role] ?? $u->role }})</option>@endforeach</select></label>
    <label class="wo-pick" for="f-action"><span>Action:</span>
      <select id="f-action" name="action" class="form-select"><option value="">All actions</option>
        @foreach(\App\Models\ActivityLog::ACTIONS as $k => [$label])<option value="{{ $k }}" @selected(($filters['action'] ?? '') === $k)>{{ $label }}</option>@endforeach</select></label>
    <label class="wo-pick" for="f-section"><span>Section:</span>
      <select id="f-section" name="section" class="form-select"><option value="">All sections</option>
        @foreach($sections as $s)<option @selected(($filters['section'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></label>
    <label class="wo-pick" for="f-from"><span>From:</span><input id="f-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></label>
    <label class="wo-pick" for="f-to"><span>To:</span><input id="f-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></label>
    <div class="wo-search"><i class="bi bi-search"></i><input id="f-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, code, bill no." class="form-control" aria-label="Search"></div>
    <button class="btn wo-btn-primary">Show</button><a class="btn btn-link px-1" href="{{ route('activity.index') }}">Clear</a>
    <span class="small text-secondary w-100">{{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }} · {{ $today }} today · entries cannot be changed or deleted</span>
  </form>

  <div class="wo-card">
    <div class="table-responsive"><table class="table align-top wo-table m-0">
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
  </div>
</x-layouts.app>
