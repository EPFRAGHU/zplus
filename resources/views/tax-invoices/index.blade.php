<x-layouts.app title="Tax Invoices" crumb="Billing / <span class='text-danger'>Tax Invoices</span>" :quiet-title="true">
  {{-- Work Orders style (owner, 26 Nov 2026): heading, figure boxes, status tabs and filter bar; the table below is unchanged. --}}
  @php
    $tabUrl = fn (?string $st) => route('tax-invoices.index', array_filter(array_merge(request()->except(['status']), ['status' => $st])));
    $sumOf = fn (string $f) => $taxInvoices->sum(fn ($x) => (float) $x->{$f});
  @endphp
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-file-earmark-ruled"></i></div>
    <div class="me-auto"><h1>Tax Invoices</h1>
      <p>A tax invoice combines a work order's sub-bills for one month (one sub-bill per project) into a single GST invoice.</p></div>
    @can('billing')<div class="wo-actions"><a class="btn wo-btn-primary" href="{{ route('tax-invoices.create') }}"><i class="bi bi-plus-lg me-1"></i>Make tax invoice</a></div>@endcan
  </div>

  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-file-earmark-ruled"></i><div><div class="l">Tax invoices listed</div><div class="v">{{ $taxInvoices->count() }} <small>/ {{ $taxInvoices->sum('sub_bills_count') }} sub-bills</small></div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-cash-stack"></i><div><div class="l">Before GST</div><div class="v" style="font-size:1.2rem">{{ inr($sumOf('taxable'), true) }}</div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-percent"></i><div><div class="l">GST</div><div class="v" style="font-size:1.2rem">{{ inr($sumOf('cgst') + $sumOf('sgst') + $sumOf('igst'), true) }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-currency-rupee"></i><div><div class="l">Invoice amount</div><div class="v" style="font-size:1.2rem">{{ inr($sumOf('rounded_total'), true) }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-hourglass-split"></i><div><div class="l">Not yet paid</div><div class="v" style="font-size:1.2rem">{{ inr($taxInvoices->where('status', '!=', 'paid')->sum(fn ($x) => (float) $x->rounded_total), true) }}</div></div></div>
  </div>

  <form class="wo-bar" method="get" action="{{ route('tax-invoices.index') }}">
    <nav class="wo-tabs" aria-label="Tax invoice status">
      <a href="{{ $tabUrl(null) }}" @class(['on' => ! request('status')])>All ({{ $statusCounts->sum() }})</a>
      @foreach(\App\Models\TaxInvoice::STATUSES as $k => $v)<a href="{{ $tabUrl($k) }}" @class(['on' => request('status') === $k])>{{ $v }} ({{ $statusCounts[$k] ?? 0 }})</a>@endforeach
    </nav>
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="wo-pick"><span>Month:</span><x-month-picker :value="request('month')" clearable /></div>
    <label class="wo-pick" for="f-agency"><span>Agency:</span>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
    <label class="wo-pick" for="f-client"><span>Client:</span>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->label() }}</option>@endforeach</select></label>
    <button class="btn wo-btn-primary">Show</button>
    @if(request()->hasAny(['month', 'agency', 'client', 'status']))<a class="btn btn-link px-1" href="{{ route('tax-invoices.index') }}">Clear</a>@endif
  </form>
  {{-- GST split in columns and a total of everything listed at the bottom (owner, 22 Nov 2026). --}}
  @php($igst = $taxInvoices->sum(fn ($x) => (float) $x->igst) > 0)
  @php($cols = $igst ? 10 : 9)
  <div class="wo-card"><div class="table-responsive"><table class="table table-hover align-middle wo-table m-0 bills-table">
    <thead><tr><th>Tax invoice no.</th><th class="text-center">Sub-bills</th><th>Month</th><th>Date</th><th class="num">Before GST</th><th class="num">CGST</th><th class="num">SGST</th>@if($igst)<th class="num">IGST</th>@endif<th class="num">Invoice amount</th><th>Status</th></tr></thead>
    <tbody>
    @php($agencyBlock = false) @php($clientBlock = false)
    @forelse($taxInvoices as $t)
      @if($agencyBlock !== $t->agency_id)
        @php($agencyBlock = $t->agency_id) @php($clientBlock = false)
        @php($inAgency = $taxInvoices->where('agency_id', $t->agency_id))
        <tr class="table-secondary"><td colspan="{{ $cols }}" class="py-2"><b class="fs-6">{{ $t->agency->name }}</b> <span class="badge text-bg-dark">{{ $t->agency->code }}</span>
          <span class="small text-secondary ms-2">{{ $inAgency->count() }} {{ \Illuminate\Support\Str::plural('tax invoice', $inAgency->count()) }} · {{ inr($inAgency->sum(fn ($x) => (float) $x->rounded_total)) }}</span></td></tr>
      @endif
      @if($clientBlock !== $t->client_id)
        @php($clientBlock = $t->client_id)
        @php($inClient = $taxInvoices->where('client_id', $t->client_id))
        <x-client-block :client="$t->client" :colspan="$cols" :meta="$inClient->count().' '.\Illuminate\Support\Str::plural('tax invoice', $inClient->count()).' · '.inr($inClient->sum(fn ($x) => (float) $x->rounded_total))">
          @can('billing')<a class="btn btn-sm btn-outline-primary" href="{{ route('tax-invoices.create', array_filter(['contract' => $t->gem_contract_id])) }}"><i class="bi bi-plus-lg"></i> Tax invoice for this client</a>@endcan
        </x-client-block>
      @endif
      <tr>
        <td class="text-nowrap"><a class="mono" href="{{ route('tax-invoices.show', $t) }}">{{ $t->number }}</a></td>
        {{-- How many sub-bills it combines; click to see and open them (owner, 22 Nov 2026). --}}
        <td class="text-center"><x-names-pop class="n-full" :title="'Sub-bills in '.$t->number" empty="No sub-bills"
          :items="$t->subBills->map(fn ($b) => ['text' => $b->invoice_no, 'url' => route('invoices.show', $b), 'sub' => inr($b->rounded_total)])">{{ $t->sub_bills_count }}</x-names-pop></td>
        <td>{{ $t->month->format('M Y') }}</td><td>{{ $t->invoice_date->format('d/m/Y') }}</td>
        <td class="num">{{ inr($t->taxable, true) }}</td>
        <td class="num">{{ inr($t->cgst, true) }}</td><td class="num">{{ inr($t->sgst, true) }}</td>@if($igst)<td class="num">{{ inr($t->igst, true) }}</td>@endif
        <td class="num fw-semibold">{{ inr($t->rounded_total, true) }}</td>
        <td><x-status :value="$t->status" /></td>
      </tr>
    @empty
      <tr><td colspan="{{ $cols }}" class="text-center text-secondary py-4">No tax invoices yet. Make the month's sub-bills first, then click <b>Make tax invoice</b>.</td></tr>
    @endforelse
    </tbody>
    @if($taxInvoices->isNotEmpty())
    <tfoot><tr class="bills-grand"><td colspan="2">Total of all {{ $taxInvoices->count() }} {{ \Illuminate\Support\Str::plural('tax invoice', $taxInvoices->count()) }} listed{{ request('month') ? ' for '.\Carbon\CarbonImmutable::parse(request('month').'-01')->format('F Y') : '' }}</td>
      <td class="text-center">{{ $taxInvoices->sum('sub_bills_count') }} sub-bills</td><td></td>
      <td class="num">{{ inr($taxInvoices->sum(fn ($x) => (float) $x->taxable), true) }}</td>
      <td class="num">{{ inr($taxInvoices->sum(fn ($x) => (float) $x->cgst), true) }}</td><td class="num">{{ inr($taxInvoices->sum(fn ($x) => (float) $x->sgst), true) }}</td>
      @if($igst)<td class="num">{{ inr($taxInvoices->sum(fn ($x) => (float) $x->igst), true) }}</td>@endif
      <td class="num">{{ inr($taxInvoices->sum(fn ($x) => (float) $x->rounded_total), true) }}</td><td></td></tr></tfoot>
    @endif
    </table></div></div>
  </div>
</x-layouts.app>
