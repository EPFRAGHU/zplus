<x-layouts.app title="Project In-charge" crumb="Masters / <span class='text-danger'>Project In-charge</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('incharges.create', array_filter(['agency' => $agencyId])) }}"><i class="bi bi-plus-lg me-1"></i>Add Project In-charge</a>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap">
    <input class="form-control flex-grow-1 w-auto" name="q" value="{{ request('q') }}" placeholder="Search by name, mobile or e-mail" aria-label="Search">
    <select class="form-select w-auto" name="agency" aria-label="Agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId == $a->id)>{{ $a->name }}</option>@endforeach</select>
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    <span class="small text-secondary align-self-center">Each Project In-charge with their projects, the employees under each project, and the sub-bills of those projects (marked to them; a linked PI login sees the same sub-bills in the PI portal).</span>
  </form>

  @php($block = null)
  @forelse($incharges as $i)
    @if($block !== $i->agency_id)
      @php($block = $i->agency_id)
      <div class="d-flex align-items-center gap-2 bg-secondary-subtle rounded px-3 py-2 mb-2 mt-3">
        <b class="fs-6">{{ $i->agency->name }}</b> <span class="badge text-bg-dark">{{ $i->agency->code }}</span>
        <span class="small text-secondary ms-2">{{ $incharges->where('agency_id', $i->agency_id)->count() }} Project In-charges</span>
      </div>
    @endif
    <div class="z-card mb-3">
      <div class="z-card-h flex-wrap gap-2">
        <h2 class="mb-0"><i class="bi bi-person-badge me-1"></i>{{ $i->name }}@if($i->designation)<span class="fw-normal text-secondary">, {{ $i->designation }}</span>@endif</h2>
        <span class="small text-secondary">@if($i->mobile)<i class="bi bi-telephone"></i> {{ $i->mobile }}@endif @if($i->email) · <i class="bi bi-envelope"></i> {{ $i->email }}@endif @if($i->user) · PI login: {{ $i->user->email }}@endif</span>
        <span class="ms-auto d-flex gap-2 align-items-center"><x-status :value="$i->status" />
          @can('manage-masters')<a class="btn btn-sm btn-outline-secondary" href="{{ route('incharges.edit', $i) }}">Edit / allot projects</a>@endcan</span>
      </div>
      @forelse($i->projects as $p)
        @php($staff = $p->posts->flatMap->activeEmployees)
        <div class="px-3 py-2 border-top">
          <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
            <a class="fw-semibold" href="{{ route('contracts.show', $p->gem_contract_id) }}">{{ $p->name }} <span class="mono">({{ $p->code }})</span></a>
            <span class="small text-secondary">Work order <span class="mono">{{ $p->contract->contract_no }}</span> · {{ $staff->count() }} {{ \Illuminate\Support\Str::plural('employee', $staff->count()) }}</span>
          </div>
          <div class="row g-3">
            <div class="col-lg-6">
              <table class="table table-sm mb-0">
                <thead><tr><th>Emp ID</th><th>Employee</th><th>Post</th></tr></thead>
                <tbody>
                @forelse($p->posts as $post)
                  @foreach($post->activeEmployees as $e)
                    <tr><td class="mono small">{{ $e->emp_code }}</td><td><a href="{{ route('staff.index', ['employee' => $e->id]) }}">{{ $e->name }}</a></td><td class="small">{{ $post->designation }}</td></tr>
                  @endforeach
                @empty
                @endforelse
                @if($staff->isEmpty())<tr><td colspan="3" class="text-secondary small">No employees on this project.</td></tr>@endif
                </tbody>
              </table>
            </div>
            <div class="col-lg-6">
              <table class="table table-sm mb-0">
                <thead><tr><th>Sub-bill</th><th>Month</th><th class="num">Amount</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($p->invoices->take(6) as $b)
                  <tr><td><a class="mono small" href="{{ route('invoices.show', $b) }}">{{ $b->invoice_no }}</a></td><td class="small">{{ $b->month->format('M Y') }}</td>
                    <td class="num">{{ inr($b->rounded_total) }}</td><td><x-status :value="$b->status" /></td></tr>
                @empty
                  <tr><td colspan="4" class="text-secondary small">No sub-bills yet.</td></tr>
                @endforelse
                @if($p->invoices->count() > 6)<tr><td colspan="4" class="small"><a href="{{ route('invoices.index', ['project' => $p->id]) }}">All {{ $p->invoices->count() }} sub-bills</a></td></tr>@endif
                </tbody>
              </table>
            </div>
          </div>
        </div>
      @empty
        <div class="px-3 py-2 border-top text-secondary small">No project allotted yet. @can('manage-masters')Use <b>Edit / allot projects</b>.@endcan</div>
      @endforelse
    </div>
  @empty
    <div class="z-card p-4 text-center text-secondary">No Project In-charges yet. Click <b>Add Project In-charge</b>.</div>
  @endforelse

  @if($unallotted->isNotEmpty())
    <div class="alert alert-warning small mt-3"><b>{{ $unallotted->count() }} {{ \Illuminate\Support\Str::plural('project', $unallotted->count()) }} without a Project In-charge:</b>
      {{ $unallotted->map(fn ($p) => $p->name.' ('.$p->code.', '.$p->contract->agency->code.')')->implode(', ') }}</div>
  @endif
</x-layouts.app>
