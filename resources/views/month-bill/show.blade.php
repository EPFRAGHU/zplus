<x-layouts.app title="Month bill" crumb="Billing / <span class='text-danger'>Month bill</span>" :quiet-title="true">
  @php
    $money = fn ($v) => inr((float) $v, true);
    $igst = $total && (float) $total['igst'] > 0;
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-journal-text"></i></div>
      <div class="me-auto"><h1>Month bill – {{ $month->format('F Y') }}</h1>
        <p>{{ $agency?->name ?? 'Choose an agency' }}@if($rows->isNotEmpty()) · client: {{ $rows->map(fn ($r) => $r['contract']->client?->name)->filter()->unique()->implode('; ') }}@endif · all tax invoices of the month in one sheet, one row per work order. Internal summary, not a GST invoice.</p></div>
      @if($agency && $rows->isNotEmpty())
        <div class="wo-actions"><a class="btn wo-btn-primary" href="{{ route('month-bill.pdf', ['agency' => $agency->id, 'month' => $month->format('Y-m')]) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a></div>
      @endif
    </div>

    <form class="wo-bar" method="get" action="{{ route('month-bill.show') }}">
      <div class="wo-pick"><span>Month:</span><x-month-picker :value="$month->format('Y-m')" /></div>
      <label class="wo-pick"><span>Agency:</span>
        <select name="agency" class="form-select"><option value="">Choose…</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agency?->id === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <button class="btn wo-btn-primary">Show</button>
      <a class="btn btn-link px-1" href="{{ route('invoices.index', array_filter(['month' => $month->format('Y-m'), 'agency' => $agency?->id])) }}">Back to Sub-bills</a>
    </form>

    @if(! $agency)
      <div class="wo-empty">Choose the agency (SWACHHA or Z Plus): each agency has its own month bill.</div>
    @elseif($rows->isEmpty())
      <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No sub-bills for {{ $agency->name }} in {{ $month->format('F Y') }}.</div>
    @else
      <div class="wo-kpis">
        <div class="wo-kpi k-blue"><i class="bi bi-file-earmark-text"></i><div><div class="l">Work orders</div><div class="v">{{ $total['work_orders'] }}</div></div></div>
        <div class="wo-kpi k-violet"><i class="bi bi-receipt"></i><div><div class="l">Sub-bills / tax invoices</div><div class="v">{{ $total['sub_bills'] }} <small>/ {{ $total['tax_invoices'] }}</small></div></div></div>
        <div class="wo-kpi k-green"><i class="bi bi-people"></i><div><div class="l">Staff billed</div><div class="v">{{ $total['staff'] }}</div></div></div>
        <div class="wo-kpi k-amber"><i class="bi bi-percent"></i><div><div class="l">GST</div><div class="v" style="font-size:1.2rem">{{ $money((float) $total['cgst'] + (float) $total['sgst'] + (float) $total['igst']) }}</div></div></div>
        <div class="wo-kpi k-red"><i class="bi bi-currency-rupee"></i><div><div class="l">Month total (tax invoices)</div><div class="v" style="font-size:1.2rem">{{ $money($total['tax_amount']) }}</div></div></div>
      </div>
      @if($total['not_in_tax'])
        <div class="alert alert-warning small"><b>{{ $total['not_in_tax'] }} {{ \Illuminate\Support\Str::plural('sub-bill', $total['not_in_tax']) }} not yet in a tax invoice</b> (shown in the sub-bill columns, not in the tax invoice amount). Make the tax invoice on the work order to complete the month.</div>
      @endif
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table mb-table">
        <thead><tr><th>#</th><th>Work order</th><th>Tax invoice no.</th><th class="text-center">Sub-bills</th><th class="text-center">Staff</th><th class="num">Wages</th><th class="num">EPF</th><th class="num">ESIC</th>
          <th class="num">Service charge</th><th class="num">Before GST</th><th class="num">CGST</th><th class="num">SGST</th>@if($igst)<th class="num">IGST</th>@endif<th class="num">Sub-bills total</th><th class="num">Tax invoice amount</th></tr></thead>
        <tbody>
        @foreach($rows as $n => $r)
          <tr>
            <td>{{ $n + 1 }}</td>
            <td><a class="mono fw-semibold text-decoration-none text-nowrap" href="{{ route('contracts.show', $r['contract']) }}">{{ $r['contract']->contract_no }}</a></td>
            <td class="text-nowrap">@forelse($r['tax_invoices'] as $t)<a class="mono d-block" href="{{ route('tax-invoices.show', $t) }}">{{ $t->number }}</a>@empty<span class="badge text-bg-warning">Not made yet</span>@endforelse</td>
            <td class="text-center">{{ $r['sub_bills'] }}@if($r['not_in_tax'])<div class="small text-warning-emphasis">{{ $r['not_in_tax'] }} open</div>@endif</td>
            <td class="text-center">{{ $r['staff'] }}</td>
            <td class="num">{{ $money($r['wages']) }}</td><td class="num">{{ $money($r['epf']) }}</td><td class="num">{{ $money($r['esic']) }}</td>
            <td class="num">{{ $money($r['service_charge']) }}</td><td class="num">{{ $money($r['taxable']) }}</td>
            <td class="num">{{ $money($r['cgst']) }}</td><td class="num">{{ $money($r['sgst']) }}</td>@if($igst)<td class="num">{{ $money($r['igst']) }}</td>@endif
            <td class="num">{{ $money($r['rounded_total']) }}</td><td class="num fw-semibold">{{ $money($r['tax_amount']) }}</td>
          </tr>
        @endforeach
        </tbody>
        <tfoot><tr class="bills-grand">
          <td colspan="3">Month total · {{ $total['work_orders'] }} work orders</td><td class="text-center">{{ $total['sub_bills'] }}</td><td class="text-center">{{ $total['staff'] }}</td>
          <td class="num">{{ $money($total['wages']) }}</td><td class="num">{{ $money($total['epf']) }}</td><td class="num">{{ $money($total['esic']) }}</td>
          <td class="num">{{ $money($total['service_charge']) }}</td><td class="num">{{ $money($total['taxable']) }}</td>
          <td class="num">{{ $money($total['cgst']) }}</td><td class="num">{{ $money($total['sgst']) }}</td>@if($igst)<td class="num">{{ $money($total['igst']) }}</td>@endif
          <td class="num">{{ $money($total['rounded_total']) }}</td><td class="num">{{ $money($total['tax_amount']) }}</td></tr></tfoot>
      </table></div></div>
      <p class="small text-secondary mt-2">Tax invoice amounts are worked out once on each work order's combined sub-total, so they can differ by a few paise from the sum of its sub-bills.</p>
    @endif
  </div>
</x-layouts.app>
