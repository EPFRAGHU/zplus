{{-- "E-mail to client" box. $action, $agency, $to, $subject, $body, $preview (attachments), $history (SentEmail list). --}}
@php($configured = app(\App\Services\Mail\AgencyMailer::class)->isConfigured($agency))
<div class="z-card p-3 mt-3" id="email-client">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <h2 class="h6 fw-bold mb-0"><i class="bi bi-envelope-paper me-1"></i>E-mail to client</h2>
    @if($configured)
      <span class="small text-secondary">Sent from {{ $agency->name }}'s Gmail.</span>
      <button class="btn btn-sm btn-primary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#email-form" aria-expanded="{{ $errors->has('to') || $errors->has('cc') || $errors->has('subject') || $errors->has('message') || $errors->has('email') ? 'true' : 'false' }}"><i class="bi bi-send me-1"></i>E-mail to client</button>
    @else
      <span class="small text-secondary">Switched off for {{ $agency->name }}: its Gmail address and Gmail app password must first be added on the server (Coolify → Environment Variables), never in the app or in chat.</span>
    @endif
  </div>
  @error('email')<div class="alert alert-danger small mt-2 mb-0">{{ $message }}</div>@enderror

  @if($configured)
  <form method="post" action="{{ $action }}" class="collapse {{ $errors->hasAny(['to', 'cc', 'subject', 'message', 'email']) ? 'show' : '' }} mt-3" id="email-form"
        onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerText='Sending…';">@csrf
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label small mb-0" for="em-to">To</label>
        <input id="em-to" name="to" class="form-control form-control-sm @error('to') is-invalid @enderror" value="{{ old('to', $to) }}" placeholder="client@example.gov.in" required>
        @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-md-6"><label class="form-label small mb-0" for="em-cc">CC <span class="text-secondary">(optional; separate with commas)</span></label>
        <input id="em-cc" name="cc" class="form-control form-control-sm @error('cc') is-invalid @enderror" value="{{ old('cc') }}">
        @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-12"><label class="form-label small mb-0" for="em-subject">Subject</label>
        <input id="em-subject" name="subject" maxlength="200" class="form-control form-control-sm @error('subject') is-invalid @enderror" value="{{ old('subject', $subject) }}" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-12"><label class="form-label small mb-0" for="em-message">Message</label>
        <textarea id="em-message" name="message" rows="7" maxlength="5000" class="form-control form-control-sm @error('message') is-invalid @enderror" required>{{ old('message', $body) }}</textarea>
        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    <div class="small mt-2"><b>Attached:</b>
      @foreach($preview as $file)
        <span class="me-2"><i class="bi bi-file-earmark-pdf"></i> {{ $file['name'] }}
          @if($file['signed'])<span class="badge text-bg-success">DSC signed</span>@else<span class="badge text-bg-warning">not signed</span>@endif</span>
      @endforeach
    </div>
    @if(collect($preview)->contains(fn ($f) => ! $f['signed']))
      <div class="small text-secondary mt-1">"Not signed" files are made fresh from the app. To send the DSC-signed copy, upload it in the box above first.</div>
    @endif
    <button type="submit" class="btn btn-sm btn-primary mt-2"><i class="bi bi-send me-1"></i>Send e-mail</button>
  </form>
  @endif

  @if($history->isNotEmpty())
    <table class="table table-sm small mt-3 mb-0">
      <thead><tr><th>When</th><th>To</th><th>Files</th><th>By</th><th>Result</th></tr></thead>
      <tbody>
      @foreach($history as $h)
        <tr><td class="text-nowrap">{{ $h->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
          <td>{{ $h->to_addresses }}@if($h->cc_addresses)<br><span class="text-secondary">cc {{ $h->cc_addresses }}</span>@endif</td>
          <td>{{ count($h->attachments) }} PDF{{ count($h->attachments) === 1 ? '' : 's' }}@if(collect($h->attachments)->contains('signed', true)) <span class="text-success">(signed)</span>@endif</td>
          <td>{{ $h->sender?->name }}</td>
          <td>@if($h->status === 'sent')<span class="badge text-bg-success">Sent</span>@else<span class="badge text-bg-danger">Failed</span> <span class="text-secondary">{{ $h->error }}</span>@endif</td></tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>
