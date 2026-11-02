@php($c = $analysis['counts'])
@php($toSave = $c['new'] + $c['update'])
@php($badge = ['new' => ['New', 'success'], 'update' => ['Update', 'info'], 'unchanged' => ['No change', 'secondary'], 'error' => ['Error', 'danger']])
@php($headers = collect($sheet->columns())->mapWithKeys(fn ($col) => [$col->key => $col->header])->all() + ['agency_id' => 'Agency', 'client_id' => 'Client', 'gem_contract_id' => 'GeM contract', 'project_id' => 'Project', 'post_id' => 'Post'])
<x-layouts.app :title="'Check: '.$sheet->title()" :crumb="'Setup / <a href=\''.route('master-import.index').'\'>Data Upload</a>'">
  <div class="z-card p-3 mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-center">
      <div><div class="small text-secondary">File</div><div class="fw-semibold">{{ $fileName }}</div></div>
      <span class="badge text-bg-success fs-6">{{ $c['new'] }} new</span>
      <span class="badge text-bg-info fs-6">{{ $c['update'] }} to update</span>
      <span class="badge text-bg-secondary fs-6">{{ $c['unchanged'] }} no change</span>
      <span class="badge text-bg-danger fs-6">{{ $c['error'] }} with errors</span>
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
</x-layouts.app>
