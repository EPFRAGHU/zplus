@php($c = $analysis['counts'])
@php($toSave = $c['new'] + $c['update'])
@php($badge = ['new' => ['New', 'success'], 'update' => ['Update', 'info'], 'unchanged' => ['No change', 'secondary'], 'error' => ['Error', 'danger']])
@php($headers = collect($sheet->columns())->mapWithKeys(fn ($col) => [$col->key => $col->header])->all() + ['agency_id' => 'Agency', 'client_id' => 'Client', 'gem_contract_id' => 'Work order', 'project_id' => 'Project', 'post_id' => 'Post'])
<x-layouts.app :title="'Check: '.$sheet->title()" :crumb="'Setup / <a href=\''.route('master-import.index').'\'>Data Upload</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-file-earmark-check"></i></div>
    <div class="me-auto"><h1>Check: {{ $sheet->title() }}</h1>
      <p>File <b class="text-body">{{ $fileName }}</b>. Rows with errors are skipped; nothing is saved until you click Save.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-green"><i class="bi bi-plus-circle"></i><div><div class="l">New</div><div class="v">{{ $c['new'] }}</div></div></div>
    <div class="wo-kpi k-blue"><i class="bi bi-arrow-repeat"></i><div><div class="l">To update</div><div class="v">{{ $c['update'] }}</div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-dash-circle"></i><div><div class="l">No change</div><div class="v">{{ $c['unchanged'] }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-exclamation-triangle"></i><div><div class="l">With errors</div><div class="v">{{ $c['error'] }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-check2-circle"></i><div><div class="l">Will be saved</div><div class="v">{{ $toSave }}</div></div></div>
  </div>

  <div class="z-card p-3 mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-center">
      <div><div class="small text-secondary">File</div><div class="fw-semibold">{{ $fileName }}</div></div>
      <span class="small text-secondary">{{ $c['new'] }} new · {{ $c['update'] }} to update · {{ $c['unchanged'] }} no change · {{ $c['error'] }} with errors</span>
      <div class="ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('master-import.index') }}">Cancel</a>
        <form method="post" action="{{ route('master-import.commit', $sheet->type()) }}">@csrf
          <button class="btn btn-primary" @disabled($toSave === 0)><i class="bi bi-check2-circle me-1"></i>Save {{ $toSave }} {{ \Illuminate\Support\Str::plural('row', $toSave) }}</button>
        </form>
      </div>
    </div>
    @if($c['error'])<div class="alert alert-warning small mt-3 mb-0">Rows with errors will be <b>skipped</b>. Fix them in the Excel file and upload it again; rows already saved will show as "No change".</div>@endif
    @if($toSave === 0 && ! $c['error'])<div class="alert alert-light border small mt-3 mb-0">Everything in this file is already in the app.</div>@endif
  </div>

  <div class="z-card">
    <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Row</th><th>Result</th><th>Record</th><th>Details</th></tr></thead>
        <tbody>
        @foreach(collect($analysis['rows'])->sortBy(fn ($r) => [$r->status === 'error' ? 0 : 1, $r->row]) as $r)
          @php([$label, $cls] = $badge[$r->status])
          <tr class="{{ $r->status === 'error' ? 'table-danger' : '' }}">
            <td class="mono">{{ $r->row }}</td>
            <td><span class="badge text-bg-{{ $cls }}">{{ $label }}</span></td>
            <td>{{ $r->label }}</td>
            <td class="small">
              @if($r->errors)<ul class="mb-0 ps-3 text-danger">@foreach($r->errors as $e)<li>{{ $e }}</li>@endforeach</ul>
              @elseif($r->status === 'update')Changes: {{ collect($r->changes)->map(fn ($k) => $headers[$k] ?? str_replace('_', ' ', $k))->unique()->implode(', ') }}
              @elseif($r->status === 'new')Will be added
              @else<span class="text-secondary">Same as in the app</span>@endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
  </div>
</x-layouts.app>
