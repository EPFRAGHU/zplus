<x-layouts.app title="Backups" crumb="Setup / Backups" :quiet-title="true">
  {{-- Database backups reported by the backup scripts (owner, 26 Nov 2026). Developer only. --}}
  <div class="wo">
    <div class="wo-head">
      <div class="wo-head-icon"><i class="bi bi-database-check"></i></div>
      <div class="me-auto"><h1>Backups</h1>
        <p>Each database backup as reported by the backup script on the server (primary) and the off-server copy (secondary). Times in IST. Set-up: <span class="mono">docs/guides/BACKUPS.md</span>.</p></div>
    </div>
    @include('backups._card')
    <div class="wo-card"><div class="table-responsive"><table class="table align-middle wo-table m-0">
      <thead><tr><th>Finished (IST)</th><th>Where</th><th>Server</th><th>Result</th><th>File</th><th class="num">Size</th><th>Note</th></tr></thead>
      <tbody>
      @forelse($runs as $r)
        <tr><td class="text-nowrap">{{ $r->finished_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
          <td>{{ \App\Models\BackupRun::LOCATIONS[$r->location] ?? $r->location }}</td><td class="mono">{{ $r->server }}</td>
          <td>@if($r->status === 'ok')<span class="badge text-bg-success">OK</span>@else<span class="badge text-bg-danger">Failed</span>@endif</td>
          <td class="mono small">{{ $r->file ?? '—' }}</td><td class="num">{{ $r->sizeLabel() }}</td><td class="small">{{ $r->note }}</td></tr>
      @empty
        <tr><td colspan="7" class="text-center text-secondary py-4">No backup has been reported yet. Set up the backup script on the server (docs/guides/BACKUPS.md).</td></tr>
      @endforelse
      </tbody>
    </table></div></div>
  </div>
</x-layouts.app>
