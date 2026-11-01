<x-layouts.app title="Posts" crumb="Masters / <span class='text-danger'>Posts</span>">
  <x-slot:actions>@can('manage-masters')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPostModal"><i class="bi bi-plus-lg me-1"></i>Add post</button>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Show</button>
    <span class="small text-secondary ms-md-2">Each post sits under a project, and each project under a GeM contract. Use <b>Add post</b> to enter one; you can create the contract and project in the same window.</span>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Agency</th><th>Client</th><th>GeM contract</th><th>Project · PI</th><th>Designation</th><th class="num">Sanctioned</th><th class="num">Filled</th><th class="num">Rate</th><th></th></tr></thead>
    <tbody>
    @forelse($posts as $p)
      @php($k = $p->project->contract)
      <tr>
        <td><span class="badge text-bg-light border" title="{{ $k->agency->name }}">{{ $k->agency->code }}</span></td>
        <td>{{ $k->client->name }}</td>
        <td class="mono">{{ $k->bid_no }}</td>
        <td>{{ $p->project->label() }}</td>
        <td class="fw-semibold">{{ $p->designation }}</td>
        <td class="num">{{ $p->sanctioned_qty }}</td>
        <td class="num">@if($p->active_employees_count < $p->sanctioned_qty)<span class="text-warning-emphasis">{{ $p->active_employees_count }}</span>@else{{ $p->active_employees_count }}@endif</td>
        <td class="num">{{ inr($p->rate) }}{{ $k->billing_type === 'daily' ? '/day' : '' }}</td>
        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('contracts.show', $k) }}">Open contract</a></td>
      </tr>
    @empty
      <tr><td colspan="9" class="text-center text-secondary py-4">No posts yet. Click <b>Add post</b> to enter the first one.</td></tr>
    @endforelse
    </tbody></table></div></div>
  @can('manage-masters')@include('posts._add-modal', ['onSaved' => 'reload'])@endcan
  <script>
    (function () {
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client');
      function update() {
        [...client.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !!agency.value && o.dataset.agency !== agency.value; } });
        if (client.selectedOptions[0] && client.selectedOptions[0].disabled) client.value = '';
      }
      agency.addEventListener('change', update); update();
    })();
  </script>
</x-layouts.app>
