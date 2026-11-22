<x-layouts.app :title="$receipt->exists ? 'Change payment' : 'Enter payment received'" :crumb="'Billing / <a href=\''.route('payments.index').'\'>Payments received</a>'" :quiet-title="true">
  @php
    $money = fn ($v) => inr((float) $v, true);
    $old = fn (string $k, $default = null) => old($k, $default);
    $fields = ['tds_it' => 'TDS 2%', 'tds_cgst' => 'CGST TDS 1%', 'tds_sgst' => 'SGST TDS 1%', 'tds_igst' => 'IGST TDS 2%', 'other_deduction' => 'Other deduction'];
    // IGST only when a bill has IGST (another state); CGST / SGST only when a bill has them.
    if ($rows->every(fn ($r) => (float) $r['bill']->igst == 0 && (float) ($r['values']['tds_igst'] ?? 0) == 0)) unset($fields['tds_igst']);
    if ($rows->isNotEmpty() && $rows->every(fn ($r) => (float) $r['bill']->igst > 0 && (float) ($r['values']['tds_cgst'] ?? 0) == 0)) unset($fields['tds_cgst'], $fields['tds_sgst']);
  @endphp
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-cash-coin"></i></div>
      <div class="me-auto"><h1>{{ $receipt->exists ? 'Change payment' : 'Enter payment received' }}</h1>
        <p>{{ $contract ? $contract->agency?->name.' · client: '.$contract->client?->name.' · work order '.$contract->contract_no : 'Choose the work order the client paid for' }}</p></div>
    </div>
    @if($receipt->status === 'returned' && $receipt->return_note)<div class="alert alert-warning small"><b>Returned by the Super Admin:</b> {{ $receipt->return_note }}</div>@endif
    @error('receipt')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @if($errors->any() && ! $errors->has('receipt'))<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif

    @unless($receipt->exists)
      <form class="wo-bar" method="get" action="{{ route('payments.create') }}">
        <label class="wo-pick"><span>Work order:</span>
          <select name="contract" class="form-select" onchange="this.form.submit()"><option value="">Choose…</option>
            @foreach($contracts as $c)<option value="{{ $c->id }}" @selected($contract?->id === $c->id)>{{ $c->contract_no }} – {{ $c->client?->short_code ?: $c->client?->name }} ({{ $c->agency?->code }})</option>@endforeach</select></label>
        <a class="btn btn-link px-1" href="{{ route('payments.index') }}">Back to Payments</a>
      </form>
    @endunless

    @if($contract)
      @if($rows->isEmpty())
        <div class="wo-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No sub-bills of this work order are waiting for payment. A sub-bill can be paid once it is <b>Submitted</b> to the client.</div>
      @else
      <form method="post" enctype="multipart/form-data" action="{{ $receipt->exists ? route('payments.update', $receipt) : route('payments.store') }}" id="pay-form">
        @csrf @if($receipt->exists) @method('PUT') @endif
        <input type="hidden" name="gem_contract_id" value="{{ $contract->id }}">
        <div class="wo-card p-3 mb-3">
          <div class="row g-3">
            <div class="col-md-2"><label class="form-label small fw-semibold">Mode</label>
              <select name="mode" class="form-select" id="pay-mode">@foreach(\App\Models\PaymentReceipt::MODES as $k => $v)<option value="{{ $k }}" @selected($old('mode', $receipt->mode) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-3" data-for="neft rtgs other"><label class="form-label small fw-semibold">UTR number</label><input name="utr_no" class="form-control mono" maxlength="40" value="{{ $old('utr_no', $receipt->utr_no) }}"></div>
            <div class="col-md-2" data-for="neft rtgs other"><label class="form-label small fw-semibold">NEFT / RTGS date</label><input type="date" name="transfer_date" class="form-control" value="{{ $old('transfer_date', $receipt->transfer_date?->format('Y-m-d')) }}"></div>
            <div class="col-md-2" data-for="cheque dd"><label class="form-label small fw-semibold">Cheque / DD no.</label><input name="cheque_no" class="form-control mono" maxlength="20" value="{{ $old('cheque_no', $receipt->cheque_no) }}"></div>
            <div class="col-md-2" data-for="cheque dd"><label class="form-label small fw-semibold">Cheque / DD date</label><input type="date" name="cheque_date" class="form-control" value="{{ $old('cheque_date', $receipt->cheque_date?->format('Y-m-d')) }}"></div>
            <div class="col-md-3" data-for="cheque dd"><label class="form-label small fw-semibold">Drawn on (bank)</label><input name="cheque_bank" class="form-control" maxlength="120" value="{{ $old('cheque_bank', $receipt->cheque_bank) }}"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold">Credit date (bank statement)</label><input type="date" name="credit_date" class="form-control" required max="{{ now()->format('Y-m-d') }}" value="{{ $old('credit_date', $receipt->credit_date?->format('Y-m-d')) }}"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Our account credited</label><input name="bank_account" class="form-control" maxlength="120" value="{{ $old('bank_account', $receipt->bank_account ?? $account) }}" placeholder="e.g. SBI A/c ****1234"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold">Amount credited (₹)</label><input name="amount" id="pay-amount" class="form-control text-end fw-semibold" inputmode="decimal" required value="{{ $old('amount', $receipt->amount) }}"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Payment advice (PDF / photo)</label><input type="file" name="advice" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
              @if($receipt->advice_path)<div class="small mt-1">Now: <a href="{{ route('payments.advice', $receipt) }}" target="_blank">{{ $receipt->advice_name }}</a> (a new file replaces it)</div>@endif</div>
            <div class="col-md-12"><label class="form-label small fw-semibold">Remarks</label><input name="remarks" class="form-control" maxlength="2000" value="{{ $old('remarks', $receipt->remarks) }}"></div>
          </div>
        </div>

        <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0 bills-table pay-lines">
          <thead><tr><th style="width:2rem"></th><th>Sub-bill no.</th><th>Month</th><th class="num">Before GST</th><th class="num">Still due</th>
            @foreach($fields as $f => $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Received (net)</th><th>Result</th></tr></thead>
          <tbody>
          @foreach($rows as $r)
            @php($b = $r['bill'])
            @php($picked = old("lines.{$b->id}.pick", $r['line'] || in_array($b->id, $preselect, true) || (! $receipt->exists && ! $preselect)))
            <tr data-due="{{ $r['due'] }}">
              <td><input class="form-check-input" type="checkbox" name="lines[{{ $b->id }}][pick]" value="1" @checked($picked) aria-label="Pay {{ $b->invoice_no }}"></td>
              <td><a class="mono fw-semibold text-decoration-none" href="{{ route('invoices.show', $b) }}" target="_blank">{{ $b->invoice_no }}</a></td>
              <td>{{ $b->month->format('M Y') }}</td><td class="num">{{ $money($b->taxable) }}</td><td class="num">{{ $money($r['due']) }}</td>
              @foreach(array_keys($fields) as $f)
                <td><input class="form-control form-control-sm text-end" style="min-width:90px" name="lines[{{ $b->id }}][{{ $f }}]" inputmode="decimal" value="{{ old("lines.{$b->id}.{$f}", $r['values'][$f] ?? '0.00') }}" ></td>
              @endforeach
              <td><input class="form-control form-control-sm text-end fw-semibold" style="min-width:110px" name="lines[{{ $b->id }}][received]" inputmode="decimal" value="{{ old("lines.{$b->id}.received", $r['values']['received'] ?? '0.00') }}"></td>
              <td class="small" data-result></td>
            </tr>
            <tr class="pay-reason" data-reason-for="{{ $b->id }}"><td></td><td colspan="{{ 6 + count($fields) }}"><input class="form-control form-control-sm" name="lines[{{ $b->id }}][other_reason]" maxlength="200" placeholder="Reason for the other deduction on {{ $b->invoice_no }} (penalty, shortfall, LD…)" value="{{ old("lines.{$b->id}.other_reason", $r['values']['other_reason'] ?? '') }}"></td></tr>
          @endforeach
          </tbody>
          <tfoot><tr class="bills-grand"><td colspan="5">Total of ticked sub-bills</td>@foreach(array_keys($fields) as $f)<td class="num" data-sum="{{ $f }}"></td>@endforeach<td class="num" data-sum="received"></td><td data-check></td></tr></tfoot>
        </table></div></div>
        <p class="small text-secondary mt-2">The deductions are filled in as expected (TDS 2% and GST TDS 2% of the value before GST, rounded to the rupee). <b>Change them to what the client actually deducted</b> (see the payment advice). Received + deductions must not be more than what is still due; within ₹1 the sub-bill counts as paid in full. The amount credited must equal the total received.</p>
        <div class="d-flex gap-2 mt-2">
          <button class="btn wo-btn-primary"><i class="bi bi-check-lg me-1"></i>{{ $receipt->exists ? 'Save changes' : 'Save payment' }}</button>
          <a class="btn btn-link" href="{{ $receipt->exists ? route('payments.show', $receipt) : route('payments.index') }}">Cancel</a>
        </div>
      </form>
      @endif
    @endif
  </div>
  <script>
    (function () {
      const form = document.getElementById('pay-form');
      if (!form) return;
      const mode = document.getElementById('pay-mode'), amount = document.getElementById('pay-amount');
      const showMode = () => form.querySelectorAll('[data-for]').forEach(el => el.classList.toggle('d-none', !el.dataset.for.split(' ').includes(mode.value)));
      mode.addEventListener('change', showMode); showMode();
      const num = v => { const n = parseFloat(String(v).replace(/,/g, '')); return isNaN(n) ? 0 : n; };
      const fmt = n => n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const keys = @json(array_keys($fields));
      let amountTouched = amount.value !== '';
      amount.addEventListener('input', () => { amountTouched = true; update(); });
      function update() {
        const sums = { received: 0 }; keys.forEach(k => sums[k] = 0);
        form.querySelectorAll('tr[data-due]').forEach(tr => {
          const on = tr.querySelector('[name$="[pick]"]').checked, id = tr.querySelector('[name$="[pick]"]').name.match(/\d+/)[0];
          tr.querySelectorAll('input:not([type=checkbox])').forEach(i => i.disabled = !on);
          tr.classList.toggle('opacity-50', !on);
          const val = k => num(tr.querySelector('[name$="[' + k + ']"]').value);
          const other = val('other_deduction');
          const reason = form.querySelector('[data-reason-for="' + id + '"]');
          reason.classList.toggle('d-none', !on || other <= 0);
          reason.querySelector('input').disabled = !on;
          const res = tr.querySelector('[data-result]');
          if (!on) { res.textContent = ''; return; }
          let settles = val('received'); keys.forEach(k => { sums[k] += val(k); settles += val(k); });
          sums.received += val('received');
          const left = num(tr.dataset.due) - settles;
          res.innerHTML = left > 1 ? '<span class="text-warning-emphasis">Part paid · ₹' + fmt(left) + ' left</span>'
            : (left < -1 ? '<span class="text-danger">More than due by ₹' + fmt(-left) + '</span>' : '<span class="text-success fw-semibold">Paid in full</span>');
        });
        keys.concat(['received']).forEach(k => form.querySelector('[data-sum="' + k + '"]').textContent = '₹' + fmt(sums[k]));
        if (!amountTouched) amount.value = sums.received.toFixed(2);
        const diff = num(amount.value) - sums.received;
        form.querySelector('[data-check]').innerHTML = Math.abs(diff) < 0.005 ? '<span class="text-white">= amount credited</span>' : '<span class="text-warning">Amount credited differs by ₹' + fmt(diff) + '</span>';
      }
      form.addEventListener('input', update); form.addEventListener('change', update); update();
    })();
  </script>
</x-layouts.app>
