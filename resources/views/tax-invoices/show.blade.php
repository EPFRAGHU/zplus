@php($next = ['draft' => ['submitted', 'Mark submitted to client'], 'submitted' => ['paid', 'Mark paid']][$tax->status] ?? null)
<x-layouts.app :title="'Tax invoice '.$tax->number" :crumb="'Billing / <a href=\''.route('tax-invoices.index').'\'>Tax Invoices</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-file-earmark-ruled"></i></div>
    <div class="me-auto"><h1>Tax invoice <span class="mono">{{ $tax->number }}</span> <x-status :value="$tax->status" /></h1>
      <p>Agency: <b class="text-body">{{ $tax->agency->name }}</b> · Client: <b class="text-body">{{ $tax->client->name }}</b>
        @if($tax->contract)· Work order: <a class="mono" href="{{ route('contracts.show', $tax->contract) }}">{{ $tax->contract->contract_no }}</a> ({{ $tax->contract->typeLabel() }})@endif
        · Month: <b class="text-body">{{ $tax->month->format('F Y') }}</b> · Date: <b class="text-body">{{ $tax->invoice_date->format('d/m/Y') }}</b></p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    <a class="btn btn-outline-secondary" href="{{ route('tax-invoices.pdf', $tax) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Tax invoice PDF</a>
    @can('show-delete-bills')@if($tax->status === 'submitted')<x-delete-button :action="route('tax-invoices.destroy', $tax)" :small="false" label="Delete tax invoice" :confirm="'Delete submitted tax invoice '.$tax->number.'? Its sub-bills are released and can be invoiced again.'" />@endif @endcan
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-receipt"></i><div><div class="l">Sub-bills</div><div class="v">{{ $tax->subBills->count() }} <small>/ {{ $tax->subBills->sum(fn ($b) => $b->lines()->count()) }} staff</small></div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-cash-stack"></i><div><div class="l">Before GST</div><div class="v" style="font-size:1.2rem">{{ inr($tax->taxable, true) }}</div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-percent"></i><div><div class="l">GST</div><div class="v" style="font-size:1.2rem">{{ inr((float) $tax->cgst + (float) $tax->sgst + (float) $tax->igst, true) }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-currency-rupee"></i><div><div class="l">Invoice amount</div><div class="v" style="font-size:1.2rem">{{ inr($tax->rounded_total, true) }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-hourglass-split"></i><div><div class="l">Still due</div><div class="v" style="font-size:1.2rem">{{ $tax->status === 'draft' ? '—' : inr($tax->subBills->sum(fn ($b) => (float) \App\Support\Exact::money(app(\App\Services\Payments\Receipts::class)->outstanding($b))), true) }}</div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8"><div class="z-card">
      <div class="z-card-h"><h2>Sub-bills in this tax invoice</h2></div>
      <div class="table-responsive"><table class="table align-middle wo-table m-0">
        <thead><tr><th>Sub-bill no.</th><th>Project</th><th class="text-center">Staff</th><th>SAC</th><th class="num">Before GST</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">Total</th></tr></thead>
        <tbody>@foreach($tax->subBills as $b)
          <tr><td><a class="mono" href="{{ route('invoices.show', $b) }}">{{ $b->invoice_no }}</a></td><td>{{ $b->project->name }}</td><td class="text-center">{{ $b->lines()->count() }}</td><td class="mono">{{ $b->contract->sac_code }}</td>
            <td class="num">{{ inr($b->taxable, true) }}</td><td class="num">{{ inr($b->cgst, true) }}</td><td class="num">{{ inr($b->sgst, true) }}</td><td class="num">{{ inr($b->total, true) }}</td></tr>
        @endforeach</tbody>
        {{-- Total of the sub-bills (owner, 26 Nov 2026); the tax invoice itself works its figures out once on the combined sub total. --}}
        <tfoot><tr class="bills-grand"><td colspan="2">Sub-bills total · {{ $tax->subBills->count() }} {{ \Illuminate\Support\Str::plural('sub-bill', $tax->subBills->count()) }}</td><td class="text-center">{{ $tax->subBills->sum(fn ($b) => $b->lines()->count()) }}</td><td></td>
          <td class="num">{{ inr($tax->subBills->sum(fn ($b) => (float) $b->taxable), true) }}</td><td class="num">{{ inr($tax->subBills->sum(fn ($b) => (float) $b->cgst), true) }}</td>
          <td class="num">{{ inr($tax->subBills->sum(fn ($b) => (float) $b->sgst), true) }}</td><td class="num">{{ inr($tax->subBills->sum(fn ($b) => (float) $b->total), true) }}</td></tr></tfoot>
      </table></div>
    </div>
    @include('payments._box', ['bills' => $tax->subBills])
    </div>
    <div class="col-lg-4"><div class="z-card p-3">
      <table class="table table-sm mb-2">
        <tr><td>Wages</td><td class="num">{{ inr($t->wages, true) }}</td></tr>
        @if((float) $t->epf > 0)<tr><td>Add: Employer EPF</td><td class="num">{{ inr($t->epf, true) }}</td></tr>@endif
        @if((float) $t->esic > 0)<tr><td>Add: Employer ESIC</td><td class="num">{{ inr($t->esic, true) }}</td></tr>@endif
        <tr><td>Sub total</td><td class="num">{{ inr($t->subtotal, true) }}</td></tr>
        <tr><td>Add: Service charge</td><td class="num">{{ inr($t->serviceCharge, true) }}</td></tr>
        <tr class="fw-semibold"><td>Total before tax</td><td class="num">{{ inr($tax->taxable, true) }}</td></tr>
        @if((float) $tax->igst > 0)<tr><td>Add: IGST</td><td class="num">{{ inr($tax->igst, true) }}</td></tr>
        @else<tr><td>Add: CGST</td><td class="num">{{ inr($tax->cgst, true) }}</td></tr><tr><td>Add: SGST</td><td class="num">{{ inr($tax->sgst, true) }}</td></tr>@endif
        <tr><td>Total after tax</td><td class="num">{{ inr($tax->total, true) }}</td></tr>
        <tr class="fs-5 fw-bold"><td>Rounded</td><td class="num">{{ inr($tax->rounded_total, true) }}</td></tr>
      </table>
      <div class="small text-secondary mb-3">{{ $tax->amountInWords() }}</div>
      @php($bal = \App\Services\Billing\TaxInvoiceBuilder::contractBalance($tax))
      @if($bal)
        <table class="table table-sm mb-3 small" id="contract-balance">
          <tr class="table-light"><th colspan="2">Contract value position</th></tr>
          <tr><td>Total tender value (incl. GST)</td><td class="num">{{ inr($bal['value'], true) }}</td></tr>
          <tr><td>Less: billed before ({{ $bal['count'] }})</td><td class="num">{{ inr($bal['previous'], true) }}</td></tr>
          <tr class="fw-semibold"><td>Opening balance</td><td class="num">{{ inr($bal['opening'], true) }}</td></tr>
          <tr><td>Less: this tax invoice</td><td class="num">{{ inr($bal['current'], true) }}</td></tr>
          <tr class="fw-bold"><td>Net balance (carried forward)</td><td class="num">{{ inr($bal['closing'], true) }}</td></tr>
        </table>
      @else
        <div class="small text-warning-emphasis mb-3"><i class="bi bi-info-circle me-1"></i>Enter the total contract value on the work order to show the contract balance here and on the PDF.</div>
      @endif
      <div class="small text-secondary mb-3">Worked out on the combined sub-total of its sub-bills, with no rounding until the final total (as on your Excel tax invoice), so it can differ from the sum of the sub-bills by a few paise.</div>
      @if($next && $next[0] === 'paid' && ! \App\Services\Approvals\Approvals::canApprove(auth()->user()))
        <div class="alert alert-light border small mb-2"><i class="bi bi-lock me-1"></i>When the client pays, enter it under <a href="#payments">Payments received</a>; the bill turns Paid when a Super Admin approves the payment. Only a Super Admin can mark it paid by hand.</div>
      @elseif($next)
        <form method="post" action="{{ route('tax-invoices.status', $tax) }}" class="d-grid gap-2">@csrf
          <input type="hidden" name="status" value="{{ $next[0] }}">
          @if($next[0] === 'paid')<label class="small text-secondary" for="paid_on">Payment received on</label><input id="paid_on" type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" required><div class="small text-secondary mt-1">Better: <a href="#payments">Enter payment</a> with the UTR and the client's TDS; the bill turns Paid when it is approved. Use this button only for bills paid before payments were entered in the app.</div>@endif
          <button class="btn btn-primary">{{ $next[1] }}</button>
        </form>
      @endif
      @if($tax->status !== 'paid' && (! $tax->contract || ! \App\Services\Billing\TaxInvoiceBuilder::isNewStyle($tax->number, $tax->contract)))
        @can('billing')<form method="post" action="{{ route('tax-invoices.recalculate', $tax) }}" class="d-grid mt-2"
          onsubmit="return confirm('Give this tax invoice the work order number (SW/001/26-27 style) and work its figures out again with the current rules?{{ $tax->status === 'submitted' ? ' It was already submitted: send the client the new PDF.' : '' }}')">@csrf
          <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Renumber and recalculate</button></form>
        <div class="small text-warning-emphasis mt-1">Made before 16 Nov 2026: old-style number.</div>@endcan
      @endif
      @if($tax->status === 'draft')
        @can('billing')<form method="post" action="{{ route('tax-invoices.destroy', $tax) }}" class="d-grid mt-2" onsubmit="return confirm('Delete this draft tax invoice? Its sub-bills can be invoiced again.')">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">Delete draft</button></form>@endcan
      @endif
      @unless($tax->agency->bank_account_no)<div class="alert alert-warning small mt-3 mb-0">Bank details are missing on the PDF. Add them under <a href="{{ route('agencies.edit', $tax->agency) }}">Agencies → Edit</a>.</div>@endunless
    </div></div>
  </div>
  @include('partials.approval-box', ['type' => 'tax_invoice', 'subject' => $tax, 'sendAction' => route('approvals.send.tax-invoice', $tax), 'canSend' => auth()->user()->can('billing')])
  @can('billing')
  @include('partials.signed-copy', ['signed' => \App\Models\SignedDocument::latestFor('tax_invoice', taxInvoiceId: $tax->id), 'action' => route('tax-invoices.signed', $tax), 'changedAt' => $tax->updated_at])
  @include('partials.email-client', ['action' => route('tax-invoices.email', $tax), 'agency' => $tax->agency,
    'to' => $tax->client->email, 'preview' => app(\App\Services\Mail\AgencyMailer::class)->previewForTaxInvoice($tax),
    'history' => \App\Models\SentEmail::historyFor(taxInvoiceId: $tax->id),
    'subject' => 'Tax Invoice No. '.$tax->number.' for '.$tax->month->format('F Y').' – '.$tax->agency->name,
    'body' => "Dear Sir / Madam,\n\nPlease find attached our tax invoice no. {$tax->number} dated {$tax->invoice_date->format('d.m.Y')} for the manpower supplied in the month of {$tax->month->format('F Y')}".($tax->contract ? ' ('.($tax->contract->contract_type === 'offline' ? 'work order' : 'GeM contract')." no. {$tax->contract->contract_no})" : '').", together with the project-wise sub-bills.\n\nKindly arrange the payment at the earliest.\n\nThanking you,\nYours faithfully,\nFor {$tax->agency->name}"])
  @endcan
  </div>
</x-layouts.app>
