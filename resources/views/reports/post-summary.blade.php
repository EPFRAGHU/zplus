<x-layouts.app title="Post-wise summary" crumb="Billing / <span class='text-danger'>Post-wise summary</span>" :quiet-title="true">
  @php
    $money = fn ($v) => inr((float) $v, true);
    $igst = $data && $data['igst'];
    $cgst = ! $data || ! $igst || (float) $data['total']['cgst'] != 0;
    $cols = array_keys(array_filter($headings, fn ($h, $k) => ($k !== 'igst' || $igst) && (! in_array($k, ['cgst', 'sgst']) || $cgst), ARRAY_FILTER_USE_BOTH));
    $query = array_filter(['month' => $month->format('Y-m'), 'contract' => $contract?->id, 'bill' => $bill?->id, 'post' => $post]);
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-diagram-3"></i></div>
      <div class="me-auto"><h1>Post-wise summary – {{ $month->format('F Y') }}</h1>
        <p>{{ $contract ? $contract->agency?->name.' · client: '.$contract->client?->name.' · work order '.$contract->contract_no : 'Choose the work order' }} · wages, EPF, ESIC, service charge, value before GST and GST per post, for filing GeM returns.</p></div>
      @if($data && $data['rows'])
        <div class="wo-actions d-flex gap-2 flex-wrap">
          <a class="btn wo-btn-green" href="{{ route('post-summary.excel', $query) }}"><i class="bi bi-file-earmark-excel me-1"></i>Download Excel</a>
          <a class="btn wo-btn-primary" href="{{ route('post-summary.pdf', $query) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a></div>
      @endif
    </div>

    <form class="wo-bar" method="get" action="{{ route('post-summary.show') }}" id="ps-form">
      <div class="wo-pick"><span>Month:</span><x-month-picker :value="$month->format('Y-m')" /></div>
      <label class="wo-pick"><span>Work order:</span>
        <select name="contract" class="form-select" data-ps="contract"><option value="">Choose…</option>
          @foreach($contracts as $c)<option value="{{ $c->id }}" @selected($contract?->id === $c->id)>{{ $c->contract_no }} – {{ $c->client?->short_code ?: $c->client?->name }} ({{ $c->agency?->code }})</option>@endforeach</select></label>
      <label class="wo-pick"><span>Sub-bill:</span>
        <select name="bill" class="form-select" data-ps="bill" @disabled(! $contract)><option value="">All sub-bills</option>
          @foreach($bills as $b)<option value="{{ $b->id }}" @selected($bill?->id === $b->id)>{{ $b->invoice_no }}</option>@endforeach</select></label>
      <label class="wo-pick"><span>Post:</span>
        <select name="post" class="form-select" data-ps="post" @disabled(! $contract)><option value="">All posts</option>
          @foreach($posts as $p)<option value="{{ $p }}" @selected($post !== null && \App\Http\Controllers\PostController::postKey($p) === \App\Http\Controllers\PostController::postKey($post))>{{ $p }}</option>@endforeach</select></label>
      <button class="btn wo-btn-primary">Show</button>
      <a class="btn btn-link px-1" href="{{ route('invoices.index', array_filter(['month' => $month->format('Y-m'), 'contract' => $contract?->id])) }}">Back to Sub-bills</a>
    </form>

    @if($contracts->isEmpty())
      <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No sub-bills in {{ $month->format('F Y') }}. Choose another month.</div>
    @elseif(! $contract)
      <div class="wo-empty">Choose the work order, then (if you like) one sub-bill and one post.</div>
    @elseif(! $data['rows'])
      <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Nothing billed for this choice.</div>
    @else
      <div class="wo-kpis">
        <div class="wo-kpi k-blue"><i class="bi bi-receipt"></i><div><div class="l">Sub-bills</div><div class="v">{{ $data['bills']->count() }}</div></div></div>
        <div class="wo-kpi k-violet"><i class="bi bi-person-badge"></i><div><div class="l">Posts</div><div class="v">{{ count($data['rows']) }}</div></div></div>
        <div class="wo-kpi k-green"><i class="bi bi-people"></i><div><div class="l">Staff billed</div><div class="v">{{ $data['total']['staff'] }}</div></div></div>
        <div class="wo-kpi k-amber"><i class="bi bi-cash-stack"></i><div><div class="l">Before GST</div><div class="v" style="font-size:1.2rem">{{ $money($data['total']['taxable']) }}</div></div></div>
        <div class="wo-kpi k-red"><i class="bi bi-currency-rupee"></i><div><div class="l">Total with GST</div><div class="v" style="font-size:1.2rem">{{ $money($data['total']['total']) }}</div></div></div>
      </div>
      <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table mb-table post-summary">
        <thead><tr><th>#</th><th>Post</th><th>Sub-bill(s)</th><th class="text-center">Staff</th><th class="text-center">Days paid</th>
          @foreach($cols as $k)<th class="num">{{ $headings[$k] }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($data['rows'] as $n => $r)
          <tr><td>{{ $n + 1 }}</td><td class="fw-semibold">{{ $r['post'] }}</td><td class="small mono">{{ $r['bills'] }}</td><td class="text-center">{{ $r['staff'] }}</td><td class="text-center">{{ $r['days'] }}</td>
            @foreach($cols as $k)<td class="num">{{ $money($r[$k]) }}</td>@endforeach</tr>
        @endforeach
        </tbody>
        <tfoot>
          <tr class="bills-grand"><td colspan="3">Total · {{ count($data['rows']) }} {{ \Illuminate\Support\Str::plural('post', count($data['rows'])) }}</td><td class="text-center">{{ $data['total']['staff'] }}</td><td class="text-center">{{ $data['total']['days'] }}</td>
            @foreach($cols as $k)<td class="num">{{ $money($data['total'][$k]) }}</td>@endforeach</tr>
          @unless($post)
            <tr class="text-secondary fst-italic"><td colspan="5">As on the sub-bill{{ $data['bills']->count() === 1 ? '' : 's' }} (stored figures)</td>
              @foreach($cols as $k)<td class="num">{{ $money($data['stored'][$k]) }}</td>@endforeach</tr>
          @endunless
        </tfoot>
      </table></div></div>
      <p class="small text-secondary mt-2">Wages, EPF and ESIC are as billed for each person. The service charge and GST are worked out per post with each sub-bill's own rates
        ({{ $data['bills']->pluck('service_charge_percent')->unique()->map(fn ($v) => rtrim(rtrim((string) $v, '0'), '.').'%')->implode(', ') }} service charge{{ $data['bills']->contains('service_charge_includes_gst', true) ? ', including GST' : '' }}); a paisa of difference to the bill can come from the bill's own rounding.</p>
    @endif
  </div>
  <script>
    (function () {
      // A new work order or sub-bill shows again at once; a new work order clears the sub-bill and post.
      const form = document.getElementById('ps-form');
      form.querySelectorAll('[data-ps]').forEach(function (s) {
        s.addEventListener('change', function () {
          if (s.dataset.ps === 'contract') { form.querySelector('[data-ps=bill]').value = ''; form.querySelector('[data-ps=post]').value = ''; }
          if (s.dataset.ps === 'bill') { form.querySelector('[data-ps=post]').value = ''; }
          form.submit();
        });
      });
    })();
  </script>
</x-layouts.app>
