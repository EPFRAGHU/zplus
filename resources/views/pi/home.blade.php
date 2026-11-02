@php($label = ['draft' => ['Being marked', 'secondary'], 'submitted' => ['Submitted · with the agency', 'info'], 'locked' => ['Approved', 'success']])
<x-layouts.app title="My projects">
  <div class="z-card p-3 mb-3 small">
    <b>How it works:</b> open the month → mark each person's attendance and <b>Save</b> (you can change it until you submit) →
    <b>Submit</b> → download the PDF, sign it and upload the signed copy here (or e-mail it to the agency) → the agency approves it and makes the sub-bill.
  </div>
  @forelse($projects as $p)
    @php($projectSheets = $sheets[$p->id] ?? collect())
    <div class="z-card mb-3">
      <div class="z-card-h">
        <h2>{{ $p->title ?: $p->name }}</h2>
        <span class="small text-secondary">{{ $p->contract->client->name }} · staff supplied by {{ $p->contract->agency->name }}</span>
      </div>
      <div class="p-3">
        <div class="d-flex flex-wrap gap-2 mb-3">
          @foreach($months as $m)
            @php($existing = $projectSheets->first(fn ($s) => $s->month->isSameMonth($m)))
            @if($existing)
              <a class="btn btn-outline-primary" href="{{ route('pi.sheet', $existing) }}"><i class="bi bi-calendar3 me-1"></i>{{ $m->format('F Y') }}</a>
            @else
              <form method="post" action="{{ route('pi.start', $p) }}">@csrf<input type="hidden" name="month" value="{{ $m->format('Y-m') }}">
                <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Mark {{ $m->format('F Y') }}</button></form>
            @endif
          @endforeach
        </div>
        @if($projectSheets->count())
          <table class="table table-sm mb-0">
            <thead><tr><th>Month</th><th>Status</th><th>Submitted</th><th>Signed copy</th><th>Sub-bill</th><th></th></tr></thead>
            <tbody>
            @foreach($projectSheets as $s)
              @php([$text, $cls] = $label[$s->status] ?? [$s->status, 'secondary'])
              <tr><td class="fw-semibold">{{ $s->month->format('F Y') }}</td><td><span class="badge text-bg-{{ $cls }}">{{ $text }}</span></td>
                <td class="small">{{ $s->submitted_at?->timezone('Asia/Kolkata')->format('d M Y') ?? '—' }}</td>
                <td>{!! $s->certificate_path ? '<i class="bi bi-check-circle-fill text-success"></i> Uploaded' : '<span class="text-secondary">—</span>' !!}</td>
                <td>{{ $s->invoice?->invoice_no ?? '—' }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('pi.sheet', $s) }}">Open</a></td></tr>
            @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </div>
  @empty
    <div class="alert alert-light border">No projects are linked to your login yet. Please contact the agency.</div>
  @endforelse
</x-layouts.app>
