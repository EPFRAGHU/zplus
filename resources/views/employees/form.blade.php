<x-layouts.app :title="$employee->exists ? 'Edit '.$employee->name : 'Add employee'" crumb="Masters / Employees">
  <form method="post" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="z-card">
    @csrf @if($employee->exists) @method('put') @endif
    <ul class="nav nav-tabs px-3 pt-2" role="tablist">
      @foreach(['basic' => 'Basic info', 'work' => 'Work info', 'bank' => 'Bank & payout', 'kyc' => 'KYC & statutory'] as $k => $v)
        <li class="nav-item"><button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#t-{{ $k }}" type="button" role="tab">{{ $v }}</button></li>
      @endforeach
    </ul>
    <div class="tab-content p-3">
      <div class="tab-pane fade show active" id="t-basic" role="tabpanel"><div class="row g-3">
        <x-field name="name" label="Employee name" :value="$employee->name" required />
        <x-field name="father_name" label="Father's name" :value="$employee->father_name" />
        <x-field name="dob" label="Date of birth" type="date" :value="$employee->dob?->toDateString()" col="col-md-3" />
        <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :value="$employee->gender" placeholder="—" col="col-md-3" />
        <x-field name="mobile" label="Mobile" :value="$employee->mobile" col="col-md-3" />
        <x-field name="whatsapp" label="WhatsApp" :value="$employee->whatsapp" col="col-md-3" hint="Leave blank if same as mobile" />
        <x-field name="email" label="Email" type="email" :value="$employee->email" />
        <x-field name="qualification" label="Qualification" :value="$employee->qualification" />
        <x-textarea name="address" label="Address" :value="$employee->address" />
      </div></div>
      <div class="tab-pane fade" id="t-work" role="tabpanel"><div class="row g-3">
        <x-field name="emp_code" label="Employee ID" :value="$employee->emp_code" required col="col-md-3" class="mono" />
        <x-select name="post_id" label="GeM post" col="col-md-9" :value="$employee->post_id" placeholder="Not linked to a post"
          :options="$posts->mapWithKeys(fn ($p) => [$p->id => $p->project->contract->bid_no.' · '.$p->label().' ('.$p->active_employees_count.'/'.$p->sanctioned_qty.' filled)'])"
          hint="The bill rate comes from the post unless you set a different salary below." />
        <x-field name="joining_date" label="Joining date" type="date" :value="$employee->joining_date?->toDateString()" col="col-md-3" />
        <x-field name="exit_date" label="Exit date" type="date" :value="$employee->exit_date?->toDateString()" col="col-md-3" />
        <x-select name="status" label="Status" :options="\App\Models\Employee::STATUSES" :value="$employee->status" required col="col-md-3" />
        <x-field name="rate_override" label="Salary if different from post (₹)" type="number" step="0.01" :value="$employee->rate_override" col="col-md-3" />
      </div></div>
      <div class="tab-pane fade" id="t-bank" role="tabpanel"><div class="row g-3">
        <x-field name="bank_name" label="Bank name" :value="$employee->bank_name" />
        <x-field name="account_holder" label="Account holder name" :value="$employee->account_holder" hint="As printed on the passbook" />
        @can('view-bank-details')
          <x-field name="account_number" label="Account number" value="" col="col-md-4" class="mono" autocomplete="off"
            :hint="$employee->account_last4 ? 'Saved: '.$employee->maskedAccount().'. Leave blank to keep it.' : 'Digits only'" />
        @endcan
        <x-field name="ifsc" label="IFSC" :value="$employee->ifsc" col="col-md-4" class="mono text-uppercase" hint="11 characters, e.g. CNRB0002957" />
        <x-field name="upi_id" label="UPI ID (optional)" :value="$employee->upi_id" col="col-md-4" class="mono" />
        <div class="col-12"><div class="alert alert-light border small mb-0">Bank account verification and Cashfree payouts arrive in the next update. Changing the account number clears any earlier verification.</div></div>
      </div></div>
      <div class="tab-pane fade" id="t-kyc" role="tabpanel"><div class="row g-3">
        @can('view-bank-details')
          <x-field name="aadhaar" label="Aadhaar number" value="" col="col-md-4" class="mono" autocomplete="off"
            :hint="$employee->aadhaar_last4 ? 'Saved: XXXX XXXX '.$employee->aadhaar_last4.'. Leave blank to keep it.' : 'Stored encrypted'" />
        @endcan
        <x-field name="pan" label="PAN" :value="$employee->pan" col="col-md-4" class="mono text-uppercase" />
        <x-field name="uan" label="UAN (PF)" :value="$employee->uan" col="col-md-4" class="mono" />
        <x-field name="esic_ip" label="ESIC IP number" :value="$employee->esic_ip" col="col-md-4" class="mono" />
        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="pf_applicable" value="1" id="pf" @checked(old('pf_applicable', $employee->pf_applicable))><label class="form-check-label" for="pf">PF applicable</label></div></div>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="esic_applicable" value="1" id="esi" @checked(old('esic_applicable', $employee->esic_applicable))><label class="form-check-label" for="esi">ESIC applicable</label></div></div>
      </div></div>
    </div>
    <div class="d-flex gap-2 justify-content-end p-3 border-top">
      <a class="btn btn-outline-secondary" href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}">Cancel</a>
      <button class="btn btn-primary">Save employee</button>
    </div>
  </form>
  @if($errors->any())
  <script>document.addEventListener('DOMContentLoaded',()=>{const bad=document.querySelector('.is-invalid');const pane=bad&&bad.closest('.tab-pane');if(pane){bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#'+pane.id+'"]')).show();}});</script>
  @endif
</x-layouts.app>
