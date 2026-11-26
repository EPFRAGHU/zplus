{{-- Approve / Return for one pending approval ($a) in a table row (owner, 26 Nov 2026). The four-eyes rule: never the sender. --}}
@if((int) $a->submitted_by === (int) auth()->id())
  <span class="small text-secondary" title="You sent this yourself, so another Super Admin must approve it (four-eyes rule)"><i class="bi bi-person-lock"></i> Another Super Admin approves</span>
@else
  <div class="d-flex gap-1 justify-content-end">
    <form method="post" action="{{ route('approvals.approve', $a) }}">@csrf
      <button class="btn btn-sm btn-success text-nowrap" @disabled($stale ?? false) title="{{ ($stale ?? false) ? 'Changed after sending: the maker must send it again' : 'Approve' }}"><i class="bi bi-check-lg me-1"></i>Approve</button></form>
    <div class="dropdown">
      <button type="button" class="btn btn-sm btn-outline-danger text-nowrap" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-arrow-return-left me-1"></i>Return</button>
      <form method="post" action="{{ route('approvals.return', $a) }}" class="dropdown-menu dropdown-menu-end p-2" style="width:300px">@csrf
        <label class="form-label small mb-1" for="rr-{{ $a->id }}">Reason for returning</label>
        <input id="rr-{{ $a->id }}" name="reason" class="form-control form-control-sm mb-2" required minlength="5" maxlength="1000" placeholder="e.g. Two days wrong for SW-1001">
        <button class="btn btn-sm btn-danger w-100">Return to the maker</button>
      </form></div>
  </div>
@endif
