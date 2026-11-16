<x-layouts.app title="Make tax invoice" :crumb="'Billing / <a href=\''.route('tax-invoices.index').'\'>Tax Invoices</a>'">
  <form method="get" action="{{ route('tax-invoices.create') }}" class="z-card p-3 mb-3 d-flex gap-2 flex-wrap align-items-end">
    <div style="min-width:320px"><label class="form-label small text-secondary mb-1" for="c">Work order</label>
      <select id="c" name="contract" class="form-select" required><option value="">Choose work order</option>@foreach($contracts as $k)<option value="{{ $k->id }}" @selected($contract?->id === $k->id)>{{ $k->agency->numberPrefix() }} · {{ $k->contract_no }} ({{ $k->projects->pluck('code')->implode(', ') }}) · {{ $k->client->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="m">Month</label><x-month-picker :value="$month->format('Y-m')" required /></div>
    <button class="btn btn-outline-secondary">Show sub-bills</button>
  </form>

  @if($contract)
  <form method="post" action="{{ route('tax-invoices.store') }}" class="z-card">
    @csrf
    <input type="hidden" name="gem_contract_id" value="{{ $contract->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <div class="z-card-h"><h2>Sub-bills of work order {{ $contract->contract_no }} ({{ $contract->agency->code }} · {{ $contract->client->name }}), {{ $month->format('F Y') }}</h2><span class="small text-secondary">Not yet in a tax invoice</span></div>
    <div class="table-responsive"><table class="table">
      <thead><tr><th></th><th>Sub-bill no.</th><th>Project</th><th>Work order</th><th class="num">Before GST</th><th class="num">GST</th><th class="num">Total</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($pending as $b)
        <tr>
          <td><input class="form-check-input" type="checkbox" name="sub_bills[]" value="{{ $b->id }}" id="sb{{ $b->id }}" checked aria-label="Include {{ $b->invoice_no }}"></td>
          <td><label for="sb{{ $b->id }}" class="mono">{{ $b->invoice_no }}</label></td>
          <td>{{ $b->project->name }}</td><td class="mono small">{{ $b->contract->bid_no }}</td>
          <td class="num">{{ inr($b->taxable, true) }}</td><td class="num">{{ inr((float) $b->cgst + (float) $b->sgst + (float) $b->igst, true) }}</td>
          <td class="num">{{ inr($b->total, true) }}</td><td><x-status :value="$b->status" /></td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">No sub-bills waiting for this work order and month. Make them on the <a href="{{ route('invoices.index') }}">Sub-bills</a> page first.</td></tr>
      @endforelse
      </tbody></table></div>
    @if($pending->isNotEmpty())
    <div class="row g-3 p-3 border-top align-items-end">
      <x-field name="number" label="Tax invoice number" :value="$suggested" col="col-md-4" class="mono" hint="Given automatically (same serial as its sub-bills); change only if needed." />
      <x-field name="invoice_date" label="Invoice date" type="date" :value="now()->toDateString()" required col="col-md-3" />
      <div class="col-md-5 text-end"><button class="btn btn-primary"><i class="bi bi-file-earmark-ruled me-1"></i>Make tax invoice</button></div>
    </div>
    @endif
  </form>
  @endif
</x-layouts.app>
