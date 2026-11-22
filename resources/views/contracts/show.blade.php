<x-layouts.app :title="'Work order '.$contract->contract_no" :crumb="'Masters / <a href=\''.route('contracts.index').'\'>Work Orders</a>'">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-outline-primary" href="#add-project"><i class="bi bi-plus-lg me-1"></i>Add project</a><a class="btn btn-outline-secondary" href="{{ route('contracts.edit', $contract) }}"><i class="bi bi-pencil me-1"></i>Edit work order</a>@endcan
    @can('show-delete')<x-delete-button :action="route('contracts.destroy', $contract)" :small="false" label="Delete work order" :blocked="\App\Support\SafeDelete::contract($contract)" :confirm="'Delete work order '.$contract->contract_no.'? It has no projects or bills.'" />@endcan</x-slot:actions>
  @error('move')<div class="alert alert-danger small">{{ $message }}</div>@enderror
  <div class="z-card p-3 mb-3">
    <div class="row g-3 small">
      <div class="col-md-3"><div class="text-secondary">Agency</div><div class="fw-semibold">{{ $contract->agency->name }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Client</div><div class="fw-semibold">{{ $contract->client->name }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Contract no.</div><div class="mono">{{ $contract->contract_no }}</div>
        <span class="badge {{ $contract->contract_type === 'offline' ? 'text-bg-secondary' : 'text-bg-info' }}">{{ $contract->typeLabel() }}</span></div>
      <div class="col-md-3"><div class="text-secondary">Status</div><x-status :value="$contract->status" /></div>
      <div class="col-md-3"><div class="text-secondary">GeM bid no.</div><div class="mono">{{ $contract->bid_no ?: '—' }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Contract date</div><div>{{ $contract->contract_date?->format('d.m.Y') ?? '—' }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Service period</div><div>{{ $contract->start_date?->format('d.m.Y') ?? '—' }} to {{ $contract->end_date?->format('d.m.Y') ?? '—' }}</div>
        @include('contracts._expiry', ['c' => $contract])</div>
      <div class="col-md-3"><div class="text-secondary">Contract value</div><div>{{ $contract->contract_value !== null ? inr($contract->contract_value) : '—' }}</div></div>
      <div class="col-md-6"><div class="text-secondary">Billing</div><div>{{ $contract->isDaily() ? 'Daily rate × paid days' : 'Monthly rate × paid days ÷ days in month' }}</div></div>
      <div class="col-12"><div class="text-secondary">Bill calculation</div>
        <div>Wages + employer EPF {{ (float) $contract->epf_percent }}% (on wages up to ₹15,000) and ESIC {{ (float) $contract->esic_percent }}% for staff with them switched on, then
          service charge {{ (float) $contract->service_charge_percent }}%{{ $contract->service_charge_includes_gst ? ' incl. GST (charged as amount × '.(float) $contract->service_charge_percent.'% ÷ '.(1 + (float) $contract->gst_percent / 100).')' : '' }} on the total before GST, then GST {{ (float) $contract->gst_percent }}% {{ $contract->inter_state ? '(IGST)' : '(CGST + SGST)' }}</div></div>
    </div>
    <div class="small text-secondary mt-2">These details are entered once and used for every project, sub-bill and tax invoice of this work order. Change them with <b>Edit work order</b>.</div>
  </div>

  {{-- Billing for a month: work order → sub-bills (one per project) → tax invoice --}}
  <div class="z-card mb-3">
    <div class="z-card-h flex-wrap gap-2">
      <h2>Sub-bills and tax invoice</h2>
      <form method="get" class="d-flex gap-2 align-items-center ms-auto"><x-month-picker :value="$month->format('Y-m')" /><button class="btn btn-sm btn-outline-secondary">Show</button></form>
    </div>
    <div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>Project</th><th>Project In-charge</th><th>Attendance {{ $month->format('M Y') }}</th><th>Sub-bill no.</th><th class="num">Bill amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse($contract->projects as $project)
        @php($sheet = $sheets[$project->id] ?? null)
        @php($bill = $sheet?->invoice)
        <tr>
          <td><span class="fw-semibold">{{ $project->name }}</span> <span class="mono text-secondary">({{ $project->code }})</span></td>
          <td class="small">{{ $project->incharge?->name ?? $project->pi_name ?? '—' }}</td>
          <td>@if($sheet)<a href="{{ route('attendance.show', $sheet) }}"><x-status :value="$sheet->status" /></a>@else<span class="text-secondary small">Not started</span>@endif</td>
          <td>@if($bill)<a class="mono" href="{{ route('invoices.show', $bill) }}">{{ $bill->invoice_no }}</a>@else<span class="text-secondary">—</span>@endif</td>
          <td class="num">{{ $bill ? inr($bill->rounded_total) : '' }}</td>
          <td>@if($bill)<x-status :value="$bill->status" />@endif</td>
          <td class="text-end text-nowrap">
            @if(! $sheet)
              <form method="post" action="{{ route('attendance.open') }}" class="d-inline">@csrf<input type="hidden" name="project_id" value="{{ $project->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><button class="btn btn-sm btn-outline-secondary">Start attendance</button></form>
            @elseif(! $sheet->isLocked())
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('attendance.show', $sheet) }}">Open attendance</a>
            @elseif(! $bill)
              @can('billing')<form method="post" action="{{ route('invoices.store') }}" class="d-inline">@csrf<input type="hidden" name="attendance_sheet_id" value="{{ $sheet->id }}"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add sub-bill</button></form>@endcan
            @else
              <a class="btn btn-sm btn-link" href="{{ route('invoices.pdf', $bill) }}" target="_blank">PDF</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-secondary py-3">Add a project below first.</td></tr>
      @endforelse
      </tbody>
      <tfoot>
        @foreach($taxInvoices as $t)
          <tr class="table-light"><td colspan="3" class="fw-semibold">Tax invoice {{ $month->format('M Y') }}</td><td><a class="mono fw-semibold" href="{{ route('tax-invoices.show', $t) }}">{{ $t->number }}</a></td>
            <td class="num fw-semibold">{{ inr($t->rounded_total) }}</td><td><x-status :value="$t->status" /></td><td class="text-end"><a class="btn btn-sm btn-link" href="{{ route('tax-invoices.pdf', $t) }}" target="_blank">PDF</a></td></tr>
        @endforeach
        @if($pending->isNotEmpty())
          <tr class="table-light"><td colspan="7">
            @can('billing')
            <form method="post" action="{{ route('tax-invoices.store') }}" class="d-flex flex-wrap gap-2 align-items-center">@csrf
              <input type="hidden" name="gem_contract_id" value="{{ $contract->id }}"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
              @foreach($pending as $b)<input type="hidden" name="sub_bills[]" value="{{ $b->id }}">@endforeach
              <span class="small">{{ $pending->count() }} {{ \Illuminate\Support\Str::plural('sub-bill', $pending->count()) }} ({{ $pending->map(fn ($b) => $b->project->code)->implode(', ') }}) not yet in a tax invoice, total {{ inr($pending->sum(fn ($b) => (float) $b->rounded_total)) }}.</span>
              <label class="small text-secondary ms-auto" for="ti-date">Date</label><input id="ti-date" type="date" name="invoice_date" class="form-control form-control-sm" style="max-width:150px" value="{{ now()->toDateString() }}" required>
              <button class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-ruled me-1"></i>Add tax invoice {{ $nextTaxNumber }}</button>
            </form>
            @endcan
          </td></tr>
        @elseif($taxInvoices->isEmpty())
          <tr><td colspan="7" class="small text-secondary">The tax invoice can be made once the month's sub-bills exist. It gets the number shared by the sub-bills, e.g. {{ $nextTaxNumber }}.</td></tr>
        @endif
      </tfoot>
    </table></div>
  </div>

  @include('contracts._papers')

  <h2 class="h6 fw-bold mt-4 mb-2">Projects and posts</h2>
  @can('manage-masters')
  <form method="post" action="{{ route('contracts.import', $contract) }}" enctype="multipart/form-data" class="z-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">@csrf
    <div class="me-2"><b class="small"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import posts from the GeM contract PDF</b>
      <div class="small text-secondary">Reads every post (designation, number, basic pay), the service period and the contract value. You check them before anything is saved.</div></div>
    <input type="file" name="contract_pdf" accept="application/pdf,.pdf" required class="form-control form-control-sm @error('contract_pdf') is-invalid @enderror" style="max-width:320px" aria-label="GeM contract PDF">
    <button class="btn btn-sm btn-outline-primary">Read the PDF</button>
    @error('contract_pdf')<div class="small text-danger w-100">{{ $message }}</div>@enderror
  </form>
  @endcan
  @foreach ($contract->projects as $project)
    <div class="z-card mb-3">
      <div class="z-card-h">
        <h2>{{ $project->name }} <span class="mono text-secondary fw-normal">{{ $project->code }}</span></h2>
        <span class="small text-secondary">PI: {{ $project->incharge?->label() ?? $project->pi_name ?? '—' }}@if($project->incharge?->mobile) · {{ $project->incharge->mobile }}@endif</span>
        @can('manage-masters')<button class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="collapse" data-bs-target="#edit-p{{ $project->id }}">Edit project</button>@endcan
        @can('move-projects')@if($otherContracts->isNotEmpty())
          <span class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-arrow-left-right me-1"></i>Move to another work order</button>
            <form method="post" action="{{ route('projects.move', $project) }}" class="dropdown-menu dropdown-menu-end p-3" style="width:340px"
              data-confirm="Move project {{ $project->code }} with its posts, staff and attendance to the chosen work order?" onsubmit="return confirm(this.dataset.confirm)">@csrf
              <label class="form-label small fw-semibold mb-1" for="mv-{{ $project->id }}">Move {{ $project->code }} to work order</label>
              <select id="mv-{{ $project->id }}" name="gem_contract_id" class="form-select form-select-sm mb-2" required><option value="">Choose work order</option>
                @foreach($otherContracts as $oc)<option value="{{ $oc->id }}">{{ $oc->contract_no }}{{ $oc->bid_no ? ' · '.$oc->bid_no : '' }}</option>@endforeach</select>
              <div class="small text-secondary mb-2">Posts, staff, attendance and appointment letters move with it. Not possible once the project has sub-bills.</div>
              <button class="btn btn-sm btn-primary w-100">Move project</button>
            </form></span>
        @endif @endcan
        @can('show-delete')@unless(\App\Http\Controllers\ProjectController::cannotDelete($project))
          <form method="post" action="{{ route('projects.destroy', $project) }}" class="d-inline" data-confirm="Delete project {{ $project->name }} ({{ $project->code }})? It has no attendance, bills or staff. Its empty posts are removed too." onsubmit="return confirm(this.dataset.confirm)">@csrf @method('delete')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete project</button></form>
        @endunless @endcan
      </div>
      @can('manage-masters')
      <form method="post" action="{{ route('projects.update', $project) }}" class="collapse border-bottom p-3" id="edit-p{{ $project->id }}">
        @csrf @method('put')
        @include('contracts._project-fields', ['p' => $project])
        <div class="text-end mt-2"><button class="btn btn-sm btn-primary">Save project</button></div>
      </form>
      @endcan
      <div class="table-responsive"><table class="table">
        <thead><tr><th>Post</th><th class="num">Sanctioned</th><th class="num">{{ $contract->isDaily() ? 'Rate / day' : 'Rate / month' }}</th><th>Staff on this post</th><th>Vacancy</th><th></th></tr></thead>
        <tbody>
        @foreach ($project->posts as $post)
          @php($n = $post->activeEmployees->count())
          {{-- Over-filled posts in red; names that repeat on the post (capitals and Dr./Mr./Mrs./Ms. ignored) flagged as possible duplicates (owner, 22 Nov 2026). --}}
          @php($nameKey = fn ($name) => preg_replace('/\s+/', ' ', trim(preg_replace('/^(dr|mr|mrs|ms|miss|shri|smt)\.?\s+/i', '', mb_strtolower(trim($name))))))
          @php($repeats = $post->activeEmployees->countBy(fn ($e) => $nameKey($e->name))->filter(fn ($c) => $c > 1))
          <tr @class(['wo-over' => $n > (int) $post->sanctioned_qty, 'wo-full' => $n === (int) $post->sanctioned_qty && $n > 0, 'wo-vacant' => $n < (int) $post->sanctioned_qty])>
            <td class="fw-semibold">{{ $post->designation }}</td><td class="num">{{ $post->sanctioned_qty }}</td><td class="num">{{ inr($post->rate) }}</td>
            <td>@forelse($post->activeEmployees as $e)<a href="{{ route('employees.show', $e) }}" class="me-2">{{ $e->name }}</a>@if($repeats->has($nameKey($e->name)))<span class="badge text-bg-warning me-2" title="Another employee on this post has the same name: probably entered twice">same name?</span>@endif @empty<span class="text-secondary">—</span>@endforelse</td>
            <td>@if($n < $post->sanctioned_qty)<span class="badge text-bg-warning">{{ $post->sanctioned_qty - $n }} due</span>@elseif($n > $post->sanctioned_qty)<span class="badge text-bg-danger">{{ $n - $post->sanctioned_qty }} over</span>@else<span class="text-secondary small">Filled</span>@endif</td>
            <td class="text-end text-nowrap">@can('manage-masters')
              <button class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#edit-post{{ $post->id }}">Edit</button>
              <form method="post" action="{{ route('posts.destroy', $post) }}" class="d-inline" onsubmit="return confirm('Remove this post?')">@csrf @method('delete')<button class="btn btn-sm btn-link text-danger">Remove</button></form>
            @endcan</td>
          </tr>
          @can('manage-masters')
          <tr class="collapse" id="edit-post{{ $post->id }}"><td colspan="6">
            <form method="post" action="{{ route('posts.update', $post) }}" class="row g-2 align-items-end">@csrf @method('put')
              @include('contracts._post-fields', ['post' => $post])<div class="col-md-2 d-grid"><button class="btn btn-sm btn-primary">Save</button></div></form>
          </td></tr>
          @endcan
        @endforeach
        </tbody>
      </table></div>
      @can('manage-masters')
      <form method="post" action="{{ route('posts.store', $project) }}" class="row g-2 align-items-end p-3 border-top bg-light-subtle">@csrf
        @include('contracts._post-fields', ['post' => null])<div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Add post</button></div>
      </form>
      @endcan
    </div>
  @endforeach

  @can('manage-masters')
  <form method="post" action="{{ route('contracts.setup', $contract) }}" class="z-card p-3" id="add-project">
    @csrf
    <div class="small text-secondary mb-3">New projects use this work order's bid no. <span class="mono">{{ $contract->bid_no ?: '—' }}</span> and contract no. <span class="mono">{{ $contract->contract_no }}</span>; no need to enter them again.</div>
    @error('setup')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror
    @include('contracts._setup-builder', ['incharges' => $incharges->map->only(['id', 'agency_id', 'name'])->values(), 'fixedAgency' => $contract->agency_id, 'step' => '+'])
    <div class="text-end mt-3"><button class="btn btn-primary">Save projects, posts and staff</button></div>
  </form>
  @endcan
  <script>
    // "Edit" on the Work orders and Posts lists jumps here and opens that project's or post's edit form.
    window.addEventListener('load', function () {
      const target = /^#edit-(p|post)\d+$/.test(location.hash) && document.querySelector(location.hash);
      if (target && window.bootstrap) { bootstrap.Collapse.getOrCreateInstance(target).show(); target.scrollIntoView({block: 'center'}); }
    });
  </script>
</x-layouts.app>
