{{-- New employees waiting for approval, one row each (owner, 23 Nov 2026): details in columns, actions at the end. $items: Approval[] --}}
@php
  $staff = app(\App\Services\Approvals\StaffChanges::class);
  $v = fn ($a, $f) => $a->changes[$f] ?? null;
  $fullAccount = auth()->user()->can('view-bank-details');
  $onOff = fn ($x) => $x === '1' ? '<span class="badge rounded-pill text-bg-success">On</span>' : '<span class="badge rounded-pill text-bg-light border text-secondary">Off</span>';
@endphp
<div class="table-responsive"><table class="table table-sm align-middle mb-0 appr-table" style="font-size:.86rem">
  <thead><tr><th style="width:3%">#</th><th style="min-width:140px">Employee</th><th style="min-width:190px">Post</th><th style="min-width:110px">Bank</th><th style="min-width:110px">Name as per bank</th><th>A/c no.</th><th>IFSC</th>
    <th class="text-center">EPF</th><th class="text-center">ESIC</th><th class="text-center">TDS</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  @foreach($items as $i => $a)
    @php($post = $v($a, 'post_id') ? \App\Models\Post::query()->with('project')->find($v($a, 'post_id')) : null)
    @php($acct = $v($a, 'account_number'))
    @php($stale = $a->employee ? $staff->changedFields($a, $a->employee) : [])
    @php($own = (int) $a->submitted_by === (int) auth()->id())
    <tr @class(['table-warning' => $stale])>
      <td class="text-secondary">{{ $i + 1 }}</td>
      <td>
        @if($a->employee)<a class="fw-semibold text-decoration-none" href="{{ route('employees.show', $a->employee) }}">{{ $a->employee->name }}</a>@else<span class="fw-semibold">{{ $a->changes['name'] ?? '—' }}</span>@endif
        <div class="small text-secondary"><span class="mono">{{ $a->changes['emp_code'] ?? '' }}</span></div>
        <div class="small text-secondary">by {{ $a->submitter?->name ?? '—' }}, {{ $a->submitted_at->timezone('Asia/Kolkata')->format('d M, h:i A') }}</div>
      </td>
      <td>@if($post)<div>{{ $post->designation }}</div><div class="small text-secondary"><span class="badge text-bg-primary">{{ $post->project->code }}</span> ₹{{ number_format((float) ($v($a, 'rate_override') ?: $post->rate)) }}</div>@else<span class="text-secondary">—</span>@endif</td>
      <td>{{ $v($a, 'bank_name') ?: '—' }}</td>
      <td>{{ $v($a, 'account_holder') ?: '—' }}</td>
      <td class="mono">{{ $acct ? ($fullAccount ? $acct : '••••'.substr($acct, -4)) : '—' }}</td>
      <td class="mono">{{ $v($a, 'ifsc') ?: '—' }}</td>
      <td class="text-center">{!! $onOff($v($a, 'pf_applicable')) !!}</td>
      <td class="text-center">{!! $onOff($v($a, 'esic_applicable')) !!}</td>
      <td class="text-center">{!! $onOff($v($a, 'tds_applicable')) !!}</td>
      <td class="text-end" style="width:118px">
        <div class="d-grid gap-1">
        @if($own)
          <span class="small text-secondary" title="You sent this yourself, so another Super Admin must approve it (four-eyes rule)"><i class="bi bi-person-lock"></i> Another Super Admin approves</span>
        @else
          <form method="post" action="{{ route('approvals.approve', $a) }}" class="d-grid">@csrf
            <button class="btn btn-sm btn-success" @disabled($stale) title="{{ $stale ? 'Changed after sending: the maker must send it again' : 'Approve' }}"><i class="bi bi-check-lg me-1"></i>Approve</button></form>
          <div class="dropdown d-grid">
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-arrow-return-left me-1"></i>Return</button>
            <form method="post" action="{{ route('approvals.return', $a) }}" class="dropdown-menu dropdown-menu-end p-2" style="width:300px">@csrf
              <label class="form-label small mb-1" for="r-{{ $a->id }}">Reason for returning</label>
              <input id="r-{{ $a->id }}" name="reason" class="form-control form-control-sm mb-2" required minlength="5" maxlength="1000" placeholder="e.g. Wrong bank account number">
              <button class="btn btn-sm btn-danger w-100">Return to the maker</button>
            </form></div>
        @endif
        @can('delete-masters')@if($a->employee && ! \App\Support\SafeDelete::employee($a->employee))
          <form method="post" action="{{ route('employees.destroy', $a->employee) }}" class="d-grid" data-confirm="Delete {{ $a->employee->name }} ({{ $a->employee->emp_code }})? Use this only for an employee entered by mistake. This cannot be undone." onsubmit="return confirm(this.dataset.confirm)">
            @csrf @method('delete')<input type="hidden" name="back" value="approvals">
            <button class="btn btn-sm btn-link text-danger py-0" title="Delete this employee (entered by mistake)"><i class="bi bi-trash me-1"></i>Delete</button></form>
        @endif @endcan
        </div>
      </td>
    </tr>
    @if($stale)
      <tr class="table-warning"><td></td><td colspan="10" class="small"><i class="bi bi-exclamation-triangle me-1"></i>Changed after sending: <b>{{ implode(', ', $stale) }}</b>. The maker must use "Send again for approval" on the employee's page, or Return it with a note.</td></tr>
    @endif
  @endforeach
  </tbody>
</table></div>
@error('reason')<div class="small text-danger px-3 py-1">{{ $message }}</div>@enderror
