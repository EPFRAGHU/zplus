<x-layouts.app title="Change password">
  <div class="z-card p-3" style="max-width:520px">
    @if($forced)<div class="alert alert-warning py-2 small">Welcome. Please choose your own password before continuing.</div>@endif
    <form method="post" action="{{ route('account.password.update') }}" class="d-grid gap-3">
      @csrf @method('PUT')
      <x-field name="current_password" label="{{ $forced ? 'Temporary password' : 'Current password' }}" type="password" required col="" />
      <x-field name="password" label="New password" type="password" required col="" hint="At least 8 characters with letters and numbers." />
      <x-field name="password_confirmation" label="New password again" type="password" required col="" />
      <button class="btn btn-primary">Change password</button>
    </form>
  </div>
</x-layouts.app>
