<x-layouts.app :title="$client->exists ? 'Edit client' : 'Add client'" crumb="Masters / Clients">
  <form method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="z-card p-3">
    @csrf @if($client->exists) @method('put') @endif
    @isset($copiedFrom)
      <div class="alert alert-light border small">Copied from <b>{{ $copiedFrom->label() }}</b>. Choose the other agency and save to add this client under it.</div>
    @endisset
    <div class="row g-3">
      <x-select name="agency_id" label="Agency" :options="$agencies->pluck('name', 'id')" :value="$client->agency_id" required placeholder="Choose agency" col="col-md-6"
        :hint="($hasContracts ?? false) ? 'This client has work orders, so its agency cannot be changed.' : (auth()->user()->agency_id ? 'Clients you add belong to your agency.' : 'Works with both agencies? Save this one, then add the client again for the other agency.')" />
      <div class="col-md-6"></div>
      <x-field name="name" label="Client name" :value="$client->name" required hint="e.g. ICMR-NIHR (RMRCBB), Bhubaneswar" />
      <x-field name="short_code" label="Bill code" :value="$client->short_code" required col="col-md-3" maxlength="16" hint="Letters, numbers, / and -; up to 16. e.g. ICMR" />
      <x-field name="letter_code" label="Letter code" :value="$client->letter_code" col="col-md-3" maxlength="16" hint="For appointment letters; / and - allowed, up to 16. e.g. NIHR" />
      <x-field name="addressee" label="Bills addressed to" :value="$client->addressee" hint="e.g. The Administrative Officer" />
      <x-field name="contact_person" label="Contact person" :value="$client->contact_person" col="col-md-3" />
      <x-field name="phone" label="Contact number" :value="$client->phone" col="col-md-3" />
      <x-textarea name="billing_address" label="Billing address" :value="$client->billing_address" />
      <x-field name="email" label="Email" type="email" :value="$client->email" col="col-md-4" />
      <x-select name="state_code" label="State (GST code)" :options="\App\Support\IndianStates::options()" :value="$client->state_code" required placeholder="Choose state" col="col-md-4" hint="The GST state code is used on tax invoices" />
      <x-field name="district" label="District" :value="$client->district" col="col-md-4" list="district-list" autocomplete="off" hint="Pick from the list or type it" />
      <datalist id="district-list"></datalist>
      <x-field name="gstin" label="GSTIN" :value="$client->gstin" col="col-md-4" class="mono" />
      <x-field name="payment_term_days" label="Payment term (days)" type="number" :value="$client->payment_term_days" required col="col-md-3" />
      <x-select name="status" label="Status" :options="['active' => 'Active', 'hold' => 'On hold', 'inactive' => 'Inactive']" :value="$client->status" required col="col-md-3" />
    </div>
    <div class="alert alert-light border small mt-3 mb-0">Service charge and GST rates are set on each work order.</div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Cancel</a><button class="btn btn-primary">Save client</button></div>
  </form>
  <script>
    (() => {
      const map = @json(\App\Support\IndianStates::districtMap());
      const state = document.getElementById('f-state_code'), list = document.getElementById('district-list');
      const fill = () => { list.replaceChildren(...(map[state.value] || []).map(d => { const o = document.createElement('option'); o.value = d; return o; })); };
      state.addEventListener('change', fill); fill();
    })();
  </script>
</x-layouts.app>
