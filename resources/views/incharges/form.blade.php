<x-layouts.app :title="$incharge->exists ? 'Edit '.$incharge->name : 'Add Project In-charge'" :crumb="'Masters / <a href=\''.route('incharges.index').'\'>Project In-charge</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-person-badge"></i></div>
    <div class="me-auto"><h1>{{ $incharge->exists ? 'Edit '.$incharge->name : 'Add Project In-charge' }}</h1>
      <p>@if($incharge->exists)Agency: <b class="text-body">{{ $incharge->agency?->name }}</b> · @endif The Project In-charge signs the monthly attendance certificate. Fields marked <span class="text-danger">*</span> are required.</p></div>
    <div class="wo-actions d-flex gap-2"><a class="btn wo-btn-line" href="{{ route('incharges.index') }}">Cancel</a><button class="btn wo-btn-primary" form="incharge-form">Save</button></div>
  </div>
  <form method="post" action="{{ $incharge->exists ? route('incharges.update', $incharge) : route('incharges.store') }}" id="incharge-form" class="wo-form">
    @csrf @if($incharge->exists) @method('put') @endif
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>1</span>Project In-charge</h2>
    <div class="row g-3">
      <x-select name="agency_id" label="Agency" :options="$agencies->pluck('name', 'id')" :value="$incharge->agency_id" required placeholder="Choose agency" col="col-md-4" />
      <x-field name="name" label="Name of the Project In-charge" :value="$incharge->name" required col="col-md-4" hint="e.g. Dr. A. Kumar" />
      <x-field name="designation" label="Designation" :value="$incharge->designation" col="col-md-4" hint="e.g. Scientist-E" />
      <x-field name="mobile" label="Mobile" :value="$incharge->mobile" col="col-md-3" maxlength="10" />
      <x-field name="email" label="E-mail" type="email" :value="$incharge->email" col="col-md-5" />
      <x-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$incharge->status" required col="col-md-2" />
      @if($incharge->exists)
        @can('delete-masters')
          {{-- Next to Status (owner, 23 Nov 2026). The button submits the separate delete form below (forms cannot be nested). --}}
          @php($blocked = \App\Support\SafeDelete::incharge($incharge))
          <div class="col-md-auto d-flex align-items-end" style="padding-bottom:1px">
            <span class="d-inline-block" tabindex="0" title="{{ $blocked ?: 'No projects are allotted, so this Project In-charge can be deleted.' }}">
              <button type="submit" form="delete-incharge" class="btn btn-outline-danger" @disabled($blocked)><i class="bi bi-trash me-1"></i>Delete</button></span>
          </div>
        @endcan
      @endif
      @can('manage-users')
      <x-select name="user_id" label="PI portal login" :options="$piUsers->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->email])" :value="$incharge->user_id" placeholder="None" col="col-md-6"
        hint="If linked, this login sees exactly the projects ticked below in the PI portal. Create logins under Users." />
      @endcan
    </div>

    {{-- Mapping-style allotment (owner, 23 Nov 2026): choose a work order, tick its projects; every ticked project shows as a chip. --}}
    </div>
    <div class="wo-card p-3 mb-3"><h2 class="wo-sec"><span>2</span>Projects of this In-charge</h2>
    <p class="small text-secondary">Choose a work order, then tick the projects this In-charge looks after. Repeat for other work orders. A project has one In-charge: ticking a project that has another In-charge moves it here.</p>
    @error('projects')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @php($ticked = collect(old('projects', $incharge->exists ? $incharge->projects()->pluck('id')->all() : []))->map(fn ($v) => (int) $v))
    @if($projects->isEmpty())
      <div class="text-secondary small">No projects yet. Add work orders and projects first.</div>
    @else
      <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
        <div class="small fw-semibold mb-2"><i class="bi bi-diagram-3 me-1"></i>Allotted projects <span class="badge text-bg-primary ms-1" id="pi-count">0</span></div>
        <div class="d-flex flex-wrap gap-2" id="pi-chips"></div>
        <div class="small text-secondary" id="pi-none">None yet. Choose a work order below and tick its projects.</div>
      </div>
      <div class="row g-3 align-items-start">
        <div class="col-md-5">
          <label class="form-label small fw-semibold text-secondary mb-1" for="pi-wo">Work order</label>
          <select class="form-select" id="pi-wo"><option value="">Choose work order</option>
            @foreach($projects as $contractId => $list)
              @php($k = $list->first()->contract)
              @php($mine = $list->filter(fn ($p) => $ticked->contains($p->id))->count())
              <option value="{{ $contractId }}" data-agency="{{ $k->agency_id }}">{{ $k->contract_no }}{{ $k->bid_no ? ' · '.$k->bid_no : '' }} ({{ $list->count() }} {{ \Illuminate\Support\Str::plural('project', $list->count()) }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-7">
          <div class="form-label small fw-semibold text-secondary mb-1">Projects of this work order</div>
          <div class="small text-secondary" id="pi-pick-hint">Choose a work order to see its projects.</div>
          @foreach($projects as $contractId => $list)
            @php($k = $list->first()->contract)
            <div class="pi-block border rounded-3 p-2" data-contract="{{ $contractId }}" data-agency="{{ $k->agency_id }}" hidden>
              @foreach($list as $p)
                <div class="form-check py-1 border-bottom">
                  <input class="form-check-input" type="checkbox" name="projects[]" value="{{ $p->id }}" id="p{{ $p->id }}" @checked($ticked->contains($p->id))
                    data-code="{{ $p->code }}" data-name="{{ $p->name }}" data-wo="{{ $k->contract_no }}">
                  <label class="form-check-label small" for="p{{ $p->id }}"><span class="badge text-bg-primary me-1">{{ $p->code }}</span>{{ $p->name }}
                    @if($p->incharge && $p->incharge->id !== $incharge->id)<span class="text-warning-emphasis">· now with {{ $p->incharge->name }}</span>@endif</label>
                </div>
              @endforeach
            </div>
          @endforeach
        </div>
      </div>
    @endif
    </div>
    <div class="wo-savebar"><a class="btn wo-btn-line" href="{{ route('incharges.index') }}">Cancel</a><button class="btn wo-btn-primary">Save</button></div>
  </form>
  @if($incharge->exists)
    @can('delete-masters')
      <form method="post" action="{{ route('incharges.destroy', $incharge) }}" id="delete-incharge" data-confirm="Delete Project In-charge {{ $incharge->name }}? Only this name card is removed; no project is deleted. This cannot be undone." onsubmit="return confirm(this.dataset.confirm)">
        @csrf @method('delete')
      </form>
    @endcan
  @endif
  <script>
    (() => {
      const agency = document.getElementById('f-agency_id'), wo = document.getElementById('pi-wo');
      if (!wo) return;
      const chips = document.getElementById('pi-chips'), boxes = [...document.querySelectorAll('input[name="projects[]"]')];
      const esc = t => t.replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
      function drawChips() {
        const on = boxes.filter(b => b.checked);
        chips.innerHTML = on.map(b => '<span class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 py-2 px-3 fw-normal">'
          + '<b class="text-primary">' + esc(b.dataset.code) + '</b> ' + esc(b.dataset.name) + ' <span class="text-secondary small">· ' + esc(b.dataset.wo) + '</span>'
          + '<button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.6rem" aria-label="Remove ' + esc(b.dataset.code) + '" data-off="' + b.value + '"></button></span>').join('');
        document.getElementById('pi-count').textContent = on.length;
        document.getElementById('pi-none').hidden = on.length > 0;
      }
      function showBlock() {
        document.querySelectorAll('.pi-block').forEach(b => { b.hidden = b.dataset.contract !== wo.value; });
        document.getElementById('pi-pick-hint').hidden = !!wo.value;
      }
      function byAgency() {   // only the chosen agency's work orders; projects of the other agency are unticked
        [...wo.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !!agency.value && o.dataset.agency !== agency.value; } });
        if (wo.selectedOptions[0] && wo.selectedOptions[0].disabled) wo.value = '';
        document.querySelectorAll('.pi-block').forEach(b => { if (agency.value && b.dataset.agency !== agency.value) b.querySelectorAll('input').forEach(i => { i.checked = false; }); });
        showBlock(); drawChips();
      }
      chips.addEventListener('click', e => { const id = e.target.dataset.off; if (id) { boxes.find(b => b.value === id).checked = false; drawChips(); } });
      boxes.forEach(b => b.addEventListener('change', drawChips));
      wo.addEventListener('change', showBlock);
      agency.addEventListener('change', byAgency);
      const first = boxes.find(b => b.checked);   // open the work order of the first allotted project
      if (first) wo.value = first.closest('.pi-block').dataset.contract;
      byAgency();
    })();
  </script>
  </div>
</x-layouts.app>
