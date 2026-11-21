<x-layouts.app title="Agencies" crumb="Masters / <span class='text-danger'>Agencies</span>">
  <x-slot:actions>@can('add-agency')<a class="btn btn-primary" href="{{ route('agencies.create') }}"><i class="bi bi-plus-lg me-1"></i>Add agency</a>@endcan</x-slot:actions>
  @php($line = fn ($v) => filled($v) ? $v : '—')
  @forelse ($agencies as $a)
    <div class="z-card mb-3" id="agency-{{ $a->id }}">
      <div class="d-flex align-items-center gap-2 flex-wrap px-3 py-2 rounded-top bg-body-secondary border-bottom">
        @if($a->logo_path)<img src="{{ route('agencies.logo', $a) }}" alt="" style="width:36px;height:36px;object-fit:contain">@endif
        <b class="fs-6">{{ $a->name }}</b> <span class="badge text-bg-dark">{{ $a->code }}</span>
        <span class="small text-secondary">{{ $a->contracts_count }} {{ \Illuminate\Support\Str::plural('work order', $a->contracts_count) }} ·
          <a href="{{ route('employees.index', ['agency' => $a->id]) }}">{{ $a->employees_count }} {{ \Illuminate\Support\Str::plural('employee', $a->employees_count) }}</a></span>
        @can('manage-masters')
          <div class="ms-auto d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="{{ route('agencies.edit', $a) }}"><i class="bi bi-pencil me-1"></i>Edit</a>
            @can('show-delete-bills')<x-delete-button :action="route('agencies.destroy', $a)" :blocked="\App\Support\SafeDelete::agency($a)" :confirm="'Delete agency '.$a->name.'? It has no clients, staff, bills or logins.'" />@endcan
          </div>
        @endcan
      </div>
      <div class="row g-0">
        <div class="col-lg-6 p-3">
          <dl class="row small mb-0">
            <dt class="col-sm-4 text-secondary fw-semibold">Address</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $line($a->address) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Phone</dt><dd class="col-sm-8">{{ $line($a->phones ?: $a->phone) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">E-mail</dt><dd class="col-sm-8">{{ $line($a->email) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Website</dt><dd class="col-sm-8">{{ $line($a->website) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">GSTIN</dt><dd class="col-sm-8 mono">{{ $line($a->gstin) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">PAN</dt><dd class="col-sm-8 mono">{{ $line($a->pan) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Registrations</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $line($a->registrations) }}</dd>
          </dl>
        </div>
        <div class="col-lg-6 p-3 border-start-lg">
          <dl class="row small mb-0">
            <dt class="col-sm-4 text-secondary fw-semibold">Bill no. prefix</dt><dd class="col-sm-8 mono">{{ $a->numberPrefix() }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Signatory</dt><dd class="col-sm-8">{{ $line(trim($a->signatory_name.($a->signatory_title ? ', '.$a->signatory_title : ''), ', ')) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Bank</dt><dd class="col-sm-8">{{ $line(trim($a->bank_name.($a->bank_branch ? ', '.$a->bank_branch : ''), ', ')) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Account no.</dt><dd class="col-sm-8 mono">{{ $a->bank_account_no ? 'XXXX'.substr((string) $a->bank_account_no, -4) : '—' }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">IFSC</dt><dd class="col-sm-8 mono">{{ $line($a->bank_ifsc) }}</dd>
            <dt class="col-sm-4 text-secondary fw-semibold">Letterhead</dt><dd class="col-sm-8">{{ ucfirst($a->letterhead_style ?: 'classic') }}</dd>
          </dl>
          @if(! $a->bank_account_no || ! $a->bank_ifsc)<div class="alert alert-warning small py-2 mt-2 mb-0">Bank details are incomplete: tax invoices and bank letters will show blanks. Use <b>Edit</b> to add them.</div>@endif
        </div>
      </div>
    </div>
  @empty
    <div class="z-card p-4 text-center text-secondary">No agencies yet.</div>
  @endforelse
</x-layouts.app>
