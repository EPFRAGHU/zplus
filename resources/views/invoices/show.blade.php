@php($next = ['draft' => ['submitted', 'Mark submitted to client'], 'approved' => ['submitted', 'Mark submitted to client'], 'submitted' => ['paid', 'Mark paid']][$invoice->status] ?? null)
<x-layouts.app :title="'Sub-bill '.$invoice->invoice_no" :crumb="'Billing / <a href=\''.route('invoices.index').'\'>Bills</a>'">
  <x-slot:actions>
    <a class="btn btn-outline-secondary" href="{{ route('invoices.covering-letter', $invoice) }}" target="_blank"><i class="bi bi-envelope-paper me-1"></i>Covering letter</a>
    <a class="btn btn-outline-secondary" href="{{ route('invoices.pdf', $invoice) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Sub-bill PDF</a>
    @can('show-delete')@if(! in_array($invoice->status, ['draft', 'paid'], true))<x-delete-button :action="route('invoices.destroy', $invoice)" :small="false" label="Delete bill" :blocked="$invoice->taxInvoice?->status === 'paid' ? 'Its tax invoice is paid.' : null" :confirm="'Delete '.$invoice->status.' bill '.$invoice->invoice_no.'? It is taken out of its tax invoice. The attendance stays locked.'" />@endif @endcan
    @if($invoice->sheet->certificate_path)<a class="btn btn-outline-secondary" href="{{ route('attendance.scan', $invoice->sheet) }}" target="_blank"><i class="bi bi-file-earmark-check me-1"></i>PI certificate</a>@endif
  </x-slot:actions>
  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Agency: <b>{{ $invoice->contract->agency->name }}</b></span>
    <span>Client: <b>{{ $invoice->contract->client->name }}</b></span>
    <span>Work order: <span class="mono">{{ $invoice->contract->contract_no }}</span></span>
    <span>PI: <b>{{ $invoice->project->pi_name ?? '—' }}</b></span>
    <span>Tax invoice: @if($invoice->taxInvoice)<a class="mono" href="{{ route('tax-invoices.show', $invoice->taxInvoice) }}">{{ $invoice->taxInvoice->number }}</a>@else<span class="text-secondary">not yet</span> @can('billing')<a href="{{ route('tax-invoices.create', ['client' => $invoice->contract->client_id, 'month' => $invoice->month->format('Y-m')]) }}">Make tax invoice</a>@endcan @endif</span>
  </div>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="z-card">
        <div class="z-card-h"><h2>{{ $invoice->project->name }}, {{ $invoice->month->format('F Y') }}</h2><x-status :value="$invoice->status" /><span class="small text-secondary ms-auto">Dated {{ $invoice->invoice_date->format('d/m/Y') }}</span></div>
        <div class="table-responsive"><table class="table">
          <thead><tr><th>Sl.</th><th>Name</th><th>Post</th><th class="num">Rate</th>@if($invoice->billing_type === 'monthly')<th class="num">Days in month</th>@endif<th class="num">Paid days</th><th class="num">Amount</th></tr></thead>
          <tbody>@foreach($invoice->lines as $l)
            @php($exit = $l->employee?->exitIn($invoice->month))
            <tr @class(['table-danger' => $exit])><td>{{ $loop->iteration }}</td><td>{{ $l->employee_name }}@if($exit)<div><span class="badge text-bg-danger">Resigned · last day {{ $exit->format('d.m.Y') }}</span></div>@endif</td><td>{{ $l->designation }}</td><td class="num">{{ inr($l->rate) }}{{ $l->rate_type === 'daily' ? '/day' : '' }}</td>
              @if($invoice->billing_type === 'monthly')<td class="num">{{ $l->days_in_month }}</td>@endif<td class="num">{{ rtrim(rtrim((string) $l->paid_days, '0'), '.') }}</td><td class="num">{{ inr($l->amount, true) }}</td></tr>
          @endforeach</tbody>
        </table></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="z-card p-3">
        <table class="table table-sm mb-2">
          <tr><td>Wages</td><td class="num">{{ inr($invoice->wages, true) }}</td></tr>
          @if((float) $invoice->epf > 0)<tr><td>Employer EPF</td><td class="num">{{ inr($invoice->epf, true) }}</td></tr>@endif
          @if((float) $invoice->esic > 0)<tr><td>Employer ESIC</td><td class="num">{{ inr($invoice->esic, true) }}</td></tr>@endif
          <tr><td>{{ $invoice->serviceChargeLabel() }}</td><td class="num">{{ inr($invoice->service_charge, true) }}</td></tr>
          <tr class="fw-semibold"><td>Total before GST</td><td class="num">{{ inr($invoice->taxable, true) }}</td></tr>
          @if((float) $invoice->igst > 0)<tr><td>IGST @ {{ (float) $invoice->gst_percent }}%</td><td class="num">{{ inr($invoice->igst, true) }}</td></tr>
          @else<tr><td>CGST @ {{ (float) $invoice->gst_percent / 2 }}%</td><td class="num">{{ inr($invoice->cgst, true) }}</td></tr><tr><td>SGST @ {{ (float) $invoice->gst_percent / 2 }}%</td><td class="num">{{ inr($invoice->sgst, true) }}</td></tr>@endif
          <tr class="fs-5 fw-bold"><td>Bill amount</td><td class="num">{{ inr($invoice->rounded_total, true) }}</td></tr>
        </table>
        <div class="small text-secondary mb-3">{{ $invoice->amountInWords() }}</div>
        @if($next)
          <form method="post" action="{{ route('invoices.status', $invoice) }}" class="d-grid gap-2">@csrf
            <input type="hidden" name="status" value="{{ $next[0] }}">
            @if($next[0] === 'paid')<label class="small text-secondary" for="paid_on">Payment received on</label><input id="paid_on" type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" required>@endif
            <button class="btn btn-primary">{{ $next[1] }}</button>
          </form>
        @endif
        @if($invoice->status !== 'paid' && (! $invoice->taxInvoice || $invoice->taxInvoice->status !== 'paid'))
          @can('billing')<form method="post" action="{{ route('invoices.recalculate', $invoice) }}" class="d-grid mt-2"
            onsubmit="return confirm('Work this bill out again with the current rules (service charge incl. GST ÷ 1.18, no rounding, new bill number)? Its status stays the same and the change is recorded.{{ $invoice->status === 'submitted' ? ' This bill was already submitted: send the client the new PDF in place of the old one.' : '' }}')">@csrf
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Recalculate with current rules</button></form>
          @unless($invoice->service_charge_includes_gst)<div class="small text-warning-emphasis mt-1">Made before 16 Nov 2026 with the old service charge (3.85% on the amount).</div>@endunless
          @endcan
        @endif
        @if($invoice->status === 'draft')
          @can('billing')<form method="post" action="{{ route('invoices.destroy', $invoice) }}" class="d-grid mt-2" onsubmit="return confirm('Delete this draft bill? Its number will not be reused.')">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">Delete draft</button></form>@endcan
        @endif
      </div>
    </div>
  </div>
  @include('partials.approval-box', ['type' => 'sub_bill', 'subject' => $invoice, 'sendAction' => route('approvals.send.invoice', $invoice), 'canSend' => auth()->user()->can('billing')])
  @can('billing')
  @include('partials.signed-copy', ['signed' => \App\Models\SignedDocument::latestFor('sub_bill', invoiceId: $invoice->id), 'action' => route('invoices.signed', $invoice), 'changedAt' => $invoice->updated_at])
  @php($agencyName = $invoice->contract->agency->name)
  @include('partials.email-client', ['action' => route('invoices.email', $invoice), 'agency' => $invoice->contract->agency,
    'to' => $invoice->contract->client->email, 'preview' => app(\App\Services\Mail\AgencyMailer::class)->previewForInvoice($invoice),
    'history' => \App\Models\SentEmail::historyFor(invoiceId: $invoice->id),
    'subject' => 'Bill No. '.$invoice->invoice_no.' for '.$invoice->month->format('F Y').' – '.($invoice->project->title ?: $invoice->project->name),
    'body' => "Dear Sir / Madam,\n\nPlease find attached our bill no. {$invoice->invoice_no} dated {$invoice->invoice_date->format('d.m.Y')} for the manpower supplied under {$invoice->project->name} for the month of {$invoice->month->format('F Y')} (".($invoice->contract->contract_type === 'offline' ? 'work order' : 'GeM contract')." no. {$invoice->contract->contract_no}), with its covering letter.\n\nKindly arrange the payment at the earliest.\n\nThanking you,\nYours faithfully,\nFor {$agencyName}"])
  @endcan
</x-layouts.app>
