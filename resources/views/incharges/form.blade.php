<x-layouts.app :title="$incharge->exists ? 'Edit '.$incharge->name : 'Add Project In-charge'" :crumb="'Masters / <a href=\''.route('incharges.index').'\'>Project In-charge</a>'">
  <form method="post" action="{{ $incharge->exists ? route('incharges.update', $incharge) : route('incharges.store') }}" class="z-card p-3">
    @csrf @if($incharge->exists) @method('put') @endif
    <div class="row g-3">
      <x-select name="agency_id" label="Agency" :options="$agencies->pluck('name', 'id')" :value="$incharge->agency_id" required placeholder="Choose agency" col="col-md-4" />
      <x-field name="name" label="Name of the Project In-charge" :value="$incharge->name" required col="col-md-4" hint="e.g. Dr. A. Kumar" />
      <x-field name="designation" label="Designation" :value="$incharge->designation" col="col-md-4" hint="e.g. Scientist-E" />
      <x-field name="mobile" label="Mobile" :value="$incharge->mobile" col="col-md-3" maxlength="10" />
      <x-field name="email" label="E-mail" type="email" :value="$incharge->email" col="col-md-5" />
      <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$incharge->status" required col="col-md-2" />
      @can('manage-users')
      <x-select name="user_id" label="PI portal login" :options="$piUsers->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->email])" :value="$incharge->user_id" placeholder="None" col="col-md-6"
        hint="If linked, this login sees exactly the projects ticked below in the PI portal. Create logins under Users." />
      @endcan
    </div>

    <h2 class="h6 fw-bold mt-4">Projects of this In-charge</h2>
    <p class="small text-secondary">Tick every project they are in charge of, under any work order. A project has one In-charge: ticking a project that has another In-charge moves it here.</p>
    @error('projects')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @php($ticked = collect(old('projects', $incharge->exists ? $incharge->projects()->pluck('id')->all() : []))->map(fn ($v) => (int) $v))
    @forelse($projects as $contractId => $list)
      @php($k = $list->first()->contract)
      <div class="border rounded p-2 mb-2" data-agency="{{ $k->agency_id }}">
        <div class="small fw-semibold mb-1">Work order <span class="mono">{{ $k->contract_no }}</span> <span class="text-secondary fw-normal">· {{ $k->client->name }}</span></div>
        <div class="d-flex flex-wrap gap-3">
          @foreach($list as $p)
            <div class="form-check"><input class="form-check-input" type="checkbox" name="projects[]" value="{{ $p->id }}" id="p{{ $p->id }}" @checked($ticked->contains($p->id))>
              <label class="form-check-label small" for="p{{ $p->id }}">{{ $p->name }} <span class="mono">({{ $p->code }})</span>
                @if($p->incharge && $p->incharge->id !== $incharge->id)<span class="text-secondary">· now {{ $p->incharge->name }}</span>@endif</label></div>
          @endforeach
        </div>
      </div>
    @empty
      <div class="text-secondary small">No projects yet. Add work orders and projects first.</div>
    @endforelse
    <div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-outline-secondary" href="{{ route('incharges.index') }}">Cancel</a><button class="btn btn-primary">Save</button></div>
  </form>
  @if($incharge->exists)
    @can('delete-masters')
      {{-- Delete from here too (owner, 23 Nov 2026); only a Project In-charge with no projects allotted can go. --}}
      @php($blocked = \App\Support\SafeDelete::incharge($incharge))
      <div class="z-card p-3 mt-3 d-flex align-items-center gap-3 flex-wrap" id="delete-incharge">
        <div class="me-auto"><b>Delete this Project In-charge</b>
          <div class="small text-secondary">{{ $blocked ?: 'No projects are allotted, so this Project In-charge can be deleted. This cannot be undone.' }}</div></div>
        <form method="post" action="{{ route('incharges.destroy', $incharge) }}" data-confirm="Delete Project In-charge {{ $incharge->name }}? This cannot be undone." onsubmit="return confirm(this.dataset.confirm)">
          @csrf @method('delete')
          <button class="btn btn-outline-danger" @disabled($blocked) @if($blocked) title="{{ $blocked }}" @endif><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
      </div>
    @endcan
  @endif
  <script>
    (() => {   // show only the chosen agency's work orders
      const agency = document.getElementById('f-agency_id');
      const update = () => document.querySelectorAll('[data-agency]').forEach(b => {
        const hide = !!agency.value && b.dataset.agency !== agency.value;
        b.hidden = hide; if (hide) b.querySelectorAll('input').forEach(i => { i.checked = false; });
      });
      agency.addEventListener('change', update); update();
    })();
  </script>
</x-layouts.app>
