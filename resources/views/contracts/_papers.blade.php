{{-- Monthly papers checklist of the work order for $month, upload form and the GeM bill pack. --}}
@can('billing')
@php($due = $month->addMonthNoOverflow())
<div class="z-card mb-3" id="papers">
  <div class="z-card-h flex-wrap gap-2">
    <h2>Monthly papers, {{ $month->format('F Y') }}</h2>
    <span class="small text-secondary">GeM pays only with these: wages paid by {{ $due->format('7 M') }}, EPF / ESIC deposited by {{ $due->format('15 M') }}</span>
    <a class="btn btn-sm btn-primary ms-auto" href="{{ route('contracts.bill-pack', ['contract' => $contract, 'month' => $month->format('Y-m')]) }}"><i class="bi bi-file-earmark-zip me-1"></i>Download bill pack (ZIP)</a>
  </div>
  @error('pack')<div class="alert alert-warning small m-3 mb-0">{{ $message }}</div>@enderror
  <div class="table-responsive px-2"><table class="table table-sm mb-0">
    <thead><tr><th>Paper</th><th>Status</th><th>Files</th></tr></thead>
    <tbody>
    @foreach(\App\Models\MonthlyPaper::KINDS as $kind => $label)
      @php($files = $papers[$kind] ?? collect())
      @php($optional = $kind === 'other' || (array_key_exists($kind, $needs) && ! $needs[$kind]))
      @continue($kind === 'other' && $files->isEmpty())
      <tr>
        <td>{{ $label }}</td>
        <td>@if($files->isNotEmpty())<span class="badge text-bg-success">Uploaded ✓</span>
            @elseif($optional)<span class="badge text-bg-light border" title="No one on this work order has it switched on">Not needed</span>
            @else<span class="badge text-bg-warning">Missing</span>@endif</td>
        <td class="small">
          @foreach($files as $f)
            <span class="me-2 text-nowrap">
              @if(in_array($kind, \App\Models\MonthlyPaper::BANK_KINDS, true) && ! auth()->user()->can('view-bank-details'))<i class="bi bi-lock"></i> {{ $f->original_name }}
              @else<a href="{{ route('papers.show', $f) }}" target="_blank">{{ $f->original_name }}</a>@endif
              <span class="text-secondary">({{ $f->created_at->timezone('Asia/Kolkata')->format('d M') }}{{ $f->uploader ? ', '.$f->uploader->name : '' }})</span>
              @can('reset-month')<form method="post" action="{{ route('papers.destroy', $f) }}" class="d-inline" data-confirm="Remove {{ $f->original_name }}?" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('delete')<button class="btn btn-link btn-sm p-0 text-danger" title="Remove" aria-label="Remove {{ $f->original_name }}"><i class="bi bi-x-circle"></i></button></form>@endcan
            </span>
          @endforeach
        </td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <form method="post" action="{{ route('papers.store', $contract) }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center p-3 border-top bg-light-subtle">@csrf
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <select name="kind" class="form-select form-select-sm w-auto @error('kind') is-invalid @enderror" aria-label="Which paper" required>
      @foreach(\App\Models\MonthlyPaper::KINDS as $kind => $label)<option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ $label }}</option>@endforeach
    </select>
    <input type="file" name="paper[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" required class="form-control form-control-sm @error('paper') is-invalid @enderror @error('paper.*') is-invalid @enderror" style="max-width:320px" aria-label="Files">
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>Upload for {{ $month->format('M Y') }}</button>
    <span class="small text-secondary">PDF, photo or Excel, up to 10 MB each. The bill pack ZIP has the tax invoice, sub-bills, covering letters, PI certificates and these papers.</span>
    @foreach(['kind', 'paper', 'paper.*'] as $field)@error($field)<div class="small text-danger w-100">{{ $message }}</div>@enderror @endforeach
  </form>
</div>
@endcan
