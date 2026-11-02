@php($releases = \App\Support\Release::all())
@php($started = \App\Support\Release::started())
@php($typeBadge = ['major' => ['Milestone', 'text-bg-dark'], 'feature' => ['New feature', 'text-bg-primary'], 'fix' => ['Improvement', 'text-bg-light border']])
<x-layouts.app title="Release notes" crumb="Release notes">
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="z-card z-kpi"><div class="l">Current version</div><div class="v mono" style="font-size:1.45rem">v{{ $releases[0]['version'] }}</div><div class="small text-secondary">Released {{ $releases[0]['date']->format('d M Y, h:i A') }} IST</div></div></div>
    <div class="col-md-4"><div class="z-card z-kpi"><div class="l">Project started</div><div class="v">{{ $started->format('d M Y') }}</div><div class="small text-secondary">{{ $started->format('h:i A') }} IST</div>@php($days = (int) $started->startOfDay()->diffInDays(now($started->timezone)->startOfDay()) + 1)
      <div class="small text-secondary">{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }} of development</div></div></div>
    <div class="col-md-4"><div class="z-card z-kpi"><div class="l">Releases</div><div class="v">{{ count($releases) }}</div><div class="small text-secondary">MAJOR.MINOR.PATCH: milestone, new feature, improvement</div></div></div>
  </div>

  <div class="z-card">
    <div class="z-card-h"><h2>Version history</h2><span class="small text-secondary">Times in IST (India)</span></div>
    @foreach(collect($releases)->groupBy(fn ($r) => $r['date']->toDateString()) as $day => $items)
      <div class="px-3 pt-3 pb-1 small fw-semibold text-secondary text-uppercase" style="letter-spacing:.06em">{{ \Carbon\CarbonImmutable::parse($day)->format('l, d F Y') }}</div>
      @foreach($items as $r)
        @php([$label, $cls] = $typeBadge[$r['type']] ?? ['Update', 'text-bg-secondary'])
        <div class="d-flex gap-3 px-3 py-2 border-bottom">
          <div style="min-width:96px"><div class="small text-secondary mono mb-1">{{ $r['date']->format('h:i A') }}</div><span class="badge text-bg-{{ $loop->parent->first && $loop->first ? 'success' : 'light border' }} mono">v{{ $r['version'] }}</span></div>
          <div class="flex-grow-1">
            <div class="fw-semibold">{{ $r['title'] }} <span class="badge {{ $cls }} ms-1" style="font-size:.65rem">{{ $label }}</span>
              @if($loop->parent->first && $loop->first)<span class="badge text-bg-success ms-1" style="font-size:.65rem">Current</span>@endif</div>
            <ul class="mb-1 small text-secondary ps-3">
              @foreach($r['notes'] as $note)<li>{{ $note }}</li>@endforeach
            </ul>
          </div>
        </div>
      @endforeach
    @endforeach
  </div>
</x-layouts.app>
