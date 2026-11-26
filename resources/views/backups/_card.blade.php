{{-- Database backup status (owner, 26 Nov 2026): developer only. Green within 26 hours, amber older, red failed / none. --}}
@can('view-activity')
@php
  $bkColours = ['green' => ['#16a34a', '#f0fdf4', 'Backed up'], 'amber' => ['#d97706', '#fffbeb', 'Late'], 'red' => ['#dc2626', '#fef2f2', 'Not backed up']];
@endphp
<div class="wo-card p-3 mb-3 backup-card">
  <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-database-check fs-5 text-primary"></i><b>Database backup</b>
    <span class="small text-secondary">reported by the backup scripts · times in IST</span>
    <a class="btn btn-sm wo-btn-primary ms-auto" href="{{ route('backups.index') }}"><i class="bi bi-clock-history me-1"></i>Backups</a></div>
  <div class="row g-2">
    @foreach(\App\Models\BackupRun::LOCATIONS as $loc => $locLabel)
      @php($h = \App\Models\BackupRun::health($loc))
      @php($latest = \App\Models\BackupRun::latestFor($loc))
      @php($good = \App\Models\BackupRun::lastGood($loc))
      @php([$fg, $bg, $word] = $bkColours[$h])
      <div class="col-md-6"><div class="rounded-3 p-2 px-3 h-100" style="background:{{ $bg }};border:1px solid {{ $fg }}33">
        <div class="small text-secondary">{{ $locLabel }}</div>
        <div class="fw-bold" style="color:{{ $fg }}"><i class="bi {{ $h === 'green' ? 'bi-check-circle-fill' : ($h === 'amber' ? 'bi-exclamation-circle-fill' : 'bi-x-circle-fill') }} me-1"></i>{{ $word }}
          @if($good)· {{ $good->finished_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}@endif</div>
        <div class="small">
          @if($good)Server <b>{{ $good->server }}</b>@if($good->file) · {{ $good->file }}@endif · {{ $good->sizeLabel() }}
            · {{ $good->finished_at->diffForHumans() }}@else No backup reported yet. @endif
          @if($latest && $latest->status === 'failed')<div class="text-danger">Last run failed on {{ $latest->server }} at {{ $latest->finished_at->timezone('Asia/Kolkata')->format('d M, h:i A') }}{{ $latest->note ? ': '.$latest->note : '' }}</div>@endif
        </div>
      </div></div>
    @endforeach
  </div>
</div>
@endcan
