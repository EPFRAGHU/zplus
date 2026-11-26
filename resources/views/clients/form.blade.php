<x-layouts.app :title="$client->exists ? 'Edit client' : 'Add client'" crumb="Masters / Clients" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-bank"></i></div>
    <div class="me-auto"><h1>{{ $client->exists ? 'Edit client '.$client->name : 'Add client' }}</h1>
      <p>@if($client->exists)Agency: <b class="text-body">{{ $client->agency?->name }}</b> · @endif Fields marked <span class="text-danger">*</span> are required.</p></div>
    <div class="wo-actions d-flex gap-2"><a class="btn wo-btn-line" href="{{ route('clients.index') }}">Cancel</a><button class="btn wo-btn-primary" form="client-form">Save client</button></div>
  </div>
  <form method="post" id="client-form" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="wo-form">
    @csrf @if($client->exists) @method('put') @endif
    @isset($copiedFrom)
      <div class="alert alert-light border small">Copied from <b>{{ $copiedFrom->label() }}</b>. Choose the other agency and save to add this client under it.</div>
    @endisset
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>1</span>Client</h2><p class="wo-sec-note">Who the client is, and which agency serves it. A client served by both agencies is added once under each.</p><div class="row g-3">
      <x-select name="agency_id" label="Agency" :options="$agencies->pluck('name', 'id')" :value="$client->agency_id" required placeholder="Choose agency" col="col-md-6"
        :hint="($hasContracts ?? false) ? 'This client has work orders, so its agency cannot be changed.' : (\App\Support\Agency\AgencyContext::id() ? 'Clients you add belong to your agency.' : 'Works with both agencies? Save this one, then add the client again for the other agency.')" />
      <div class="col-md-6"></div>
      <x-field name="name" label="Client name" :value="$client->name" required hint="e.g. ICMR-NIHR (RMRCBB), Bhubaneswar" />
      <x-field name="letter_code" label="Letter code" :value="$client->letter_code" col="col-md-4" maxlength="20" hint="For appointment letters, up to 20 characters. e.g. NIHR" />
      <x-select name="status" label="Status" :options="['active' => 'Active', 'hold' => 'On hold', 'inactive' => 'Inactive']" :value="$client->status" required col="col-md-3" />
    </div></div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>2</span>Billing</h2><p class="wo-sec-note">Printed on sub-bills and tax invoices. The GST state code decides CGST + SGST or IGST.</p><div class="row g-3">
      <x-field name="addressee" label="Bills addressed to" :value="$client->addressee" hint="e.g. The Administrative Officer" />
      <x-textarea name="billing_address" label="Billing address" :value="$client->billing_address" />
      <x-select name="state_code" label="State (GST code)" :options="\App\Support\IndianStates::options()" :value="$client->state_code" required placeholder="Choose state" col="col-md-4" hint="The GST state code is used on tax invoices" />
      <x-field name="district" label="District" :value="$client->district" col="col-md-4" list="district-list" autocomplete="off" hint="Pick from the list or type it" />
      <datalist id="district-list"></datalist>
      <x-field name="gstin" label="GSTIN" :value="$client->gstin" col="col-md-4" class="mono" />
      <x-field name="payment_term_days" label="Payment term (days)" type="number" :value="$client->payment_term_days" required col="col-md-3" />
    </div><div class="alert alert-light border small mt-3 mb-0">Service charge and GST rates are set on each work order.</div></div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>3</span>Contact</h2><p class="wo-sec-note">The person at the client who receives the bills.</p><div class="row g-3">
      <x-field name="contact_person" label="Contact person" :value="$client->contact_person" col="col-md-3" />
      <x-field name="phone" label="Contact number" :value="$client->phone" col="col-md-3" />
      <x-field name="email" label="Email" type="email" :value="$client->email" col="col-md-4" />
    </div></div>
    <div class="wo-savebar"><a class="btn wo-btn-line" href="{{ route('clients.index') }}">Cancel</a><button class="btn wo-btn-primary">Save client</button></div>
  </form>
  <script>
    (() => {
      const map = @json(\App\Support\IndianStates::districtMap());
      const state = document.getElementById('f-state_code'), list = document.getElementById('district-list');
      const fill = () => { list.replaceChildren(...(map[state.value] || []).map(d => { const o = document.createElement('option'); o.value = d; return o; })); };
      state.addEventListener('change', fill); fill();
    })();
  </script>
  </div>
</x-layouts.app>
