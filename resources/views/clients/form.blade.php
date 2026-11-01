<x-layouts.app :title="$client->exists ? 'Edit client' : 'Add client'" crumb="Masters / Clients">
  <form method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="z-card p-3">
    @csrf @if($client->exists) @method('put') @endif
    <div class="row g-3">
      <x-field name="name" label="Client name" :value="$client->name" required hint="e.g. ICMR-NIHR (RMRCBB), Bhubaneswar" />
      <x-field name="short_code" label="Bill code" :value="$client->short_code" required col="col-md-3" hint="Bill numbers start with this, e.g. ICMR" />
      <x-field name="letter_code" label="Letter code" :value="$client->letter_code" col="col-md-3" hint="Used in appointment letters, e.g. NIHR" />
      <x-field name="addressee" label="Bills addressed to" :value="$client->addressee" hint="e.g. The Administrative Officer" />
      <x-field name="contact_person" label="Contact person" :value="$client->contact_person" col="col-md-3" />
      <x-field name="phone" label="Contact number" :value="$client->phone" col="col-md-3" />
      <x-textarea name="billing_address" label="Billing address" :value="$client->billing_address" />
      <x-field name="email" label="Email" type="email" :value="$client->email" col="col-md-4" />
      <x-field name="district" label="District" :value="$client->district" col="col-md-2" />
      <x-field name="state_code" label="State code" :value="$client->state_code" required col="col-md-2" hint="21 = Odisha" />
      <x-field name="gstin" label="GSTIN" :value="$client->gstin" col="col-md-4" class="mono" />
      <x-field name="payment_term_days" label="Payment term (days)" type="number" :value="$client->payment_term_days" required col="col-md-3" />
      <x-select name="status" label="Status" :options="['active' => 'Active', 'hold' => 'On hold', 'inactive' => 'Inactive']" :value="$client->status" required col="col-md-3" />
    </div>
    <div class="alert alert-light border small mt-3 mb-0">Service charge and GST rates are set on each GeM contract.</div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Cancel</a><button class="btn btn-primary">Save client</button></div>
  </form>
</x-layouts.app>
