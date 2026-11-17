{{-- Old → new values of a held staff / post change ($a). Account numbers are shown in full only to those who may see bank details. --}}
@php($fmt = function ($field, $v) {
    if ($v === null) return '—';
    if (in_array($field, ['pf_applicable', 'esic_applicable', 'tds_applicable'], true)) return $v === '1' ? 'On' : 'Off';
    if ($field === 'post_id') { $p = \App\Models\Post::query()->with('project')->find($v); return $p ? $p->designation.' ('.$p->project->code.', ₹'.number_format((float) $p->rate).')' : '#'.$v; }
    if ($field === 'account_number' && ! auth()->user()->can('view-bank-details')) return '••••'.substr($v, -4);
    if (in_array($field, ['rate', 'rate_override'], true)) return '₹'.number_format((float) $v, 2);
    return $v;
})
@if($a->type === 'new_employee')
  <div class="small text-secondary mt-1">
    @foreach(\App\Services\Approvals\StaffChanges::WATCHED as $f)@if(($a->changes[$f] ?? null) !== null)<span class="me-3">{{ \App\Services\Approvals\StaffChanges::LABELS[$f] }}: <b class="text-body">{{ $fmt($f, $a->changes[$f]) }}</b></span>@endif @endforeach
  </div>
@elseif($a->changes)
  <table class="table table-sm small mb-0 mt-1" style="max-width:720px">
    <thead><tr><th>Detail</th><th>Now</th><th>Changed to</th></tr></thead>
    <tbody>@foreach($a->changes as $f => [$old, $new])<tr><td>{{ \App\Services\Approvals\StaffChanges::LABELS[$f] ?? $f }}</td><td class="text-secondary">{{ $fmt($f, $old) }}</td><td class="fw-semibold">{{ $fmt($f, $new) }}</td></tr>@endforeach</tbody>
  </table>
@endif
