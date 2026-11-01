<x-layouts.app title="Posts" crumb="Masters / <span class='text-danger'>Posts</span>">
  <x-slot:actions>@can('manage-masters')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPostModal"><i class="bi bi-plus-lg me-1"></i>Add post</button>@endcan</x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-contract">GeM contract</label>
      <select id="f-contract" name="contract" class="form-select"><option value="">All contracts</option>@foreach($pickContracts as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected(request('contract') == $k->id)>{{ $k->bid_no }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-project">Project</label>
      <select id="f-project" name="project" class="form-select"><option value="">All projects</option>@foreach($pickProjects as $pp)<option value="{{ $pp->id }}" data-contract="{{ $pp->gem_contract_id }}" @selected(request('project') == $pp->id)>{{ $pp->name }}{{ $pp->pi_name ? ' · '.$pp->pi_name : '' }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Show</button>
    <span class="small text-secondary ms-md-2">Each post sits under a project, and each project under a GeM contract. Use <b>Add post</b> to enter one; you can create the contract and project in the same window.</span>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Agency</th><th>Client</th><th>GeM contract</th><th>Project</th><th>Designation</th><th class="num">Sanctioned</th><th class="num">Filled</th><th class="num">Rate</th><th></th></tr></thead>
    <tbody>
    @php($agencyBlock = null) @php($group = null)
    @forelse($posts as $p)
      @php($k = $p->project->contract)
      @if($agencyBlock !== $k->agency_id)
        @php($agencyBlock = $k->agency_id) @php($group = null)
        @php($inAgency = $posts->filter(fn ($x) => $x->project->contract->agency_id === $k->agency_id))
        <tr class="table-secondary"><td colspan="9" class="py-2">
          <b class="fs-6">{{ $k->agency->name }}</b> <span class="badge text-bg-dark">{{ $k->agency->code }}</span>
          <span class="small text-secondary ms-2">{{ $inAgency->count() }} {{ \Illuminate\Support\Str::plural('post', $inAgency->count()) }} · {{ $inAgency->sum('sanctioned_qty') }} sanctioned · {{ $inAgency->sum('active_employees_count') }} filled · {{ $inAgency->pluck('project.gem_contract_id')->unique()->count() }} {{ \Illuminate\Support\Str::plural('contract', $inAgency->pluck('project.gem_contract_id')->unique()->count()) }}</span>
        </td></tr>
      @endif
      @if($group !== $k->client_id)
        @php($group = $k->client_id)
        @php($inGroup = $posts->filter(fn ($x) => $x->project->contract->client_id === $k->client_id))
        <tr class="table-light"><td colspan="9" class="py-1 ps-4 small">
          Client: <b>{{ $k->client->name }}</b>
          <span class="text-secondary ms-2">{{ $inGroup->count() }} {{ \Illuminate\Support\Str::plural('post', $inGroup->count()) }} · {{ $inGroup->sum('sanctioned_qty') }} sanctioned · {{ $inGroup->sum('active_employees_count') }} filled</span>
        </td></tr>
      @endif
      <tr>
        <td class="small">{{ $k->agency->name }} <span class="text-secondary">({{ $k->agency->code }})</span></td>
        <td>{{ $k->client->name }}</td>
        <td><a class="mono text-decoration-none" href="{{ route('contracts.show', $k) }}">{{ $k->bid_no }}</a></td>
        <td><span class="fw-semibold">{{ $p->project->name }}</span>@if($p->project->pi_name) <div class="small text-secondary">PI: {{ $p->project->pi_name }}</div> @endif</td>
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
      // Agency narrows clients; agency and client narrow contracts; contract narrows projects.
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client'),
            contract = document.getElementById('f-contract'), project = document.getElementById('f-project');
      function narrow(select, test) {
        [...select.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !test(o); } });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
      }
      function update() {
        narrow(client, o => !agency.value || o.dataset.agency === agency.value);
        narrow(contract, o => (!agency.value || o.dataset.agency === agency.value) && (!client.value || o.dataset.client === client.value));
        const allowed = new Set([...contract.options].filter(o => o.value && !o.disabled).map(o => o.value));
        narrow(project, o => contract.value ? o.dataset.contract === contract.value : allowed.has(o.dataset.contract));
      }
      [agency, client, contract].forEach(el => el.addEventListener('change', update)); update();
    })();
  </script>
</x-layouts.app>
