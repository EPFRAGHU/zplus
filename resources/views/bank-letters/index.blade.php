<x-layouts.app title="Bank Letter" crumb="Operations / <span class='text-danger'>Bank Letter</span>">
  <form method="get" class="z-card p-3 mb-3 d-flex gap-2 flex-wrap align-items-end">
    <div><label class="form-label small text-secondary mb-1" for="ag">Agency</label>
      <select id="ag" name="agency" class="form-select" required><option value="">Choose agency</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agency?->id === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="m">Month</label><x-month-picker :value="$month->format('Y-m')" required /></div>
    <button class="btn btn-outline-secondary">Show employees</button>
    <span class="small text-secondary align-self-center">One salary transfer letter for all the agency's employees of the month, on its own letterhead. Net pay as on the salary payout sheet.</span>
  </form>

  @if($agency && $all->isNotEmpty())
  @include('partials.approval-box', ['type' => 'payout', 'subject' => ['agency' => $agency, 'month' => $month], 'sendAction' => route('approvals.send.payout'),
    'sendFields' => ['agency' => $agency->id, 'month' => $month->format('Y-m')], 'canSend' => auth()->user()->can('view-bank-details')])
  @endif
  @if($letter)
    @php($rs = fn (int $paise) => inr($paise / 100, true))
    @if($pending->isNotEmpty())
      <div class="alert alert-warning small"><b>Not included – attendance not locked yet:</b> {{ $pending->map(fn ($s) => $s->project->name.' ('.$s->project->code.')')->implode(', ') }}. Lock those months on the Attendance page to include their staff.</div>
    @endif
    @if($all->isEmpty())
      <div class="z-card p-4 text-center text-secondary">No locked attendance for {{ $agency->name }} in {{ $month->format('F Y') }}.</div>
    @else
    <form method="get" action="{{ route('bank-letters.pdf') }}" target="_blank" class="z-card mb-3">
      <input type="hidden" name="agency" value="{{ $agency->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
      <div class="z-card-h flex-wrap gap-2"><h2>{{ $agency->name }} · {{ $month->format('F Y') }}</h2>
        <span class="small text-secondary">{{ $letter['sbi']->count() + $letter['neft']->count() }} employees · total <b>{{ $rs($letter['total']) }}</b></span>
        @php($payoutOk = \App\Services\Approvals\Approvals::canApprove(auth()->user()) || $all->every(fn ($s) => app(\App\Services\Approvals\Approvals::class)->payoutClearedFor($s)))
        <button class="btn btn-primary ms-auto" @disabled(! $payoutOk) title="{{ $payoutOk ? '' : 'The owner must approve this month\'s salary payout first' }}"><i class="bi bi-file-earmark-pdf me-1"></i>Bank letter PDF</button>
        <button class="btn btn-outline-success" formaction="{{ route('bank-letters.excel') }}" formtarget="_self" @disabled(! $payoutOk)><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        {{-- NEFT intimation file in the bank's bulk upload layout (owner, 26 Nov 2026). --}}
        <button class="btn btn-success" formaction="{{ route('bank-letters.neft-excel') }}" formtarget="_self" @disabled(! $payoutOk) title="Bulk upload sheet for the bank: sn, corp id, corp name, corp acct no, product code, benf name, benf acct no, amount, ifsc, remarks, CURRENCY, ADDRESS"><i class="bi bi-file-earmark-excel me-1"></i>NEFT file (Excel)</button>
        <button class="btn btn-danger" formaction="{{ route('bank-letters.neft-pdf') }}" formtarget="_blank" @disabled(! $payoutOk)><i class="bi bi-file-earmark-pdf me-1"></i>NEFT file (PDF)</button></div>
      <div class="px-3 pt-2 small"><b>Projects in the letter</b> (untick to leave one out, then click <b>Update</b>):
        @foreach($all as $s)
          <label class="ms-2 text-nowrap"><input type="checkbox" class="form-check-input" name="projects[]" value="{{ $s->project_id }}" form="pick" @checked(in_array($s->project_id, $chosen))> {{ $s->project->name }} <span class="mono">({{ $s->project->code }})</span></label>
          <input type="hidden" name="projects[]" value="{{ $s->project_id }}" @disabled(! in_array($s->project_id, $chosen))>
        @endforeach
        <button class="btn btn-sm btn-outline-secondary ms-2" form="pick">Update</button>
      </div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Emp ID</th><th>Employee (name as per bank)</th><th>Project</th><th>Bank</th><th>Account no.</th><th>IFSC</th><th class="num">Net salary</th><th>Paid by</th></tr></thead>
        <tbody>
        @foreach($letter['sbi']->concat($letter['neft'])->sortBy(fn ($l) => $l['employee']->emp_code, SORT_NATURAL) as $l)
          @php($e = $l['employee'])
          @php($isSbi = str_starts_with(strtoupper((string) $e->ifsc), 'SBIN'))
          <tr><td class="mono">{{ $e->emp_code }}</td><td>{{ $e->account_holder ?: $e->name }}</td><td class="mono small">{{ $l['project']->code }}</td>
            <td class="small">{{ $e->bank_name }}</td><td class="mono">{{ $e->account_number }}</td><td class="mono">{{ $isSbi ? '—' : $e->ifsc }}</td>
            <td class="num">{{ $rs($l['net']) }}</td><td><span class="badge {{ $isSbi ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $isSbi ? 'SBI' : 'NEFT' }}</span></td></tr>
        @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="6">SBI ({{ $letter['sbi']->count() }})</td><td class="num">{{ $rs((int) $letter['sbi']->sum('net')) }}</td><td></td></tr>
          <tr><td colspan="6">Other banks by NEFT ({{ $letter['neft']->count() }})</td><td class="num">{{ $rs((int) $letter['neft']->sum('net')) }}</td><td></td></tr>
          <tr class="fw-bold"><td colspan="6">Total to be debited</td><td class="num">{{ $rs($letter['total']) }}</td><td></td></tr>
        </tfoot>
      </table></div>
      @if($letter['missing']->isNotEmpty())
        <div class="alert alert-danger small m-3"><b>Bank details missing</b> – not in the letter until added on the employee: {{ $letter['missing']->map(fn ($l) => $l['employee']->emp_code.' '.$l['employee']->name.' ('.inr($l['net'] / 100, true).')')->implode('; ') }}</div>
      @endif
      @unless($agency->bank_corp_id && $agency->bank_corp_name && $agency->bank_account_no && $agency->bank_address_code)
        <div class="alert alert-warning small m-3">The NEFT file needs the agency's <b>corporate ID, corporate name, account number and address code</b> (as given by the bank); missing ones stay blank in the file. Add them under <a href="{{ route('agencies.edit', $agency) }}">Agencies → Edit → Bank salary upload</a>.</div>
      @endunless
      @unless($agency->bank_account_no && $agency->bank_name)
        <div class="alert alert-warning small m-3">The agency's own bank (name, branch, account no.) is not filled in, so the letter shows blanks. Add it under <a href="{{ route('agencies.edit', $agency) }}">Agencies → Edit</a>.</div>
      @endunless
    </form>
    @include('partials.signed-copy', ['signed' => \App\Models\SignedDocument::latestFor('bank_letter', agencyId: $agency->id, month: $month->toDateString()),
      'action' => route('bank-letters.signed'), 'hidden' => ['agency' => $agency->id, 'month' => $month->format('Y-m')]])
    <div class="mt-2"><a class="btn btn-outline-secondary" href="{{ route('month-documents.zip', ['agency' => $agency->id, 'month' => $month->format('Y-m')]) }}"><i class="bi bi-file-zip me-1"></i>Download all {{ $month->format('M Y') }} PDFs (ZIP)</a>
      <span class="small text-secondary ms-2">Sub-bills, covering letters, tax invoices and this bank letter – sign them together with the DSC token.</span></div>
    <form id="pick" method="get" action="{{ route('bank-letters.index') }}"><input type="hidden" name="agency" value="{{ $agency->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}"></form>
    @endif
  @elseif($agencies->count() > 1)
    <div class="z-card p-4 text-center text-secondary">Choose the agency and month, then click <b>Show employees</b>.</div>
  @endif
</x-layouts.app>
