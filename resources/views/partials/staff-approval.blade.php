{{-- Owner approval of a new employee and of held changes (post, rate, bank, EPF/ESIC/TDS) of $employee. --}}
@php($items = \App\Models\Approval::query()->with('submitter', 'decider')->whereIn('type', ['new_employee', 'employee_change'])->where('employee_id', $employee->id)->latest('id')->limit(10)->get())
@php($open = $items->where('status', 'pending'))
@php($isOwner = \App\Services\Approvals\Approvals::canApprove(auth()->user()))
@if($items->isNotEmpty())
<div class="z-card p-3 mb-3 border-start border-4 {{ $open->isNotEmpty() ? 'border-info' : ($items->first()->status === 'returned' ? 'border-danger' : 'border-success') }}" id="approval">
  <h2 class="h6 fw-bold mb-1"><i class="bi bi-person-check me-1"></i>Owner approval</h2>
  @error('approval')<div class="alert alert-danger small mb-2">{{ $message }}</div>@enderror
  @foreach($open as $a)
    <div class="mb-2"><span class="badge text-bg-info">Waiting for the owner</span> <b class="small">{{ \App\Models\Approval::TYPES[$a->type] }}</b>
      <span class="small text-secondary">sent by {{ $a->submitter?->name }}, {{ $a->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}. Until approved {{ $a->type === 'new_employee' ? 'the employee stays Inactive.' : 'the old details stay in use.' }}</span>
      @include('approvals._changes', ['a' => $a])
      @if($a->type === 'new_employee' && ($stale = app(\App\Services\Approvals\StaffChanges::class)->changedFields($a, $employee)))
        <div class="alert alert-warning small py-2 my-2 d-flex flex-wrap gap-2 align-items-center"><span><i class="bi bi-exclamation-triangle me-1"></i>Details changed after sending: <b>{{ implode(', ', $stale) }}</b>. Send it again so the owner approves the current details.</span>
          @can('manage-masters')<form method="post" action="{{ route('employees.send-for-approval', $employee) }}" class="ms-auto">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send-check me-1"></i>Send again for approval</button></form>@endcan</div>
      @endif
      @if($isOwner)@include('approvals._decide', ['a' => $a])@endif
    </div>
  @endforeach
  @php($lastNew = $items->where('type', 'new_employee')->first())
  @if($lastNew && in_array($lastNew->status, ['returned', 'superseded'], true) && ! $open->contains('type', 'new_employee'))
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2"><span class="badge text-bg-danger">Returned</span>
      <span class="small">by {{ $lastNew->decider?->name }}: <b>{{ $lastNew->reason }}</b>. Correct the details with Edit, then send again.</span>
      @can('manage-masters')<form method="post" action="{{ route('employees.send-for-approval', $employee) }}" class="ms-auto">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send-check me-1"></i>Send again for approval</button></form>@endcan
    </div>
  @endif
  <details class="small"><summary class="text-secondary">History</summary><ul class="mb-0">
    @foreach($items as $h)<li>{{ $h->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}: {{ $h->label }} – sent by {{ $h->submitter?->name ?? '—' }} – <b>{{ \App\Models\Approval::STATUSES[$h->status] }}</b>@if($h->decided_at) by {{ $h->decider?->name }}@endif @if($h->reason) – “{{ $h->reason }}”@endif</li>@endforeach
  </ul></details>
</div>
@endif
