{{-- DSC-signed copy box: latest upload, and a form to upload a newer one. $signed, $action, $hidden (extra fields), $changedAt. --}}
<div class="z-card p-3 mt-3">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <h2 class="h6 fw-bold mb-0"><i class="bi bi-patch-check me-1"></i>Signed copy (DSC)</h2>
    @if($signed)
      <span class="badge text-bg-success">Signed ✓</span>
      <a href="{{ route('signed-documents.show', $signed) }}" target="_blank" class="small">{{ $signed->original_name }}</a>
      <span class="small text-secondary">uploaded {{ $signed->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}{{ $signed->uploader ? ' by '.$signed->uploader->name : '' }}</span>
      @if(isset($changedAt) && $changedAt && $changedAt->gt($signed->created_at))
        <span class="badge text-bg-warning">Changed after signing – sign and upload again</span>
      @endif
    @else
      <span class="small text-secondary">Not uploaded yet. Download the PDF, sign it with the DSC token (Adobe Reader or JSignPdf) and upload the signed PDF here.</span>
    @endif
  </div>
  <form method="post" action="{{ $action }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center mt-2">@csrf
    @foreach(($hidden ?? []) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <input type="file" name="signed" accept="application/pdf,.pdf" required class="form-control form-control-sm @error('signed') is-invalid @enderror" style="max-width:340px" aria-label="Signed PDF">
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>{{ $signed ? 'Upload newer signed copy' : 'Upload signed copy' }}</button>
    @error('signed')<div class="small text-danger w-100">{{ $message }}</div>@enderror
  </form>
</div>
