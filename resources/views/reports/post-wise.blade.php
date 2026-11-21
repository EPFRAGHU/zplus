<x-layouts.app title="Post-wise sub-bills" crumb="Operations / <a href='{{ route('invoices.index') }}'>Sub-bills</a> / <span class='text-danger'>Post-wise</span>">
  <x-slot:actions><a class="btn btn-outline-secondary" target="_blank" href="{{ route('reports.post-wise.pdf', array_filter(['month' => $month->format('Y-m'), 'agency' => $agencyId, 'post' => $post])) }}"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a></x-slot:actions>
  <form class="z-card p-3 mb-3 d-flex gap-2 align-items-end flex-wrap">
    <div><label class="form-label small text-secondary mb-1" for="f-month">Month</label><input id="f-month" type="month" name="month" class="form-control" value="{{ $month->format('Y-m') }}"></div>
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">Both agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-post">Post</label>
      <select id="f-post" name="post" class="form-select"><option value="">All posts</option>@foreach($designations as $d)<option value="{{ $d }}" @selected($post && \App\Http\Controllers\PostController::postKey($post) === \App\Http\Controllers\PostController::postKey($d))>{{ $d }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Show</button>
    <span class="small text-secondary ms-md-2">For your own records: wages billed per post in the month's sub-bills. Not for submission.</span>
  </form>
  <div class="row row-cols-2 row-cols-md-3 g-3 mb-3">
    <x-kpi label="Posts" :value="$groups->count()" />
    <x-kpi label="Staff billed" :value="$staff" color="#1f63c4" />
    <x-kpi label="Wages billed" :value="'₹'.inr($total)" color="#1f8a4c" />
  </div>
  @forelse($groups as $g)
    <div class="z-card mb-3">
      <div class="z-card-h"><h2>{{ $g['post'] }}</h2><span class="small text-secondary">{{ $g['rows']->count() }} {{ \Illuminate\Support\Str::plural('project', $g['rows']->count()) }} · {{ $g['staff'] }} staff · {{ $g['days'] }} days</span>
        <span class="ms-auto fw-bold">₹{{ inr($g['amount'], true) }}</span></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Agency</th><th>Project</th><th>Sub-bill no.</th><th class="num">Rate</th><th class="num">Staff</th><th class="num">Days paid</th><th class="num">Wages (₹)</th></tr></thead>
        <tbody>@foreach($g['rows'] as $r)
          <tr><td><span class="badge text-bg-dark">{{ $r['agency']->code }}</span></td><td><span class="badge text-bg-primary mono">{{ $r['project']->code }}</span> {{ $r['project']->name }}</td>
            <td><a class="mono" href="{{ route('invoices.show', $r['bill']) }}">{{ $r['bill']->invoice_no }}</a></td><td class="num">{{ $r['rates'] }}</td>
            <td class="num">{{ $r['staff'] }}</td><td class="num">{{ $r['days'] }}</td><td class="num">{{ inr($r['amount'], true) }}</td></tr>
        @endforeach</tbody>
      </table></div>
    </div>
  @empty
    <div class="z-card p-4 text-center text-secondary">No sub-bills for {{ $month->format('F Y') }}{{ $post ? ' with this post' : '' }}.</div>
  @endforelse
</x-layouts.app>
