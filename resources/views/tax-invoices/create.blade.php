<x-layouts.app title="Make tax invoice" :crumb="'Billing / <a href=\''.route('tax-invoices.index').'\'>Tax Invoices</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-file-earmark-plus"></i></div>
    <div class="me-auto"><h1>Make tax invoice</h1>
      <p>@if($contract)Agency: <b class="text-body">{{ $contract->agency->name }}</b> · Client: <b class="text-body">{{ $contract->client->name }}</b> · @endif Combine a work order's sub-bills for one month into one GST tax invoice (same serial as its sub-bills). The tax and total are worked out once on the combined sub total.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    
    </div>
  </div>


  <form method="get" action="{{ route('tax-invoices.create') }}" class="wo-bar">
    <div class="wo-pick" style="min-width:320px"><label class="text-secondary m-0" for="c">Work order:</label>
      <select id="c" name="contract" class="form-select" required><option value="">Choose work order</option>@foreach($contracts as $k)<option value="{{ $k->id }}" @selected($contract?->id === $k->id)>{{ $k->agency->numberPrefix() }} · {{ $k->contract_no }} ({{ $k->projects->pluck('code')->implode(', ') }}) · {{ $k->client->name }}</option>@endforeach</select></div>
    <div class="wo-pick"><span>Month:</span><x-month-picker :value="$month->format('Y-m')" required /></div>
    <button class="btn wo-btn-primary">Show sub-bills</button>
  </form>

  @if($contract)
  <form method="post" action="{{ route('tax-invoices.store') }}" class="z-card wo-card">
    @csrf
    <input type="hidden" name="gem_contract_id" value="{{ $contract->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <div class="z-card-h"><h2>Sub-bills of work order {{ $contract->contract_no }} ({{ $contract->agency->code }} · {{ $contract->client->name }}), {{ $month->format('F Y') }}</h2><span class="small text-secondary">Not yet in a tax invoice</span></div>
    <div class="table-responsive"><table class="table align-middle wo-table m-0">
      <thead><tr><th></th><th>Sub-bill no.</th><th>Project</th><th>Work order</th><th class="num">Before GST</th><th class="num">GST</th><th class="num">Total</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($pending as $b)
        <tr>
          <td><input class="form-check-input" type="checkbox" name="sub_bills[]" value="{{ $b->id }}" id="sb{{ $b->id }}" checked aria-label="Include {{ $b->invoice_no }}"></td>
          <td><label for="sb{{ $b->id }}" class="mono">{{ $b->invoice_no }}</label></td>
          <td>{{ $b->project->name }}</td><td class="mono small">{{ $b->contract->contract_no }}</td>
          <td class="num">{{ inr($b->taxable, true) }}</td><td class="num">{{ inr((float) $b->cgst + (float) $b->sgst + (float) $b->igst, true) }}</td>
          <td class="num">{{ inr($b->total, true) }}</td><td><x-status :value="$b->status" /></td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">No sub-bills waiting for this work order and month. Make them on the <a href="{{ route('invoices.index') }}">Sub-bills</a> page first.</td></tr>
      @endforelse
      </tbody>
      @if($pending->count() > 1)
        <tfoot><tr class="bills-grand"><td colspan="4">Total of the {{ $pending->count() }} sub-bills (all ticked)</td><td class="num">{{ inr($pending->sum(fn ($b) => (float) $b->taxable), true) }}</td>
          <td class="num">{{ inr($pending->sum(fn ($b) => (float) $b->cgst + (float) $b->sgst + (float) $b->igst), true) }}</td><td class="num">{{ inr($pending->sum(fn ($b) => (float) $b->total), true) }}</td><td></td></tr></tfoot>
      @endif
      </table></div>
    @if($pending->isNotEmpty())
    <div class="row g-3 p-3 border-top align-items-end">
      <x-field name="number" label="Tax invoice number" :value="$suggested" col="col-md-4" class="mono" hint="Given automatically (same serial as its sub-bills); change only if needed." />
      <x-field name="invoice_date" label="Invoice date" type="date" :value="now()->toDateString()" required col="col-md-3" />
      <div class="col-md-5 text-end"><button class="btn wo-btn-primary"><i class="bi bi-file-earmark-ruled me-1"></i>Make tax invoice</button></div>
    </div>
    @endif
  </form>
  @endif
  </div>
</x-layouts.app>
