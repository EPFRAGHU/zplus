<x-layouts.app title="Excel Import" crumb="Setup / <span class='text-danger'>Excel Import</span>" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
    <div class="me-auto"><h1>GeM Workbook Import</h1>
      <p>Read contracts, projects, posts and staff from the GEM PROJECT DETAILS workbook. You see every problem before anything is saved.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    
    </div>
  </div>


  <form method="post" action="{{ route('import.preview') }}" enctype="multipart/form-data" class="wo-card z-card p-3">
    @csrf
    <p class="mb-3">Upload your <b>GEM PROJECT DETAILS</b> workbook. The app reads the agency sheets (SWACHHA, ZPLUS) for contracts, projects and posts, and the PI sheets for staff salary and bank details. You see every problem it finds before anything is saved.</p>
    <div class="row g-3">
      <x-select name="client_id" label="These contracts are with" :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->label()])" required placeholder="Choose client" col="col-md-5"
        hint="Contracts on the other agency's sheet go to that agency's record of this client, created if needed." />
      <div class="col-md-7"><label class="form-label small fw-semibold text-secondary mb-1" for="wb">Workbook (.xlsx, up to 10 MB)</label>
        <input id="wb" type="file" name="workbook" class="form-control @error('workbook') is-invalid @enderror" accept=".xlsx,.xls" required>
        @error('workbook')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    @if($clients->isEmpty())<div class="alert alert-warning small mt-3 mb-0">Add the client first (for example ICMR-NIHR, Bhubaneswar) under <a href="{{ route('clients.create') }}">Clients</a>.</div>@endif
    <div class="text-end mt-3"><button class="btn wo-btn-primary"><i class="bi bi-search me-1"></i>Check workbook</button></div>
  </form>
  </div>
</x-layouts.app>
