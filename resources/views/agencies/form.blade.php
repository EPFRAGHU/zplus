<x-layouts.app :title="$agency->exists ? 'Edit agency' : 'Add agency'" crumb="Masters / Agencies">
  <form method="post" action="{{ $agency->exists ? route('agencies.update', $agency) : route('agencies.store') }}" class="z-card p-3" enctype="multipart/form-data">
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
    <h2 class="h6 fw-bold mt-4 mb-1">Letterhead</h2>
    <p class="small text-secondary mb-2">Printed at the top of bills, covering letters and appointment letters. The footer line uses the website, e-mails and mobile numbers.</p>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary mb-1" for="f-logo">Logo / seal (PNG or JPG, up to 1 MB)</label>
        @if($agency->logo_path)
          <div class="d-flex align-items-center gap-2 mb-2"><img src="{{ route('agencies.logo', $agency) }}" alt="Current logo" style="width:64px;height:64px;object-fit:contain" class="border rounded">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo"><label class="form-check-label small" for="remove_logo">Remove</label></div></div>
        @endif
        <input id="f-logo" type="file" name="logo" accept=".png,.jpg,.jpeg" class="form-control @error('logo') is-invalid @enderror">
        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-9 row g-3 m-0 p-0">
        <x-field name="tagline" label="Tagline" :value="$agency->tagline" col="col-12" hint="e.g. A leading Organization of Manpower & Security Services" />
        <x-textarea name="registrations" label="Registration lines (one per line)" :value="$agency->registrations" rows="3" />
      </div>
      <x-field name="website" label="Website" :value="$agency->website" col="col-md-4" />
      <x-field name="letterhead_emails" label="E-mails on letterhead" :value="$agency->letterhead_emails" col="col-md-5" hint="Separate with commas" />
      <x-field name="phones" label="Mobile numbers on letterhead" :value="$agency->phones" col="col-md-3" />
    </div>
    <h2 class="h6 fw-bold mt-4 mb-1">Bank details</h2>
    <p class="small text-secondary mb-2">Printed on tax invoices so the client knows where to pay.</p>
    <div class="row g-3">
      <x-field name="bank_name" label="Bank name" :value="$agency->bank_name" col="col-md-3" />
      <x-field name="bank_account_no" label="Account number" :value="$agency->bank_account_no" col="col-md-3" class="mono" />
      <x-field name="bank_ifsc" label="IFSC" :value="$agency->bank_ifsc" col="col-md-3" class="mono text-uppercase" />
      <x-field name="bank_branch" label="Branch" :value="$agency->bank_branch" col="col-md-3" />
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ route('agencies.index') }}">Cancel</a><button class="btn btn-primary">Save agency</button></div>
  </form>
</x-layouts.app>
