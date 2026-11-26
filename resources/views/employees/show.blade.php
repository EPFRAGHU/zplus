<x-layouts.app :title="$employee->name" :crumb="'Masters / <a href=\''.route('employees.index').'\'>Employees</a>'" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-person"></i></div>
    <div class="me-auto"><h1>{{ $employee->name }} <span class="mono small text-secondary">{{ $employee->emp_code }}</span> <x-status :value="$employee->status" /></h1>
      <p>Agency: <b class="text-body">{{ $employee->agency?->name ?? '—' }}</b> · Client: <b class="text-body">{{ $employee->client?->name ?? 'not assigned' }}</b>
        @if($employee->post)· work order <a class="mono" href="{{ route('contracts.show', $employee->post->project->contract) }}">{{ $employee->post->project->contract->contract_no }}</a>@endif</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    @can('manage-masters')
    @if($employee->post)<a class="btn btn-outline-secondary" href="{{ route('employees.appointment-letter', $employee) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>Appointment letter</a>@endif
    <a class="btn btn-primary" href="{{ route('employees.edit', $employee) }}"><i class="bi bi-pencil me-1"></i>Edit</a>
    @can('show-delete')<x-delete-button :action="route('employees.destroy', $employee)" :small="false" label="Delete employee" :blocked="\App\Support\SafeDelete::employee($employee)" :confirm="'Delete '.$employee->emp_code.' '.$employee->name.'? Only for a record entered by mistake.'" />@endcan
  @endcan
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-blue"><i class="bi bi-person-badge"></i><div><div class="l">Post</div><div class="v"><span style="font-size:.92rem;line-height:1.25;display:block">{{ $employee->post?->designation ?? '—' }}</span></div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-diagram-3"></i><div><div class="l">Project</div><div class="v"><span style="font-size:1rem">{{ $employee->post?->project->code ?? '—' }}</span></div></div></div>
    <div class="wo-kpi k-green"><i class="bi bi-currency-rupee"></i><div><div class="l">Monthly rate</div><div class="v" style="font-size:1.2rem">{{ inr($employee->rate()) }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-calendar-check"></i><div><div class="l">Joined</div><div class="v"><span style="font-size:1.1rem">{{ $employee->joining_date?->format('d M Y') ?? '—' }}</span></div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-bank"></i><div><div class="l">Bank details</div><div class="v" style="font-size:1.2rem">{!! $employee->ifsc && $employee->account_last4 ? '<span class="text-success">Complete</span>' : '<span class="text-danger">Missing</span>' !!}</div></div></div>
  </div>

  @include('partials.staff-approval')
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="z-card p-3 h-100">
        <div class="row g-3"><div class="col-md-6"><div class="small fw-semibold text-uppercase text-secondary mb-2" style="letter-spacing:.05em">Work</div>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-secondary fw-normal">Employee ID</dt><dd class="col-sm-8 mono">{{ $employee->emp_code }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Status</dt><dd class="col-sm-8"><x-status :value="$employee->status" /></dd>
          <dt class="col-sm-4 text-secondary fw-normal">Agency</dt><dd class="col-sm-8">{{ $employee->agency?->name ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Client</dt><dd class="col-sm-8">{{ $employee->client?->name ?? 'Not assigned to a client yet' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Post</dt><dd class="col-sm-8">{{ $employee->post?->designation ?? 'Not linked to a post' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Project</dt><dd class="col-sm-8">@if($employee->post)<a href="{{ route('contracts.show', $employee->post->project->contract) }}">{{ $employee->post->project->label() }}</a>@else — @endif</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Work order</dt><dd class="col-sm-8 mono">{{ $employee->post?->project->contract->contract_no ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Monthly rate</dt><dd class="col-sm-8">{{ inr($employee->rate()) }} @if($employee->rate_override)<span class="badge text-bg-light border">differs from post</span>@endif</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Joined</dt><dd class="col-sm-8">{{ $employee->joining_date?->format('d M Y') ?? '—' }}</dd>
        </dl></div>
        <div class="col-md-6"><div class="small fw-semibold text-uppercase text-secondary mb-2" style="letter-spacing:.05em">Bank &amp; personal</div>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-secondary fw-normal">Mobile</dt><dd class="col-sm-8">{{ $employee->mobile ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Bank</dt><dd class="col-sm-8">{{ $employee->bank_name ?? '—' }} <span class="mono">{{ $employee->maskedAccount() }}</span> <span class="mono">{{ $employee->ifsc }}</span>
            @if(! $employee->ifsc || ! $employee->account_last4)<span class="badge text-bg-danger">Incomplete</span>@endif</dd>
          <dt class="col-sm-4 text-secondary fw-normal">PAN</dt><dd class="col-sm-8 mono">{{ (auth()->user()->can('view-bank-details') ? $employee->pan : $employee->maskedPan()) ?? '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">Aadhaar</dt><dd class="col-sm-8 mono">{{ $employee->aadhaar_last4 ? 'XXXX XXXX '.$employee->aadhaar_last4 : '—' }}</dd>
          <dt class="col-sm-4 text-secondary fw-normal">EPF / ESIC / TDS</dt><dd class="col-sm-8">@foreach(['pf_applicable' => 'EPF', 'esic_applicable' => 'ESIC', 'tds_applicable' => 'TDS'] as $f => $n)<span class="badge rounded-pill me-1 {{ $employee->{$f} ? 'text-bg-success' : 'text-bg-light border text-secondary' }}">{{ $n }} {{ $employee->{$f} ? 'on' : 'off' }}</span>@endforeach</dd>
        </dl></div></div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="z-card h-100">
        <div class="z-card-h"><h2>Letters</h2></div>
        <ul class="list-group list-group-flush small">
          @forelse($letters as $l)<li class="list-group-item d-flex justify-content-between"><span>{{ str_replace('_', ' ', ucfirst($l->type)) }}</span><span class="mono">{{ $l->number }}</span></li>
          @empty<li class="list-group-item text-secondary">No letters yet.</li>@endforelse
        </ul>
      </div>
    </div>
    <div class="col-12">
      <div class="z-card">
        <div class="z-card-h"><h2>Documents</h2></div>
        <div class="table-responsive"><table class="table align-middle wo-table m-0">
          <thead><tr><th>Type</th><th>File</th><th>Expires</th><th>Uploaded</th><th></th></tr></thead>
          <tbody>
          @forelse($employee->documents as $d)
            <tr><td>{{ \App\Models\Employee::DOCUMENT_TYPES[$d->type] ?? $d->type }}</td>
              <td>@can('view-bank-details')<a href="{{ route('documents.show', $d) }}" target="_blank">{{ $d->original_name }}</a>@else{{ $d->original_name }}@endcan</td>
              <td>@if($d->expires_on)<span class="{{ $d->expires_on->isPast() ? 'text-danger fw-semibold' : '' }}">{{ $d->expires_on->format('d M Y') }}</span>@else — @endif</td>
              <td>{{ $d->created_at->format('d M Y') }}</td>
              <td class="text-end">@can('manage-masters')<form method="post" action="{{ route('documents.destroy', $d) }}" onsubmit="return confirm('Delete this document?')">@csrf @method('delete')<button class="btn btn-sm btn-link text-danger">Delete</button></form>@endcan</td></tr>
          @empty
            <tr><td colspan="5" class="text-secondary text-center py-3">No documents uploaded.</td></tr>
          @endforelse
          </tbody></table></div>
        @can('manage-masters')
        <form method="post" action="{{ route('employees.documents.store', $employee) }}" enctype="multipart/form-data" class="row g-2 p-3 border-top align-items-end">
          @csrf
          <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="doc-type">Type</label><select id="doc-type" name="type" class="form-select form-select-sm">@foreach(\App\Models\Employee::DOCUMENT_TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
          <div class="col-md-5"><label class="form-label small text-secondary mb-1" for="doc-file">File (PDF, JPG or PNG, up to 5 MB)</label><input id="doc-file" type="file" name="file" class="form-control form-control-sm" required accept=".pdf,.jpg,.jpeg,.png"></div>
          <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="doc-exp">Expires on</label><input id="doc-exp" type="date" name="expires_on" class="form-control form-control-sm"></div>
          <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary">Upload</button></div>
        </form>
        @endcan
      </div>
    </div>
  </div>
  </div>
</x-layouts.app>
