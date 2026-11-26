<x-layouts.app :title="$contract->exists ? 'Edit work order' : 'Add work order'" crumb="Masters / Work Orders" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-file-earmark-text"></i></div>
    <div class="me-auto"><h1>{{ $contract->exists ? 'Edit work order '.$contract->contract_no : 'Add work order, projects and staff' }}</h1>
      <p>@if($contract->exists)Agency: <b class="text-body">{{ $contract->agency?->name }}</b> · Client: <b class="text-body">{{ $contract->client?->name }}</b> · @endif Fields marked <span class="text-danger">*</span> are required. The rates entered here are used for every sub-bill and tax invoice of this work order.</p></div>
    <div class="wo-actions d-flex gap-2"><a class="btn wo-btn-line" href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}">Cancel</a><button class="btn wo-btn-primary" form="contract-form">{{ $contract->exists ? 'Save work order' : 'Save' }}</button></div>
  </div>
  <form method="post" id="contract-form" action="{{ $contract->exists ? route('contracts.update', $contract) : route('contracts.store') }}" class="wo-form">
    @csrf @if($contract->exists) @method('put') @endif
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>1</span>GeM contract / work order number</h2><p class="wo-sec-note">Online (GeM) or offline work order, with its bid and contract numbers.</p><div class="row g-3">
      <x-select name="contract_type" label="Contract" :options="\App\Models\GemContract::TYPES" :value="$contract->contract_type ?? 'online'" required col="col-md-3" />
      <x-field name="bid_no" label="GeM bid no." :value="$contract->bid_no" col="col-md-4" class="mono" hint="e.g. GEM/2026/B/7972720 (needed for online contracts)" />
      <x-field name="contract_no" label="Contract no. (GeM or work order)" :value="$contract->contract_no" required col="col-md-5" class="mono" hint="e.g. GEMC-511687731718030" />
      <div class="col-12" id="already-saved" hidden><div class="alert alert-info small py-2 mb-0"><i class="bi bi-info-circle me-1"></i>
        <span id="already-text"></span> <a id="already-link" href="#" class="fw-semibold">Open it and add your project there</a> instead of entering it again.</div></div>
    </div></div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>2</span>Contract details</h2><p class="wo-sec-note">Agency, client, status, service period and total value.</p><div class="row g-3">
      @php($agencyId = (string) old('agency_id', $contract->agency_id))
      @php($clientId = (string) old('client_id', $contract->client_id))
      <div class="col-md-4">
        <label for="f-agency_id" class="form-label small fw-semibold text-secondary mb-1">Agency <span class="text-danger">*</span></label>
        <select id="f-agency_id" name="agency_id" class="form-select @error('agency_id') is-invalid @enderror" required>
          <option value="">Choose agency</option>
          @foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === (string) $a->id)>{{ $a->name }}</option>@endforeach
        </select>
        @error('agency_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">{{ $locked ? 'Bills exist on this contract, so the agency and client are fixed.' : 'The agency that raises the bills.' }}</div>
      </div>
      <div class="col-md-4">
        <label for="f-client_id" class="form-label small fw-semibold text-secondary mb-1">Client <span class="text-danger">*</span></label>
        <select id="f-client_id" name="client_id" class="form-select @error('client_id') is-invalid @enderror" required>
          <option value="">Choose client</option>
          @foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === (string) $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Only clients of the selected agency are listed.</div>
      </div>
      <x-select name="status" label="Status" :options="['active' => 'Active', 'closed' => 'Closed']" :value="$contract->status ?? 'active'" required col="col-md-2" />
      <x-field name="contract_date" label="Contract date" type="date" :value="$contract->contract_date?->toDateString()" col="col-md-4" />
      <x-field name="start_date" label="Service start date" type="date" :value="$contract->start_date?->toDateString()" col="col-md-4" hint="From the GeM contract, e.g. 01-Oct-2026" />
      <x-field name="end_date" label="Service end date" type="date" :value="$contract->end_date?->toDateString()" col="col-md-4" hint="A warning shows 30 days before this date" />
      <x-field name="contract_value" label="Total contract value (₹)" :value="$contract->contract_value" col="col-md-4" hint="Incl. all duties and taxes, as printed on the contract" />
    </div></div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>3</span>Billing rules</h2><p class="wo-sec-note">How each sub-bill is worked out. Changes apply to bills made after saving; bills already made keep their figures.</p><div class="row g-3">
      <x-select name="billing_type" label="Billing type" :options="['monthly' => 'Monthly rate (consolidated)', 'daily' => 'Daily rate × paid days']" :value="$contract->billing_type" required col="col-md-4" />
      <x-field name="sac_code" label="SAC / HSN code" :value="$contract->sac_code ?? '998514'" col="col-md-2" class="mono" hint="Printed on bills" />
      <x-field name="service_charge_percent" label="Service charge (%)" :value="$contract->service_charge_percent" required col="col-md-2" hint="e.g. 3.85" />
      <x-field name="gst_percent" label="GST (%)" :value="$contract->gst_percent" required col="col-md-2" />
      <x-field name="epf_percent" label="Employer EPF (%)" :value="$contract->epf_percent" required col="col-md-2" hint="For staff with EPF on" />
      <x-field name="esic_percent" label="Employer ESIC (%)" :value="$contract->esic_percent" required col="col-md-2" hint="For staff with ESIC on" />
      <div class="col-12"><div class="form-check"><input type="hidden" name="service_charge_includes_gst" value="0"><input class="form-check-input" type="checkbox" name="service_charge_includes_gst" value="1" id="sc_incl" @checked(old('service_charge_includes_gst', $contract->service_charge_includes_gst ?? true))><label class="form-check-label" for="sc_incl">Service charge % <b>includes GST</b>: charge (amount × service charge %) ÷ 1.18, e.g. 3.85% ÷ 1.18 = 3.26%. Untick to charge the full % on the amount.</label></div></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="inter_state" value="1" id="inter_state" @checked(old('inter_state', $contract->inter_state))><label class="form-check-label" for="inter_state">Inter-state supply (charge IGST instead of CGST + SGST)</label></div></div>
      {{-- Live example with the rates above (owner, 26 Nov 2026): a ₹20,000 monthly salary, EPF and ESIC on. Display only. --}}
      <div class="col-12"><div class="alert alert-light border small mb-0" id="bill-example"></div></div>
    </div></div>
    @unless($contract->exists)
      <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>4</span>Projects, posts and staff</h2><p class="wo-sec-note">Add the projects of this work order with their posts and staff in one go.</p>
      @include('contracts._setup-builder')
      </div>
    @endunless
    <div class="wo-savebar"><a class="btn wo-btn-line" href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}">Cancel</a><button class="btn btn-primary">{{ $contract->exists ? 'Save work order' : 'Save work order, projects and staff' }}</button></div>
  </form>
  <script>
    (function () {   // "How a bill will be worked out" with the rates typed above
      const box = document.getElementById('bill-example');
      const val = id => parseFloat((document.getElementById(id) || {}).value) || 0;
      const inr = n => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      function show() {
        const wages = 20000, epf = Math.min(wages, 15000) * val('f-epf_percent') / 100, esic = wages * val('f-esic_percent') / 100;
        const sub = wages + epf + esic, incl = document.getElementById('sc_incl').checked;
        const sc = sub * val('f-service_charge_percent') / 100 / (incl ? 1 + val('f-gst_percent') / 100 : 1);
        const taxable = sub + sc, gst = taxable * val('f-gst_percent') / 100;
        box.innerHTML = '<b>How a bill will be worked out</b> (example: one person, ₹20,000 a month, EPF and ESIC on): wages ' + inr(wages) + ' + EPF ' + inr(epf) + ' + ESIC ' + inr(esic)
          + ' = ' + inr(sub) + '; service charge ' + inr(sc) + (incl ? ' (' + val('f-service_charge_percent') + '% ÷ ' + (1 + val('f-gst_percent') / 100) + ')' : '') + '; before GST ' + inr(taxable)
          + '; GST ' + inr(gst) + '; <b>total ' + inr(taxable + gst) + '</b>. The bill itself is exact to the paisa; only its grand total is rounded.';
      }
      ['f-epf_percent', 'f-esic_percent', 'f-service_charge_percent', 'f-gst_percent', 'sc_incl'].forEach(id => { const el = document.getElementById(id); if (el) { el.addEventListener('input', show); el.addEventListener('change', show); } });
      show();
    })();
    (function () {
      const agency = document.getElementById('f-agency_id'), client = document.getElementById('f-client_id');
      function update() {
        [...client.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !!agency.value && o.dataset.agency !== agency.value; } });
        if (client.selectedOptions[0] && client.selectedOptions[0].disabled) client.value = '';
        const open = [...client.options].filter(o => o.value && !o.disabled);
        if (!client.value && open.length === 1) client.value = open[0].value;   // one client per agency (owner, 21 Nov 2026)
      }
      agency.addEventListener('change', update); update();
    })();
    (function () {   // a bid / contract number that is already saved: point to that work order
      const saved = @json($saved), box = document.getElementById('already-saved');
      const bid = document.getElementById('f-bid_no'), no = document.getElementById('f-contract_no');
      const norm = v => (v || '').trim().toUpperCase();
      function check() {
        const hit = saved.find(k => (norm(no.value) && norm(k.no) === norm(no.value)) || (norm(bid.value) && norm(k.bid) === norm(bid.value)));
        box.hidden = !hit;
        if (hit) {
          document.getElementById('already-text').textContent = 'Work order ' + hit.no + (hit.bid ? ' (bid ' + hit.bid + ')' : '') + ' is already saved.';
          document.getElementById('already-link').href = hit.url;
        }
      }
      [bid, no].forEach(i => i && i.addEventListener('input', check)); check();
    })();
  </script>
  </div>
</x-layouts.app>
