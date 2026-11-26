@php($releases = \App\Support\Release::all())
@php($started = \App\Support\Release::started())
@php($typeBadge = ['major' => ['Milestone', 'text-bg-dark'], 'feature' => ['New feature', 'text-bg-primary'], 'fix' => ['Improvement', 'text-bg-light border']])
<x-layouts.app title="Release notes" crumb="Release notes" :quiet-title="true">
  <div class="wo">
  <div class="wo-head">
    <div class="wo-head-icon"><i class="bi bi-stars"></i></div>
    <div class="me-auto"><h1>What's new</h1>
      <p>Every change to the app, newest first, with its date and time in IST.</p></div>
    <div class="wo-actions d-flex gap-2 flex-wrap align-items-center">
    
    </div>
  </div>
  <div class="wo-kpis">
    <div class="wo-kpi k-green"><i class="bi bi-tag"></i><div><div class="l">Current version</div><div class="v"><span class="mono">v{{ $releases[0]['version'] }}</span></div></div></div>
    <div class="wo-kpi k-blue"><i class="bi bi-calendar-check"></i><div><div class="l">Released</div><div class="v"><span style="font-size:1.05rem">{{ $releases[0]['date']->format('d M Y, h:i A') }}</span></div></div></div>
    <div class="wo-kpi k-violet"><i class="bi bi-calendar3"></i><div><div class="l">Releases this month</div><div class="v">{{ collect($releases)->filter(fn ($r) => $r['date']->isSameMonth(now()))->count() }}</div></div></div>
    <div class="wo-kpi k-amber"><i class="bi bi-stars"></i><div><div class="l">New features</div><div class="v">{{ collect($releases)->where('type', 'feature')->count() }}</div></div></div>
    <div class="wo-kpi k-red"><i class="bi bi-collection"></i><div><div class="l">All releases</div><div class="v">{{ count($releases) }}</div></div></div>
  </div>

  <div class="wo-bar">
    <div class="wo-search" style="min-width:320px"><i class="bi bi-search"></i><input id="rel-q" class="form-control" placeholder="Search the notes, e.g. NEFT, payment, tax invoice" aria-label="Search the release notes"></div>
    <span class="small text-secondary">Started {{ $started->format('d M Y, h:i A') }} IST · MAJOR.MINOR.PATCH: milestone, new feature, improvement</span>
  </div>
  <div class="wo-card z-card">
    <div class="z-card-h"><h2>Version history</h2><span class="small text-secondary">Times in IST (India)</span></div>
    @foreach(collect($releases)->groupBy(fn ($r) => $r['date']->toDateString()) as $day => $items)
      <div class="px-3 pt-3 pb-1 small fw-semibold text-secondary text-uppercase" style="letter-spacing:.06em">{{ \Carbon\CarbonImmutable::parse($day)->format('l, d F Y') }}</div>
      @foreach($items as $r)
        @php([$label, $cls] = $typeBadge[$r['type']] ?? ['Update', 'text-bg-secondary'])
        <div class="d-flex gap-3 px-3 py-2 border-bottom" data-rel>
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
  <script>
    (function () {
      // Search the notes: hides the releases that do not mention the words typed (owner, 26 Nov 2026).
      const q = document.getElementById('rel-q');
      q.addEventListener('input', function () {
        const words = q.value.toLowerCase().split(/\s+/).filter(Boolean);
        document.querySelectorAll('[data-rel]').forEach(function (r) {
          const t = r.textContent.toLowerCase();
          r.classList.toggle('d-none', !words.every(w => t.includes(w)));
        });
      });
    })();
  </script>
  </div>
</x-layouts.app>
