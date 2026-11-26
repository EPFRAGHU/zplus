<x-layouts.app title="Approvals" crumb="Approvals (owner)" :quiet-title="true">
  {{-- Approvals in the Work Orders style (owner, 26 Nov 2026): figure boxes, tabs, agency / type filters and one table per kind,
       details in columns and actions at the row end. Same rules as before: Super Admin approves, never the sender. --}}
  @php
    $types = \App\Models\Approval::TYPES;
    $fAgency = request()->integer('agency') ?: null;
    $fType = array_key_exists((string) request('type'), $types) || request('type') === 'payment' ? (string) request('type') : null;
    $keep = fn ($items) => $items->when($fAgency, fn ($c) => $c->where('agency_id', $fAgency))->when($fType, fn ($c) => $c->where('type', $fType))->values();
    $open = $keep($canApprove ? $pending : $waiting);
    $pay = $fType && $fType !== 'payment' ? collect() : $payments->when($fAgency, fn ($c) => $c->where('agency_id', $fAgency))->values();
    if ($fType === 'payment') { $open = collect(); }
    $ret = $keep($returned);
    $rec = $keep($recent);
    $all = $canApprove ? $pending : $waiting;
    $bills = ['attendance', 'sub_bill', 'tax_invoice', 'payout'];
    $changes = ['employee_change', 'post_rate'];
    $when = fn ($t) => $t?->timezone('Asia/Kolkata')->format('d M Y, h:i A');
    $days = fn ($a) => (int) $a->submitted_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
    $ageBadge = fn ($d) => '<span class="badge '.($d >= 3 ? 'text-bg-warning' : 'text-bg-light border text-secondary').'">'.($d === 0 ? 'sent today' : $d.' '.\Illuminate\Support\Str::plural('day', $d).' waiting').'</span>';
    $amount = fn ($a) => match ($a->type) {
        'sub_bill' => $a->invoice ? inr((float) $a->invoice->rounded_total, true) : '—',
        'tax_invoice' => $a->taxInvoice ? inr((float) $a->taxInvoice->rounded_total, true) : '—',
        default => '—',
    };
    $docs = fn ($a) => match (true) {
        $a->type === 'payout' => '<a target="_blank" href="'.e(route('bank-letters.pdf', ['agency' => $a->agency_id, 'month' => $a->month->format('Y-m')])).'"><i class="bi bi-file-earmark-pdf"></i> Bank letter</a>',
        $a->type === 'sub_bill' && $a->invoice_id => '<a target="_blank" href="'.e(route('invoices.pdf', $a->invoice_id)).'"><i class="bi bi-file-earmark-pdf"></i> PDF</a>',
        $a->type === 'tax_invoice' && $a->tax_invoice_id => '<a target="_blank" href="'.e(route('tax-invoices.pdf', $a->tax_invoice_id)).'"><i class="bi bi-file-earmark-pdf"></i> PDF</a>',
        default => '',
    };
    $item = fn ($a) => $a->url() ? '<a class="fw-semibold text-decoration-none" href="'.e($a->url()).'">'.e($a->label).'</a>' : '<span class="fw-semibold">'.e($a->label).'</span>';
    $tab = in_array(request('tab'), ['waiting', 'returned', 'recent'], true) ? request('tab') : 'waiting';
    $q = fn (array $x = []) => array_filter(array_merge(['tab' => $tab, 'agency' => $fAgency, 'type' => $fType], $x));
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-person-check"></i></div>
      <div class="me-auto"><h1>Approvals</h1>
        <p>{{ $canApprove ? 'Check each item, then Approve or Return it with a reason. You never approve what you sent yourself (four-eyes rule).' : 'What you sent to the Super Admin, what was returned to you, and recent decisions.' }}</p></div>
    </div>
    @error('approval')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @error('reason')<div class="alert alert-danger small">{{ $message }}</div>@enderror

    <div class="wo-kpis">
      <div class="wo-kpi k-amber"><i class="bi bi-inbox"></i><div><div class="l">{{ $canApprove ? 'Waiting for your approval' : 'Sent, waiting' }}</div><div class="v">{{ $all->count() + $payments->count() }}</div></div></div>
      <div class="wo-kpi k-blue"><i class="bi bi-person-plus"></i><div><div class="l">New employees</div><div class="v">{{ $all->where('type', 'new_employee')->count() }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-receipt"></i><div><div class="l">Attendance, bills & payouts</div><div class="v">{{ $all->whereIn('type', $bills)->count() }}</div></div></div>
      <div class="wo-kpi k-green"><i class="bi bi-cash-coin"></i><div><div class="l">Payments received</div><div class="v">{{ $payments->count() }}</div></div></div>
      <div class="wo-kpi k-red"><i class="bi bi-arrow-return-left"></i><div><div class="l">Returned to you</div><div class="v">{{ $returned->count() }}</div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('approvals.index') }}" id="ap-form">
      <input type="hidden" name="tab" value="{{ $tab }}" data-ap-tab-input>
      <nav class="wo-tabs" aria-label="Approvals">
        <a href="{{ route('approvals.index', $q(['tab' => 'waiting'])) }}" data-ap-tab="waiting" @class(['on' => $tab === 'waiting'])>{{ $canApprove ? 'Waiting for approval' : 'Sent, waiting' }} ({{ $open->count() + $pay->count() }})</a>
        <a href="{{ route('approvals.index', $q(['tab' => 'returned'])) }}" data-ap-tab="returned" @class(['on' => $tab === 'returned'])>Returned to you ({{ $ret->count() }})</a>
        <a href="{{ route('approvals.index', $q(['tab' => 'recent'])) }}" data-ap-tab="recent" @class(['on' => $tab === 'recent'])>Recent decisions ({{ $rec->count() }})</a>
      </nav>
      @if($agencies->count() > 1)
        <label class="wo-pick"><span>Agency:</span>
          <select name="agency" class="form-select" onchange="this.form.submit()"><option value="">All</option>@foreach($agencies as $ag)<option value="{{ $ag->id }}" @selected($fAgency === $ag->id)>{{ $ag->name }}</option>@endforeach</select></label>
      @endif
      <label class="wo-pick"><span>Type:</span>
        <select name="type" class="form-select" onchange="this.form.submit()"><option value="">All</option>
          @foreach($types as $k => $label)<option value="{{ $k }}" @selected($fType === $k)>{{ $label }}</option>@endforeach
          <option value="payment" @selected($fType === 'payment')>Payment received</option></select></label>
      @if($fAgency || $fType)<a class="btn btn-link px-1" href="{{ route('approvals.index', ['tab' => $tab]) }}">Clear</a>@endif
    </form>

    {{-- ============ Waiting ============ --}}
    <div data-ap-pane="waiting" @class(['d-none' => $tab !== 'waiting'])>
      @if($canApprove)<h2 class="visually-hidden">Waiting for your approval</h2>@else<h2 class="visually-hidden">Sent – waiting for Super Admin approval</h2>@endif
      @if($open->isEmpty() && $pay->isEmpty())
        <div class="wo-empty"><i class="bi bi-check2-circle fs-3 d-block mb-2 text-success"></i>{{ $canApprove ? 'Nothing is waiting for approval.' : 'Nothing is waiting. Everything you sent has been decided.' }}</div>
      @endif

      @php($newStaff = $open->where('type', 'new_employee')->values())
      @if($newStaff->isNotEmpty())
        <div class="wo-card mb-3">
          <div class="wo-card-h px-3 pt-3 pb-2 d-flex align-items-center gap-2"><i class="bi bi-person-plus text-primary"></i><b>New employees</b><span class="badge text-bg-light border">{{ $newStaff->count() }}</span>
            <span class="small text-secondary ms-2">Inactive until approved. Check post, bank and EPF / ESIC / TDS.</span></div>
          @if($canApprove)
            <div class="px-2 pb-2">@include('approvals._new-staff-table', ['items' => $newStaff])</div>
          @else
            <div class="table-responsive"><table class="table align-middle wo-table m-0 ap-table">
              <thead><tr><th>#</th><th>Agency</th><th>Employee</th><th>Sent by</th><th>Sent on</th><th class="text-center">Waiting</th></tr></thead>
              <tbody>@foreach($newStaff as $i => $a)
                <tr><td class="text-secondary">{{ $i + 1 }}</td><td><span class="wo-code">{{ $a->agency->code }}</span></td><td>{!! $item($a) !!}</td><td>{{ $a->submitter?->name ?? '—' }}</td><td class="small text-nowrap">{{ $when($a->submitted_at) }}</td><td class="text-center">{!! $ageBadge($days($a)) !!}</td></tr>
              @endforeach</tbody></table></div>
          @endif
        </div>
      @endif

      @php($billItems = $open->whereIn('type', $bills)->values())
      @if($billItems->isNotEmpty())
        <div class="wo-card mb-3">
          <div class="wo-card-h px-3 pt-3 pb-2 d-flex align-items-center gap-2"><i class="bi bi-receipt text-primary"></i><b>Attendance, sub-bills, tax invoices & salary payouts</b><span class="badge text-bg-light border">{{ $billItems->count() }}</span></div>
          <div class="table-responsive"><table class="table align-middle wo-table m-0 ap-table">
            <thead><tr><th>#</th><th>Agency</th><th>Type</th><th>Item</th><th>Month</th><th class="num">Amount</th><th>Documents</th><th>Sent by</th><th>Sent on</th><th class="text-center">Waiting</th>@if($canApprove)<th class="text-end">Actions</th>@endif</tr></thead>
            <tbody>
            @foreach($billItems as $i => $a)
              <tr>
                <td class="text-secondary">{{ $i + 1 }}</td><td><span class="wo-code">{{ $a->agency->code }}</span></td>
                <td><span class="badge text-bg-light border">{{ $types[$a->type] }}</span></td>
                <td>{!! $item($a) !!}</td>
                <td class="text-nowrap">{{ ($a->sheet?->month ?? $a->month)?->format('M Y') }}</td>
                <td class="num">{{ $amount($a) }}</td>
                <td class="small text-nowrap">{!! $docs($a) !!}</td>
                <td>{{ $a->submitter?->name ?? '—' }}</td><td class="small text-nowrap">{{ $when($a->submitted_at) }}</td>
                <td class="text-center">{!! $ageBadge($days($a)) !!}</td>
                @if($canApprove)<td class="text-end text-nowrap" style="min-width:200px">@include('approvals._row-actions', ['a' => $a])</td>@endif
              </tr>
            @endforeach
            </tbody>
          </table></div>
        </div>
      @endif

      @php($changeItems = $open->whereIn('type', $changes)->values())
      @if($changeItems->isNotEmpty())
        <div class="wo-card mb-3">
          <div class="wo-card-h px-3 pt-3 pb-2 d-flex align-items-center gap-2"><i class="bi bi-arrow-left-right text-primary"></i><b>Staff, pay & post rate changes</b><span class="badge text-bg-light border">{{ $changeItems->count() }}</span>
            <span class="small text-secondary ms-2">The old value stays in force until approved.</span></div>
          <div class="table-responsive"><table class="table align-middle wo-table m-0 ap-table">
            <thead><tr><th>#</th><th>Agency</th><th>Type</th><th>Employee / post</th><th style="min-width:320px">Change (now → changed to)</th><th>Sent by</th><th>Sent on</th><th class="text-center">Waiting</th>@if($canApprove)<th class="text-end">Actions</th>@endif</tr></thead>
            <tbody>
            @foreach($changeItems as $i => $a)
              <tr>
                <td class="text-secondary">{{ $i + 1 }}</td><td><span class="wo-code">{{ $a->agency->code }}</span></td>
                <td><span class="badge text-bg-light border">{{ $types[$a->type] }}</span></td>
                <td>{!! $item($a) !!}</td>
                <td>@include('approvals._changes', ['a' => $a])</td>
                <td>{{ $a->submitter?->name ?? '—' }}</td><td class="small text-nowrap">{{ $when($a->submitted_at) }}</td>
                <td class="text-center">{!! $ageBadge($days($a)) !!}</td>
                @if($canApprove)<td class="text-end text-nowrap" style="min-width:200px">@include('approvals._row-actions', ['a' => $a])</td>@endif
              </tr>
            @endforeach
            </tbody>
          </table></div>
        </div>
      @endif

      {{-- Payments received waiting for a Super Admin (owner, 22 Nov 2026): approved on each payment's page. --}}
      @if($pay->isNotEmpty())
        <div class="wo-card mb-3">
          <div class="wo-card-h px-3 pt-3 pb-2 d-flex align-items-center gap-2"><i class="bi bi-cash-coin text-success"></i><b>Payments received waiting for approval</b><span class="badge text-bg-light border">{{ $pay->count() }}</span>
            <span class="small text-secondary ms-2">Open each, check it against the bank statement and payment advice, then Approve or Return.</span></div>
          <div class="table-responsive"><table class="table align-middle wo-table m-0 ap-table">
            <thead><tr><th>#</th><th>Agency</th><th>Work order</th><th>Mode / UTR / cheque</th><th>Credit date</th><th>Sub-bills</th><th class="num">Amount credited</th><th>Entered by</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @foreach($pay as $i => $p)
              <tr>
                <td class="text-secondary">{{ $i + 1 }}</td><td><span class="wo-code">{{ $p->agency?->code }}</span></td>
                <td class="mono small">{{ $p->contract?->contract_no }}</td>
                <td>{{ \App\Models\PaymentReceipt::MODES[$p->mode] ?? $p->mode }} <span class="mono small">{{ $p->reference() }}</span></td>
                <td class="text-nowrap">{{ $p->credit_date->format('d M Y') }}</td>
                <td class="small mono">{{ $p->lines->map(fn ($l) => $l->invoice?->invoice_no)->filter()->implode(', ') }}</td>
                <td class="num fw-semibold">{{ inr((float) $p->amount, true) }}</td>
                <td>{{ $p->enteredBy?->name ?? '—' }}</td>
                <td class="text-end"><a class="btn btn-sm wo-btn-primary" href="{{ route('payments.show', $p) }}">{{ $canApprove ? 'Open to approve' : 'Open' }}</a></td>
              </tr>
            @endforeach
            </tbody>
          </table></div>
        </div>
      @endif
    </div>

    {{-- ============ Returned ============ --}}
    <div data-ap-pane="returned" @class(['d-none' => $tab !== 'returned'])>
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 ap-table">
        <thead><tr><th>Returned on</th><th>Agency</th><th>Type</th><th>Item</th><th style="min-width:260px">Reason</th><th>Returned by</th></tr></thead>
        <tbody>
        @forelse($ret as $a)
          <tr><td class="small text-nowrap">{{ $when($a->decided_at) }}</td><td><span class="wo-code">{{ $a->agency->code }}</span></td>
            <td><span class="badge text-bg-light border">{{ $types[$a->type] }}</span></td><td>{!! $item($a) !!}</td>
            <td class="fw-semibold text-danger-emphasis">{{ $a->reason }}</td><td>{{ $a->decider?->name }}</td></tr>
        @empty
          <tr><td colspan="6" class="text-center text-secondary py-4">Nothing returned to you.</td></tr>
        @endforelse
        </tbody>
      </table></div></div>
      @if($ret->isNotEmpty())<p class="small text-secondary mt-2">Correct each item on its page, then use "Send again for approval" there.</p>@endif
    </div>

    {{-- ============ Recent ============ --}}
    <div data-ap-pane="recent" @class(['d-none' => $tab !== 'recent'])>
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 ap-table">
        <thead><tr><th>Decided</th><th>Agency</th><th>Type</th><th>Item</th><th>Sent by</th><th>Decision</th><th>By</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse($rec as $a)
          <tr><td class="small text-nowrap">{{ $when($a->decided_at) }}</td><td><span class="wo-code">{{ $a->agency->code }}</span></td>
            <td><span class="badge text-bg-light border">{{ $types[$a->type] }}</span></td><td>{!! $item($a) !!}</td>
            <td>{{ $a->submitter?->name }}</td>
            <td>@if($a->status === 'approved')<span class="badge text-bg-success">Approved</span>@else<span class="badge text-bg-danger">Returned</span>@endif</td>
            <td>{{ $a->decider?->name }}</td><td class="small">{{ $a->status === 'returned' ? $a->reason : '' }}</td></tr>
        @empty
          <tr><td colspan="8" class="text-center text-secondary py-4">No decisions yet.</td></tr>
        @endforelse
        </tbody>
      </table></div></div>
    </div>
  </div>
  <script>
    (function () {
      // Tabs switch in place (the address keeps ?tab=… so a refresh or a filter change stays on the same tab).
      const input = document.querySelector('[data-ap-tab-input]');
      document.querySelectorAll('[data-ap-tab]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          const t = a.dataset.apTab;
          document.querySelectorAll('[data-ap-tab]').forEach(x => x.classList.toggle('on', x === a));
          document.querySelectorAll('[data-ap-pane]').forEach(p => p.classList.toggle('d-none', p.dataset.apPane !== t));
          input.value = t;
          history.replaceState(null, '', a.href);
        });
      });
    })();
  </script>
</x-layouts.app>
