<x-layouts.app :title="$contract->exists ? 'Edit work order' : 'Add work order'" crumb="Masters / Work Orders">
  <form method="post" action="{{ $contract->exists ? route('contracts.update', $contract) : route('contracts.store') }}" class="z-card p-3">
    @csrf @if($contract->exists) @method('put') @endif
    <div class="row g-3">
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
      <x-select name="contract_type" label="Contract" :options="\App\Models\GemContract::TYPES" :value="$contract->contract_type ?? 'online'" required col="col-md-2" />
      <x-field name="bid_no" label="GeM bid no." :value="$contract->bid_no" col="col-md-4" class="mono" hint="e.g. GEM/2026/B/7972720 (needed for online contracts)" />
      <x-field name="contract_no" label="Contract no. (GeM or work order)" :value="$contract->contract_no" required col="col-md-4" class="mono" hint="e.g. GEMC-511687731718030" />
      <div class="col-12" id="already-saved" hidden><div class="alert alert-info small py-2 mb-0"><i class="bi bi-info-circle me-1"></i>
        <span id="already-text"></span> <a id="already-link" href="#" class="fw-semibold">Open it and add your project there</a> instead of entering it again.</div></div>
      <x-field name="contract_date" label="Contract date" type="date" :value="$contract->contract_date?->toDateString()" col="col-md-4" />
      <x-select name="billing_type" label="Billing type" :options="['monthly' => 'Monthly rate (consolidated)', 'daily' => 'Daily rate × paid days']" :value="$contract->billing_type" required col="col-md-4" />
      <x-field name="sac_code" label="SAC / HSN code" :value="$contract->sac_code ?? '998514'" col="col-md-2" class="mono" hint="Printed on bills" />
      <x-field name="service_charge_percent" label="Service charge (%)" :value="$contract->service_charge_percent" required col="col-md-2" hint="e.g. 3.85" />
      <x-field name="gst_percent" label="GST (%)" :value="$contract->gst_percent" required col="col-md-2" />
      <x-field name="epf_percent" label="Employer EPF (%)" :value="$contract->epf_percent" required col="col-md-2" hint="For staff with EPF on" />
      <x-field name="esic_percent" label="Employer ESIC (%)" :value="$contract->esic_percent" required col="col-md-2" hint="For staff with ESIC on" />
      <div class="col-12"><div class="form-check"><input type="hidden" name="service_charge_includes_gst" value="0"><input class="form-check-input" type="checkbox" name="service_charge_includes_gst" value="1" id="sc_incl" @checked(old('service_charge_includes_gst', $contract->service_charge_includes_gst ?? true))><label class="form-check-label" for="sc_incl">Service charge % <b>includes GST</b>: charge (amount × service charge %) ÷ 1.18, e.g. 3.85% ÷ 1.18 = 3.26%. Untick to charge the full % on the amount.</label></div></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="inter_state" value="1" id="inter_state" @checked(old('inter_state', $contract->inter_state))><label class="form-check-label" for="inter_state">Inter-state supply (charge IGST instead of CGST + SGST)</label></div></div>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}">Cancel</a><button class="btn btn-primary">Save work order</button></div>
  </form>
  <script>
    (function () {
      const agency = document.getElementById('f-agency_id'), client = document.getElementById('f-client_id');
      function update() {
        [...client.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !!agency.value && o.dataset.agency !== agency.value; } });
        if (client.selectedOptions[0] && client.selectedOptions[0].disabled) client.value = '';
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
</x-layouts.app>
