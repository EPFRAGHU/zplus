<x-layouts.app title="PI Allotment" crumb="Masters / <span class='text-danger'>PI Allotment</span>">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-primary" href="{{ route('incharges.create', array_filter(['agency' => $agencyId])) }}"><i class="bi bi-plus-lg me-1"></i>Add Project In-charge</a>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap">
    <input class="form-control flex-grow-1 w-auto" name="q" value="{{ request('q') }}" placeholder="Search by name, mobile or e-mail" aria-label="Search">
    <select class="form-select w-auto" name="agency" aria-label="Agency"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId == $a->id)>{{ $a->name }}</option>@endforeach</select>
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    <span class="small text-secondary align-self-center">Each Project In-charge is entered once and mapped to their projects, under any work order. Projects, attendance certificates, bills and the PI login use these details.</span>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Project In-charge</th><th>Mobile</th><th>E-mail</th><th>Projects (work order)</th><th>PI portal login</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @php($block = null)
    @forelse($incharges as $i)
      @if($block !== $i->agency_id)
        @php($block = $i->agency_id)
        <tr class="table-secondary"><td colspan="7" class="py-2"><b class="fs-6">{{ $i->agency->name }}</b> <span class="badge text-bg-dark">{{ $i->agency->code }}</span>
          <span class="small text-secondary ms-2">{{ $incharges->where('agency_id', $i->agency_id)->count() }} Project In-charges</span></td></tr>
      @endif
      <tr>
        <td><span class="fw-semibold">{{ $i->name }}</span>@if($i->designation)<div class="small text-secondary">{{ $i->designation }}</div>@endif</td>
        <td class="mono">{{ $i->mobile ?? '—' }}</td>
        <td class="small">{{ $i->email ?? '—' }}</td>
        <td class="small">@forelse($i->projects as $p)<div><a href="{{ route('contracts.show', $p->gem_contract_id) }}">{{ $p->name }} <span class="mono">({{ $p->code }})</span></a> <span class="text-secondary mono">{{ $p->contract->contract_no }}</span></div>@empty<span class="text-secondary">No project allotted</span>@endforelse</td>
        <td class="small">{{ $i->user?->email ?? '—' }}</td>
        <td><x-status :value="$i->status" /></td>
        <td class="text-end">@can('manage-masters')<a class="btn btn-sm btn-outline-secondary" href="{{ route('incharges.edit', $i) }}">Edit / allot</a>@endcan</td>
      </tr>
    @empty
      <tr><td colspan="7" class="text-center text-secondary py-4">No Project In-charges yet. Click <b>Add Project In-charge</b>.</td></tr>
    @endforelse
    </tbody>
  </table></div></div>
  @if($unallotted->isNotEmpty())
    <div class="alert alert-warning small mt-3"><b>{{ $unallotted->count() }} {{ \Illuminate\Support\Str::plural('project', $unallotted->count()) }} without a Project In-charge:</b>
      {{ $unallotted->map(fn ($p) => $p->name.' ('.$p->code.', '.$p->contract->agency->code.')')->implode(', ') }}</div>
  @endif
</x-layouts.app>
