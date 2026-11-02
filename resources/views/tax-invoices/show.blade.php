@php($next = ['draft' => ['submitted', 'Mark submitted to client'], 'submitted' => ['paid', 'Mark paid']][$tax->status] ?? null)
<x-layouts.app :title="'Tax invoice '.$tax->number" :crumb="'Billing / <a href=\''.route('tax-invoices.index').'\'>Tax Invoices</a>'">
  <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('tax-invoices.pdf', $tax) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Tax invoice PDF</a></x-slot:actions>
  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Agency: <b>{{ $tax->agency->name }}</b></span><span>Client: <b>{{ $tax->client->name }}</b></span>
    <span>Month: <b>{{ $tax->month->format('F Y') }}</b></span><span>Date: <b>{{ $tax->invoice_date->format('d/m/Y') }}</b></span>
    <span>Status: <x-status :value="$tax->status" /></span>
  </div>
  <div class="row g-3">
    <div class="col-lg-8"><div class="z-card">
      <div class="z-card-h"><h2>Sub-bills in this tax invoice</h2></div>
      <div class="table-responsive"><table class="table">
        <thead><tr><th>Sub-bill no.</th><th>Project</th><th>SAC</th><th class="num">Before GST</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">Total</th></tr></thead>
        <tbody>@foreach($tax->subBills as $b)
          <tr><td><a class="mono" href="{{ route('invoices.show', $b) }}">{{ $b->invoice_no }}</a></td><td>{{ $b->project->name }}</td><td class="mono">{{ $b->contract->sac_code }}</td>
            <td class="num">{{ inr($b->taxable, true) }}</td><td class="num">{{ inr($b->cgst, true) }}</td><td class="num">{{ inr($b->sgst, true) }}</td><td class="num">{{ inr($b->total, true) }}</td></tr>
        @endforeach</tbody>
      </table></div>
    </div></div>
    <div class="col-lg-4"><div class="z-card p-3">
      <table class="table table-sm mb-2">
        @php($sb = $tax->subBills)
        <tr><td>Wages</td><td class="num">{{ inr($sb->sum('wages'), true) }}</td></tr>
        @if($sb->sum('epf') > 0)<tr><td>Add: Employer EPF</td><td class="num">{{ inr($sb->sum('epf'), true) }}</td></tr>@endif
        @if($sb->sum('esic') > 0)<tr><td>Add: Employer ESIC</td><td class="num">{{ inr($sb->sum('esic'), true) }}</td></tr>@endif
        <tr><td>Add: Service charge</td><td class="num">{{ inr($sb->sum('service_charge'), true) }}</td></tr>
        <tr class="fw-semibold"><td>Total before tax</td><td class="num">{{ inr($tax->taxable, true) }}</td></tr>
        @if((float) $tax->igst > 0)<tr><td>Add: IGST</td><td class="num">{{ inr($tax->igst, true) }}</td></tr>
        @else<tr><td>Add: CGST</td><td class="num">{{ inr($tax->cgst, true) }}</td></tr><tr><td>Add: SGST</td><td class="num">{{ inr($tax->sgst, true) }}</td></tr>@endif
        <tr><td>Total after tax</td><td class="num">{{ inr($tax->total, true) }}</td></tr>
        <tr class="fs-5 fw-bold"><td>Rounded</td><td class="num">{{ inr($tax->rounded_total, true) }}</td></tr>
      </table>
      <div class="small text-secondary mb-3">{{ $tax->amountInWords() }}</div>
      @if($next)
        <form method="post" action="{{ route('tax-invoices.status', $tax) }}" class="d-grid gap-2">@csrf
          <input type="hidden" name="status" value="{{ $next[0] }}">
          @if($next[0] === 'paid')<label class="small text-secondary" for="paid_on">Payment received on</label><input id="paid_on" type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" required>@endif
          <button class="btn btn-primary">{{ $next[1] }}</button>
        </form>
      @endif
      @if($tax->status === 'draft')
        @can('billing')<form method="post" action="{{ route('tax-invoices.destroy', $tax) }}" class="d-grid mt-2" onsubmit="return confirm('Delete this draft tax invoice? Its sub-bills can be invoiced again.')">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">Delete draft</button></form>@endcan
      @endif
      @unless($tax->agency->bank_account_no)<div class="alert alert-warning small mt-3 mb-0">Bank details are missing on the PDF. Add them under <a href="{{ route('agencies.edit', $tax->agency) }}">Agencies → Edit</a>.</div>@endunless
    </div></div>
  </div>
</x-layouts.app>
