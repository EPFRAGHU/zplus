@php($draft = $sheet->status === 'draft')
<x-layouts.app :title="($sheet->project->title ?: $sheet->project->name).' · '.$sheet->month->format('F Y')" :crumb="'<a href=\''.route('pi.home').'\'>My projects</a>'">
  <x-slot:actions>
    <a class="btn btn-outline-secondary" href="{{ route('pi.pdf', $sheet) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>{{ $draft ? 'Preview PDF' : 'Download PDF' }}</a>
  </x-slot:actions>

  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Status: <x-status :value="$sheet->status === 'locked' ? 'approved' : $sheet->status" /></span>
    <span>Client: <b>{{ $sheet->project->contract->client->name }}</b></span>
    <span>Staff supplied by: <b>{{ $sheet->project->contract->agency->name }}</b></span>
    @if($sheet->submitted_at)<span>Submitted: <b>{{ $sheet->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</b> by {{ $sheet->submitter?->name }}</span>@endif
  </div>

  @if($draft)
    <div class="alert alert-light border small mb-3">Mark each person's attendance and click <b>Save attendance</b>. You can come back and change it. When the month is complete, click <b>Submit</b> at the bottom; after that it can no longer be changed here.</div>
  @elseif($sheet->isSubmitted())
    <div class="alert alert-info small mb-3"><b>Submitted.</b> Download the PDF, sign it, and upload the signed copy below (or e-mail it to the agency). The agency will check and approve it.</div>
  @else
    <div class="alert alert-success small mb-3"><b>Approved by the agency</b>{{ $sheet->locked_at ? ' on '.$sheet->locked_at->timezone('Asia/Kolkata')->format('d M Y') : '' }}.@if($sheet->invoice) Sub-bill <b>{{ $sheet->invoice->invoice_no }}</b> is under <a href="{{ route('pi.bills') }}">Sub-bills</a>.@endif</div>
  @endif

  @include('attendance._grid', ['editable' => $draft, 'action' => route('pi.save', $sheet)])

  @if($draft)
    <form method="post" action="{{ route('pi.submit', $sheet) }}" class="z-card p-3 d-flex gap-2 align-items-center flex-wrap">@csrf
      <span class="small text-secondary">Save first. After submitting, the PDF is made from what is saved and the month can no longer be changed here.</span>
      <button class="btn btn-primary ms-auto" onclick="return confirm('Submit the attendance for {{ $sheet->month->format('F Y') }}? Make sure you have saved your changes.')"><i class="bi bi-send-check me-1"></i>Submit attendance</button>
    </form>
  @elseif($sheet->isSubmitted())
    <form method="post" action="{{ route('pi.signed', $sheet) }}" enctype="multipart/form-data" class="z-card p-3 d-flex gap-2 align-items-end flex-wrap">@csrf
      <div class="flex-grow-1"><label class="form-label small text-secondary mb-1" for="signed">Signed copy (PDF or photo, up to 10 MB){{ $sheet->certificate_path ? ' · already uploaded; choose a file only to replace it' : '' }}</label>
        <input id="signed" type="file" name="signed" accept=".pdf,.jpg,.jpeg,.png" required class="form-control @error('signed') is-invalid @enderror">
        @error('signed')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload signed copy</button>
      @if($sheet->certificate_path)<a class="btn btn-outline-secondary" href="{{ route('pi.signed.show', $sheet) }}" target="_blank">View uploaded copy</a>@endif
    </form>
  @endif
</x-layouts.app>
