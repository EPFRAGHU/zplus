<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose company · ZPlus Manpower</title>
<meta name="description" content="Manpower records, attendance, GeM billing and payroll.">
@include('partials.favicon')
<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#1f1a1d;min-height:100vh;display:grid;place-items:center;padding:16px}
.pick{display:flex;gap:14px;align-items:center;width:100%;text-align:left;border:2px solid #e7e2e5;border-radius:12px;background:#fff;padding:14px 16px;transition:border-color .15s,background .15s}
.pick:hover,.pick:focus-visible{border-color:#e23d4e;background:#fff6f7;outline:none}
.pick.current{border-color:#e23d4e}
.pick .mark{width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-weight:700;color:#fff;flex:none}
</style>
</head><body>
<form method="post" action="{{ route('agency.choose.store') }}" class="bg-white rounded-3 p-4 shadow" style="width:min(460px,100%)">
  @csrf
  <div class="d-flex align-items-center gap-2 mb-3"><span class="rounded-2 text-white fw-bold d-grid" style="width:34px;height:34px;place-items:center;background:#e23d4e">Z+</span>
    <div><div class="fw-bold">Welcome, {{ auth()->user()->name }}</div><div class="small text-secondary">Which company do you want to work in?</div></div></div>
  @error('agency')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror
  <div class="d-grid gap-2">
    @foreach($agencies as $a)
      <button class="pick @if($current === $a->id) current @endif" name="agency" value="{{ $a->id }}">
        <span class="mark" style="background:{{ $a->code === 'SWH' ? '#8A2557' : '#2B4C7E' }}">{{ $a->code }}</span>
        <span><span class="d-block fw-bold">{{ $a->name }}</span>
          <span class="d-block small text-secondary">Only {{ $a->code }} staff, bills and approvals @if($a->gstin)· GSTIN {{ $a->gstin }}@endif</span></span>
        @if($current === $a->id)<span class="ms-auto badge text-bg-danger">Now</span>@endif
      </button>
    @endforeach
    <button class="pick" name="agency" value="both">
      <span class="mark" style="background:#1f1a1d"><i class="bi bi-buildings"></i></span>
      <span><span class="d-block fw-bold">Both agencies</span>
        <span class="d-block small text-secondary">Everything together, as before (needed for GeM import and adding an agency)</span></span>
    </button>
  </div>
  <div class="small text-secondary mt-3"><i class="bi bi-arrow-left-right me-1"></i>You can change this any time with <b>Change company</b> in the menu.</div>
</form>
</body></html>
