{{-- Approve / Return buttons for one pending approval ($a), for the owner. --}}
@if((int) $a->submitted_by === (int) auth()->id())
  <div class="small text-secondary mt-2"><i class="bi bi-info-circle"></i> You sent this yourself, so another owner must approve it (four-eyes rule).</div>
@else
  <div class="d-flex flex-wrap gap-2 align-items-start mt-2">
    <form method="post" action="{{ route('approvals.approve', $a) }}">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button></form>
    <form method="post" action="{{ route('approvals.return', $a) }}" class="d-flex flex-wrap gap-2 flex-grow-1">@csrf
      <input name="reason" class="form-control form-control-sm @error('reason') is-invalid @enderror" style="max-width:420px" placeholder="Reason for returning (required)" required minlength="5" maxlength="1000" aria-label="Reason for returning">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-return-left me-1"></i>Return</button>
      @error('reason')<div class="small text-danger w-100">{{ $message }}</div>@enderror
    </form>
  </div>
@endif
