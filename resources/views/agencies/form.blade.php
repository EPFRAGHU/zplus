<x-layouts.app :title="$agency->exists ? 'Edit agency' : 'Add agency'" crumb="Masters / Agencies">
  <form method="post" action="{{ $agency->exists ? route('agencies.update', $agency) : route('agencies.store') }}" class="z-card p-3">
    @csrf @if($agency->exists) @method('put') @endif
    <div class="row g-3">
      <x-field name="name" label="Agency name" :value="$agency->name" required />
      <x-field name="code" label="Short code" :value="$agency->code" required col="col-md-2" hint="Used in letter numbers, e.g. SWH" />
      <x-field name="gstin" label="GSTIN" :value="$agency->gstin" col="col-md-2" class="mono" />
      <x-field name="pan" label="PAN" :value="$agency->pan" col="col-md-2" class="mono" />
      <x-textarea name="address" label="Address" :value="$agency->address" />
      <x-field name="email" label="Email" type="email" :value="$agency->email" col="col-md-4" />
      <x-field name="phone" label="Phone" :value="$agency->phone" col="col-md-2" />
      <x-field name="signatory_name" label="Signatory name" :value="$agency->signatory_name" col="col-md-3" />
      <x-field name="signatory_title" label="Signatory title" :value="$agency->signatory_title" col="col-md-3" hint="e.g. Managing Partner" />
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ route('agencies.index') }}">Cancel</a><button class="btn btn-primary">Save agency</button></div>
  </form>
</x-layouts.app>
