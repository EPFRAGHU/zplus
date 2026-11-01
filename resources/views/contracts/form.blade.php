<x-layouts.app :title="$contract->exists ? 'Edit GeM contract' : 'Add GeM contract'" crumb="Masters / GeM Contracts">
  <form method="post" action="{{ $contract->exists ? route('contracts.update', $contract) : route('contracts.store') }}" class="z-card p-3">
    @csrf @if($contract->exists) @method('put') @endif
    <div class="row g-3">
      <x-select name="agency_id" label="Billing agency" :options="$agencies->pluck('name', 'id')" :value="$contract->agency_id" required placeholder="Choose agency" col="col-md-4" />
      <x-select name="client_id" label="Client" :options="$clients->pluck('name', 'id')" :value="$contract->client_id" required placeholder="Choose client" col="col-md-4" />
      <x-select name="status" label="Status" :options="['active' => 'Active', 'closed' => 'Closed']" :value="$contract->status ?? 'active'" required col="col-md-4" />
      <x-field name="bid_no" label="GeM bid no." :value="$contract->bid_no" required col="col-md-4" class="mono" hint="e.g. GEM/2026/B/7972720" />
      <x-field name="contract_no" label="GeM contract no." :value="$contract->contract_no" required col="col-md-4" class="mono" hint="e.g. GEMC-511687731718030" />
      <x-field name="contract_date" label="Contract date" type="date" :value="$contract->contract_date?->toDateString()" col="col-md-4" />
      <x-select name="billing_type" label="Billing type" :options="['monthly' => 'Monthly rate (consolidated)', 'daily' => 'Daily rate × paid days + EPF/ESIC']" :value="$contract->billing_type" required col="col-md-4" />
      <x-field name="service_charge_percent" label="Service charge (%)" :value="$contract->service_charge_percent" required col="col-md-2" hint="On the total before GST" />
      <x-field name="gst_percent" label="GST (%)" :value="$contract->gst_percent" required col="col-md-2" />
      <x-field name="epf_percent" label="Employer EPF (%)" :value="$contract->epf_percent" required col="col-md-2" hint="Daily-rate only" />
      <x-field name="esic_percent" label="Employer ESIC (%)" :value="$contract->esic_percent" required col="col-md-2" hint="Daily-rate only" />
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="inter_state" value="1" id="inter_state" @checked(old('inter_state', $contract->inter_state))><label class="form-check-label" for="inter_state">Inter-state supply (charge IGST instead of CGST + SGST)</label></div></div>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}">Cancel</a><button class="btn btn-primary">Save contract</button></div>
  </form>
</x-layouts.app>
