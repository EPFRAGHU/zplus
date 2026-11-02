<x-layouts.app :title="$contract->bid_no" :crumb="'Masters / <a href=\''.route('contracts.index').'\'>GeM Contracts</a>'">
  <x-slot:actions>@can('manage-masters')<a class="btn btn-outline-secondary" href="{{ route('contracts.edit', $contract) }}"><i class="bi bi-pencil me-1"></i>Edit contract</a>@endcan</x-slot:actions>
  <div class="z-card p-3 mb-3">
    <div class="row g-3 small">
      <div class="col-md-3"><div class="text-secondary">Agency</div><div class="fw-semibold">{{ $contract->agency->name }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Client</div><div class="fw-semibold">{{ $contract->client->name }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Contract no.</div><div class="mono">{{ $contract->contract_no }}</div></div>
      <div class="col-md-3"><div class="text-secondary">Status</div><x-status :value="$contract->status" /></div>
      <div class="col-md-3"><div class="text-secondary">Billing</div><div>{{ $contract->isDaily() ? 'Daily rate × paid days' : 'Monthly rate × paid days ÷ days in month' }}</div></div>
      <div class="col-md-9"><div class="text-secondary">Bill calculation</div>
        <div>Wages + employer EPF {{ (float) $contract->epf_percent }}% (on wages up to ₹15,000) and ESIC {{ (float) $contract->esic_percent }}% for staff with them switched on, then
          service charge {{ (float) $contract->service_charge_percent }}% on the total before GST, then GST {{ (float) $contract->gst_percent }}% {{ $contract->inter_state ? '(IGST)' : '(CGST + SGST)' }}</div></div>
    </div>
  </div>

  @foreach ($contract->projects as $project)
    <div class="z-card mb-3">
      <div class="z-card-h">
        <h2>{{ $project->name }} <span class="mono text-secondary fw-normal">{{ $project->code }}</span></h2>
        <span class="small text-secondary">PI: {{ $project->pi_name ?? '—' }}</span>
        @can('manage-masters')<button class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="collapse" data-bs-target="#edit-p{{ $project->id }}">Edit project</button>@endcan
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
          <tr>
            <td class="fw-semibold">{{ $post->designation }}</td><td class="num">{{ $post->sanctioned_qty }}</td><td class="num">{{ inr($post->rate) }}</td>
            <td>@forelse($post->activeEmployees as $e)<a href="{{ route('employees.show', $e) }}" class="me-2">{{ $e->name }}</a>@empty<span class="text-secondary">—</span>@endforelse</td>
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
  <form method="post" action="{{ route('projects.store', $contract) }}" class="z-card p-3">
    @csrf
    <h2 class="h6 fw-bold">Add a project to this contract</h2>
    @include('contracts._project-fields', ['p' => null])
    <div class="text-end mt-2"><button class="btn btn-primary">Add project</button></div>
  </form>
  @endcan
</x-layouts.app>
