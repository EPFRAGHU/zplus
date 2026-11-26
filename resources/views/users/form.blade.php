@php($editing = $user->exists)
@php($chosen = old('projects', $user->projects?->pluck('id')->all() ?? []))
<x-layouts.app :title="$editing ? 'Edit '.$user->name : 'Add user'" :crumb="'Setup / <a href=\''.route('users.index').'\'>Users</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-person-gear"></i></div>
    <div class="me-auto"><h1>{{ $editing ? 'Edit '.$user->name : 'Add user' }}</h1>
      <p>Who can sign in, for which agency, and with which role. Fields marked <span class="text-danger">*</span> are required.</p></div>
    <div class="wo-actions d-flex gap-2"><a class="btn wo-btn-line" href="{{ route('users.index') }}">Cancel</a><button class="btn wo-btn-primary" form="user-form">{{ $editing ? 'Save user' : 'Add user' }}</button></div>
  </div>
  <form method="post" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" id="user-form" class="wo-form">
    @csrf @if($editing) @method('PUT') @endif
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>1</span>Login</h2>
    @error('role')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="row g-3">
      <x-field name="name" label="Name" :value="$user->name" required col="col-md-4" />
      <x-field name="email" label="E-mail (used to sign in)" type="email" :value="$user->email" required col="col-md-4" />
      <x-field name="phone" label="Mobile" :value="$user->phone" col="col-md-4" />
      <div class="col-md-4">
        <label class="form-label" for="f-role">Role</label>
        <select id="f-role" name="role" class="form-select" onchange="document.getElementById('pi-projects').hidden = this.value !== 'pi'">
          @foreach(\App\Models\User::ROLES as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <div class="form-text">Admin: everything. Accounts: masters and bills. Operator: attendance. PI: only their own projects.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="f-agency">Works for</label>
        @if(auth()->user()->agency_id)
          <input type="text" class="form-control" value="{{ auth()->user()->agency?->name }}" disabled id="f-agency">
          <div class="form-text">Logins you create work for your agency only.</div>
        @else
          <select id="f-agency" name="agency_id" class="form-select @error('agency_id') is-invalid @enderror">
            @if($canPickBoth)<option value="" @selected(old('agency_id', $user->agency_id) === null)>Both agencies</option>@endif
            @foreach($agencies as $a)<option value="{{ $a->id }}" @selected((string) old('agency_id', $user->agency_id) === (string) $a->id)>{{ $a->name }} ({{ $a->code }})</option>@endforeach
          </select>
          @error('agency_id')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">The login sees only this agency's data.{{ $canPickBoth ? ' "Both agencies" is for staff who work for both.' : '' }}</div>@enderror
        @endif
      </div>
      <div class="col-md-4">
        <label class="form-label" for="f-password">{{ $editing ? 'New temporary password' : 'Temporary password' }}</label>
        <input id="f-password" type="text" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" @required(! $editing)>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">At least 8 characters with letters and numbers. {{ $editing ? 'Leave blank to keep the current password.' : '' }} The user must change it at first sign-in.</div>@enderror
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="f-active" name="active" value="1" @checked(old('active', $user->active ?? true))>
          <label class="form-check-label" for="f-active">Active (can sign in)</label></div>
      </div>
    </div>

    <div id="pi-projects" class="mt-4" @if(old('role', $user->role) !== 'pi') hidden @endif>
      <h2 class="wo-sec mt-3"><span>2</span>Projects this PI is in charge of</h2>
      <p class="small text-secondary mb-2">The PI sees only these projects: their staff (no bank, Aadhaar, PAN, phone or salary), attendance and sub-bills.</p>
      @error('projects')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
      <div class="row g-2">
        @foreach($projects as $p)
          <div class="col-md-6 col-xl-4"><div class="form-check border rounded p-2 ps-4">
            <input class="form-check-input" type="checkbox" name="projects[]" value="{{ $p->id }}" id="p-{{ $p->id }}" @checked(in_array($p->id, $chosen))>
            <label class="form-check-label" for="p-{{ $p->id }}"><b>{{ $p->name }}</b>@if($p->pi_name) · {{ $p->pi_name }}@endif<br>
              <span class="small text-secondary">{{ $p->contract->agency->code }} · {{ $p->contract->client->name }} · {{ $p->contract->contract_no }}</span></label>
          </div></div>
        @endforeach
      </div>
    </div>
    @if($editing && auth()->user()->isDeveloper() && ! $user->is_super_admin)
    <div class="alert alert-warning small mt-4 mb-0">
      <div class="form-check"><input class="form-check-input" type="checkbox" name="make_super_admin" value="1" id="f-super" @checked(old('make_super_admin'))>
        <label class="form-check-label" for="f-super"><b>Make this login a Super Admin (owner)</b>: approves or returns attendance, bills and salary payouts of both agencies,
          sees the Activity log and can delete unused records. Only for an owner. Needs role Admin and "Both agencies". It cannot be undone from the app.</label></div>
    </div>
    @endif
    </div>
    <div class="wo-savebar"><a class="btn wo-btn-line" href="{{ route('users.index') }}">Cancel</a><button class="btn wo-btn-primary">{{ $editing ? 'Save user' : 'Add user' }}</button></div>
  </form>
  </div>
</x-layouts.app>
