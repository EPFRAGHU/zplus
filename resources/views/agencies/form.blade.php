<x-layouts.app :title="$agency->exists ? 'Edit agency' : 'Add agency'" crumb="Masters / Agencies" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-building"></i></div>
    <div class="me-auto"><h1>{{ $agency->exists ? 'Edit '.$agency->name : 'Add agency' }}</h1>
      <p>Letterhead, GST and registration details, bank account and the codes for the bank's NEFT upload. Fields marked <span class="text-danger">*</span> are required.</p></div>
    <div class="wo-actions d-flex gap-2"><a class="btn wo-btn-line" href="{{ route('agencies.index') }}">Cancel</a><button class="btn wo-btn-primary" form="agency-form">Save agency</button></div>
  </div>
  <form method="post" action="{{ $agency->exists ? route('agencies.update', $agency) : route('agencies.store') }}" id="agency-form" class="wo-form" enctype="multipart/form-data">
    @csrf @if($agency->exists) @method('put') @endif
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>1</span>Agency &amp; registrations</h2>
    <div class="row g-3">
      <x-field name="name" label="Agency name" :value="$agency->name" required />
      <x-field name="code" label="Short code" :value="$agency->code" required col="col-md-2" hint="Used in letter numbers, e.g. SWH" />
      <x-field name="number_prefix" label="Bill / Emp ID prefix" :value="$agency->number_prefix" col="col-md-2" class="mono" maxlength="6" hint="SW → SW/001/26-27, SW-1001" />
      <x-field name="gstin" label="GSTIN" :value="$agency->gstin" col="col-md-2" class="mono" />
      <x-field name="pan" label="PAN" :value="$agency->pan" col="col-md-2" class="mono" />
      <x-textarea name="address" label="Address" :value="$agency->address" />
      <x-field name="email" label="Email" type="email" :value="$agency->email" col="col-md-4" />
      <x-field name="phone" label="Phone" :value="$agency->phone" col="col-md-2" />
      <x-field name="signatory_name" label="Signatory name" :value="$agency->signatory_name" col="col-md-3" />
      <x-field name="signatory_title" label="Signatory title" :value="$agency->signatory_title" col="col-md-3" hint="e.g. Managing Partner" />
    </div>
    </div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>2</span>Letterhead</h2>
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
        <div class="col-12">
          <label class="form-label" for="f-letterhead_style">Letterhead layout</label>
          <select id="f-letterhead_style" name="letterhead_style" class="form-select">
            @foreach(['classic' => 'Centred, with website and mobiles in the footer (Z Plus)', 'banner' => 'Logo, name, registrations column and address box (SWACHHA)'] as $v => $l)
              <option value="{{ $v }}" @selected(old('letterhead_style', $agency->letterhead_style ?? 'classic') === $v)>{{ $l }}</option>
            @endforeach
          </select>
          <div class="form-text">For the banner layout, write registration lines as "Label: value", e.g. "PAN No: AADAS8034Q". The GST line is added from the GSTIN field.</div>
        </div>
      </div>
      <x-field name="website" label="Website" :value="$agency->website" col="col-md-4" />
      <x-field name="letterhead_emails" label="E-mails on letterhead" :value="$agency->letterhead_emails" col="col-md-5" hint="Separate with commas" />
      <x-field name="phones" label="Mobile numbers on letterhead" :value="$agency->phones" col="col-md-3" />
    </div>
    </div>
    {{-- Security audit M2 (owner, 26 Nov 2026): only a Super Admin changes the bank details and NEFT codes; others see them read-only. --}}
    @php($canBank = \App\Services\Approvals\Approvals::canApprove(auth()->user()))
    <fieldset @disabled(! $canBank) class="m-0 p-0 border-0">
    @unless($canBank)<div class="alert alert-light border small"><i class="bi bi-lock me-1"></i>The bank details and NEFT codes can be changed only by a <b>Super Admin</b>, because the bank account is printed on every tax invoice where the client pays. Ask a Super Admin to change them.</div>@endunless
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>3</span>Bank details</h2>
    <p class="small text-secondary mb-2">Printed on tax invoices so the client knows where to pay.</p>
    <div class="row g-3">
      <x-field name="bank_name" label="Bank name" :value="$agency->bank_name" col="col-md-3" />
      <x-field name="bank_account_no" label="Account number" :value="$agency->bank_account_no" col="col-md-3" class="mono" />
      <x-field name="bank_ifsc" label="IFSC" :value="$agency->bank_ifsc" col="col-md-3" class="mono text-uppercase" />
      <x-field name="bank_branch" label="Branch" :value="$agency->bank_branch" col="col-md-3" />
    </div>
    </div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>4</span>Bank salary upload (NEFT file)</h2>
    <p class="small text-secondary mb-2">As given by the bank for corporate internet banking. Printed in every row of the NEFT file on the Bank Letter page; the debit account is the account number above.</p>
    <div class="row g-3">
      <x-field name="bank_corp_id" label="Corporate ID (corp id)" :value="$agency->bank_corp_id" col="col-md-3" class="mono" />
      <x-field name="bank_corp_name" label="Corporate name (corp name)" :value="$agency->bank_corp_name" col="col-md-3" class="text-uppercase" />
      <x-field name="bank_address_code" label="Address (e.g. BBSR)" :value="$agency->bank_address_code" col="col-md-3" class="text-uppercase" />
    </div>
    </div>
    </fieldset>
    <div class="wo-savebar"><a class="btn wo-btn-line" href="{{ route('agencies.index') }}">Cancel</a><button class="btn wo-btn-primary">Save agency</button></div>
  </form>
  </div>
</x-layouts.app>
