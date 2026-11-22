<x-layouts.app title="Payments received" crumb="Billing / <span class='text-danger'>Payments received</span>" :quiet-title="true">
  @php
    $money = fn ($v) => inr((float) $v, true);
    $q = fn (array $extra = []) => array_filter(array_merge(['tab' => $tab, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'contract' => $contract?->id, 'pending' => request('pending') ? 1 : null], $extra));
    $gstTds = (float) $kpi['tds_cgst'] + (float) $kpi['tds_sgst'] + (float) $kpi['tds_igst'];
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-cash-coin"></i></div>
      <div class="me-auto"><h1>Payments received</h1>
        <p>Money received from the client for the sub-bills: UTR / cheque, dates, the client's TDS (2%) and GST TDS (1% CGST + 1% SGST) and the net credited. Approved by a Super Admin; a sub-bill paid in full turns Paid.</p></div>
      <div class="wo-actions d-flex gap-2 flex-wrap">
        <a class="btn wo-btn-primary" href="{{ route('payments.create', array_filter(['contract' => $contract?->id])) }}"><i class="bi bi-plus-lg me-1"></i>Enter payment received</a>
      </div>
    </div>
    @error('receipt')<div class="alert alert-danger small">{{ $message }}</div>@enderror

    <div class="wo-kpis">
      <div class="wo-kpi k-green"><i class="bi bi-bank"></i><div><div class="l">Received (approved)</div><div class="v" style="font-size:1.2rem">{{ $money($kpi['received']) }}</div></div></div>
      <div class="wo-kpi k-blue"><i class="bi bi-percent"></i><div><div class="l">TDS (income tax)</div><div class="v" style="font-size:1.2rem">{{ $money($kpi['tds_it']) }}</div></div></div>
      <div class="wo-kpi k-violet"><i class="bi bi-receipt-cutoff"></i><div><div class="l">GST TDS</div><div class="v" style="font-size:1.2rem">{{ $money($gstTds) }}</div></div></div>
      <div class="wo-kpi k-red"><i class="bi bi-hourglass-split"></i><div><div class="l">Outstanding now</div><div class="v" style="font-size:1.2rem">{{ $money($outstanding['total']['due']) }}</div></div></div>
      <div class="wo-kpi k-amber"><i class="bi bi-person-check"></i><div><div class="l">Waiting for approval</div><div class="v">{{ $kpi['waiting'] }}</div></div></div>
    </div>

    <form class="wo-bar" method="get" action="{{ route('payments.index') }}">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <nav class="wo-tabs" aria-label="Payments">
        @foreach($tabs as $k => $label)
          <a href="{{ route('payments.index', $q(['tab' => $k])) }}" @class(['on' => $tab === $k])>{{ $label }}@if($k === 'outstanding') ({{ $outstanding['rows']->count() }})@elseif($k === 'receipts') ({{ $receipts->count() }})@endif</a>
        @endforeach
      </nav>
      @if($tab !== 'outstanding')
        <label class="wo-pick"><span>From:</span><input type="date" class="form-control" name="from" value="{{ $from->format('Y-m-d') }}"></label>
        <label class="wo-pick"><span>To:</span><input type="date" class="form-control" name="to" value="{{ $to->format('Y-m-d') }}"></label>
      @endif
      <label class="wo-pick"><span>Work order:</span>
        <select name="contract" class="form-select"><option value="">All</option>
          @foreach($contracts as $c)<option value="{{ $c->id }}" @selected($contract?->id === $c->id)>{{ $c->contract_no }} – {{ $c->client?->short_code ?: $c->client?->name }} ({{ $c->agency?->code }})</option>@endforeach</select></label>
      @if($tab === 'tds')<label class="form-check ms-1"><input class="form-check-input" type="checkbox" name="pending" value="1" @checked(request('pending'))> <span class="form-check-label small">Only not yet seen / accepted</span></label>@endif
      <button class="btn wo-btn-primary">Show</button>
      <span class="ms-auto d-flex gap-2">
        <a class="btn wo-btn-green" href="{{ route('payments.export', ['tab' => $tab, 'format' => 'xlsx'] + $q()) }}"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <a class="btn wo-btn-line" href="{{ route('payments.export', ['tab' => $tab, 'format' => 'pdf'] + $q()) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
      </span>
    </form>
    <p class="small text-secondary mb-2">
      @if($tab === 'outstanding') Sub-bills submitted to the client and not yet paid in full, as on today (by bill date).
      @else Credited {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} (default: this financial year). Totals count approved payments only. @endif
    </p>

    @if($tab === 'receipts')
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table">
        <thead><tr><th>Credit date</th><th>Work order</th><th>Mode / reference</th><th>Sub-bills</th><th class="num">Bill amount</th><th class="num">TDS</th><th class="num">GST TDS</th><th class="num">Other</th><th class="num">Received</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($receipts as $r)
          @php($t = \App\Services\Payments\PaymentReports::lineTotals($r->lines))
          <tr>
            <td><a class="fw-semibold text-decoration-none" href="{{ route('payments.show', $r) }}">{{ $r->credit_date->format('d M Y') }}</a></td>
            <td><span class="wo-code">{{ $r->agency?->code }}</span> <span class="mono small">{{ $r->contract?->contract_no }}</span></td>
            <td>{{ \App\Models\PaymentReceipt::MODES[$r->mode] ?? $r->mode }} <span class="mono small">{{ $r->reference() }}</span></td>
            <td class="small mono">{{ $r->lines->map(fn ($l) => $l->invoice?->invoice_no)->implode(', ') }}</td>
            <td class="num">{{ $money($t['bill_amount']) }}</td><td class="num">{{ $money($t['tds_it']) }}</td>
            <td class="num">{{ $money((float) $t['tds_cgst'] + (float) $t['tds_sgst'] + (float) $t['tds_igst']) }}</td><td class="num">{{ $money($t['other_deduction']) }}</td>
            <td class="num fw-semibold">{{ $money($t['received']) }}</td>
            <td>@include('payments._status', ['r' => $r])</td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-secondary py-4">No payments entered for this period. Use <b>Enter payment received</b> when the client's money is credited.</td></tr>
        @endforelse
        </tbody>
        @if($receipts->where('status', 'approved')->isNotEmpty())
          <tfoot><tr class="bills-grand"><td colspan="4">Total of approved payments ({{ $receipts->where('status', 'approved')->count() }})</td>
            <td class="num">{{ $money($kpi['bill_amount']) }}</td><td class="num">{{ $money($kpi['tds_it']) }}</td><td class="num">{{ $money($gstTds) }}</td><td class="num">{{ $money($kpi['other_deduction']) }}</td><td class="num">{{ $money($kpi['received']) }}</td><td></td></tr></tfoot>
        @endif
      </table></div></div>

    @elseif($tab === 'outstanding')
      <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach(\App\Services\Payments\PaymentReports::AGES as $k => $label)
          <span class="badge rounded-pill {{ $k === '90+' ? 'text-bg-danger' : ($k === '61-90' ? 'text-bg-warning' : 'text-bg-light border') }} fs-6 fw-normal">{{ $label }}: <b>{{ $money($outstanding['ages'][$k]) }}</b></span>
        @endforeach
      </div>
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table">
        <thead><tr><th>Sub-bill no.</th><th>Work order</th><th>Month</th><th>Bill date</th><th class="text-center">Days</th><th class="num">Bill amount</th><th class="num">Received + deducted</th><th class="num">Still due</th><th></th></tr></thead>
        <tbody>
        @forelse($outstanding['rows'] as $r)
          <tr @class(['wo-over' => $r['age'] === '90+'])>
            <td><a class="mono fw-semibold text-decoration-none" href="{{ route('invoices.show', $r['bill']) }}">{{ $r['bill']->invoice_no }}</a></td>
            <td><span class="wo-code">{{ $r['bill']->contract?->agency?->code }}</span> <span class="mono small">{{ $r['bill']->contract?->contract_no }}</span></td>
            <td>{{ $r['bill']->month->format('M Y') }}</td><td>{{ $r['bill']->invoice_date?->format('d M Y') }}</td><td class="text-center">{{ $r['days'] }}</td>
            <td class="num">{{ $money($r['billed']) }}</td><td class="num">{{ $money($r['settled']) }}</td><td class="num fw-semibold">{{ $money($r['due']) }}</td>
            <td><a class="btn btn-sm wo-btn-line py-0" href="{{ route('payments.create', ['contract' => $r['bill']->gem_contract_id, 'bills' => [$r['bill']->id]]) }}">Enter payment</a></td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-secondary py-4">Nothing outstanding: every submitted sub-bill is paid.</td></tr>
        @endforelse
        </tbody>
        @if($outstanding['rows']->isNotEmpty())
          <tfoot><tr class="bills-grand"><td colspan="5">Total · {{ $outstanding['rows']->count() }} sub-bills</td><td class="num">{{ $money($outstanding['total']['billed']) }}</td><td class="num">{{ $money($outstanding['total']['settled']) }}</td><td class="num">{{ $money($outstanding['total']['due']) }}</td><td></td></tr></tfoot>
        @endif
      </table></div></div>

    @else
      @php($tt = \App\Services\Payments\PaymentReports::lineTotals($tds))
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table">
        <thead><tr><th>Credit date</th><th>Sub-bill no.</th><th>Work order</th><th>UTR / cheque</th><th class="num">Before GST</th><th class="num">TDS</th><th class="num">CGST TDS</th><th class="num">SGST TDS</th><th class="num">IGST TDS</th><th class="text-center">Form 16A / 26AS</th><th class="text-center">GSTR-7A</th></tr></thead>
        <tbody>
        @forelse($tds as $l)
          <tr>
            <td><a class="text-decoration-none" href="{{ route('payments.show', $l->payment_receipt_id) }}">{{ $l->receipt->credit_date->format('d M Y') }}</a></td>
            <td class="mono">{{ $l->invoice?->invoice_no }}</td><td><span class="wo-code">{{ $l->receipt->agency?->code }}</span> <span class="mono small">{{ $l->receipt->contract?->contract_no }}</span></td>
            <td class="mono small">{{ $l->receipt->reference() }}</td>
            <td class="num">{{ $money($l->taxable) }}</td><td class="num">{{ $money($l->tds_it) }}</td><td class="num">{{ $money($l->tds_cgst) }}</td><td class="num">{{ $money($l->tds_sgst) }}</td><td class="num">{{ $money($l->tds_igst) }}</td>
            <td class="text-center">{!! (float) $l->tds_it > 0 ? ($l->form16a_seen ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-circle text-danger" title="Not yet seen"></i>') : '–' !!}</td>
            <td class="text-center">{!! ((float) $l->tds_cgst + (float) $l->tds_sgst + (float) $l->tds_igst) > 0 ? ($l->gstr7a_accepted ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-circle text-danger" title="Not yet accepted"></i>') : '–' !!}</td>
          </tr>
        @empty
          <tr><td colspan="11" class="text-center text-secondary py-4">No deductions on approved payments in this period.</td></tr>
        @endforelse
        </tbody>
        @if($tds->isNotEmpty())
          <tfoot><tr class="bills-grand"><td colspan="4">Total · {{ $tds->count() }}</td><td class="num">{{ $money($tt['taxable']) }}</td><td class="num">{{ $money($tt['tds_it']) }}</td><td class="num">{{ $money($tt['tds_cgst']) }}</td><td class="num">{{ $money($tt['tds_sgst']) }}</td><td class="num">{{ $money($tt['tds_igst']) }}</td><td colspan="2"></td></tr></tfoot>
        @endif
      </table></div></div>
      <p class="small text-secondary mt-2">Tick on each payment's page when the TDS shows in Form 26AS / Form 16A and the GST TDS is accepted on the GST portal (GSTR-7A), so nothing is missed in the returns.</p>
    @endif
  </div>
</x-layouts.app>
