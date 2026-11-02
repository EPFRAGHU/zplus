@php($icons = ['agencies' => 'bi-building', 'clients' => 'bi-bank', 'contracts' => 'bi-file-earmark-text', 'projects' => 'bi-kanban', 'posts' => 'bi-diagram-3', 'employees' => 'bi-people'])
<x-layouts.app title="Data Upload" crumb="Setup / Data Upload">
  <div class="z-card p-3 mb-3">
    <div class="fw-semibold mb-1">Load your existing data from Excel</div>
    <ol class="small mb-0 ps-3">
      <li><b>Download</b> a template (blank, or with the data already in the app to edit).</li>
      <li><b>Fill</b> one row per record in Excel. Columns marked * are required; hover over a heading for help. The <i>Instructions</i> sheet explains every column.</li>
      <li><b>Upload</b> it here. The app checks every row and shows what will be added, updated or has errors. <b>Nothing is saved until you click Save.</b></li>
    </ol>
    <div class="small text-secondary mt-2">Upload in order: each template needs the ones before it (a client needs its agency, a post needs its project, and so on). Records already in the app are matched by their code or number and updated; blank cells keep what is stored. Bank account and Aadhaar numbers are never included in downloads.</div>
  </div>

  <div class="row g-3">
    @foreach($sheets as $type => $sheet)
      <div class="col-md-6 col-xl-4">
        <div class="z-card h-100 d-flex flex-column">
          <div class="z-card-h">
            <span class="badge text-bg-dark">{{ $loop->iteration }}</span>
            <h2><i class="bi {{ $icons[$type] ?? 'bi-table' }} me-1"></i>{{ $sheet->title() }}</h2>
            <span class="ms-auto small text-secondary">{{ $counts[$type] }} in the app</span>
          </div>
          <div class="p-3 d-flex flex-column gap-2 flex-grow-1">
            <div class="small text-secondary flex-grow-1">{{ $sheet->description() }}</div>
            <div class="d-flex gap-2 flex-wrap">
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('master-import.template', $type) }}"><i class="bi bi-download me-1"></i>Blank template</a>
              @if($counts[$type])<a class="btn btn-sm btn-outline-secondary" href="{{ route('master-import.template', [$type, 'data' => 1]) }}"><i class="bi bi-download me-1"></i>With current data</a>@endif
            </div>
            <form method="post" action="{{ route('master-import.preview', $type) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-start">
              @csrf
              <div class="flex-grow-1">
                <label class="visually-hidden" for="file-{{ $type }}">Filled {{ $sheet->title() }} file</label>
                <input id="file-{{ $type }}" type="file" name="file" accept=".xlsx,.xls" required class="form-control form-control-sm @error('file_'.$type) is-invalid @enderror">
                @error('file_'.$type)<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload me-1"></i>Check file</button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  </div>
  @error('file')<div class="alert alert-danger mt-3">{{ $message }}</div>@enderror
</x-layouts.app>
