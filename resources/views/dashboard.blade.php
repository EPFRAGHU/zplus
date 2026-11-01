<x-layouts.app title="Dashboard" crumb="Admin dashboard">
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-3">
    <x-kpi label="Clients" :value="$stats['clients']" />
    <x-kpi label="Active GeM contracts" :value="$stats['contracts']" color="#1f63c4" />
    <x-kpi label="Employees deployed" :value="$stats['employees']" color="#1f8a4c" />
    <x-kpi label="Posts vacant" :value="$stats['vacant']" :meta="'of '.$stats['sanctioned'].' sanctioned'" color="#b26b00" />
    <x-kpi label="Billed this year" :value="inr($stats['billedThisYear'])" color="#1d1a1c" />
    <x-kpi label="Awaiting payment" :value="inr($stats['unpaid'])" meta="Approved and submitted bills" color="#5b3fd1" />
  </div>
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="z-card h-100">
        <div class="z-card-h"><h2>Billed vs paid, {{ now()->year }}</h2><span class="small text-secondary">by bill month</span></div>
        <div class="p-3">
          @php($max = max(1, collect($monthly)->max('billed') ?? 1))
          <div class="d-flex align-items-end gap-2" style="height:200px">
            @foreach (range(1, 12) as $m)
              @php($b = $monthly[$m]['billed'] ?? 0) @php($p = $monthly[$m]['paid'] ?? 0)
              <div class="flex-fill d-flex flex-column align-items-center gap-1 h-100 justify-content-end" title="{{ \Carbon\Carbon::create(null, $m)->format('M') }}: billed {{ inr($b) }}, paid {{ inr($p) }}">
                <div class="d-flex align-items-end gap-1 w-100 justify-content-center" style="height:100%">
                  <div style="width:40%;height:{{ $b / $max * 100 }}%;background:#e23d4e;border-radius:3px 3px 0 0"></div>
                  <div style="width:40%;height:{{ $p / $max * 100 }}%;background:#1d1a1c;opacity:.75;border-radius:3px 3px 0 0"></div>
                </div>
                <small class="text-secondary">{{ \Carbon\Carbon::create(null, $m)->format('M') }}</small>
              </div>
            @endforeach
          </div>
          <div class="small text-secondary mt-2"><span class="d-inline-block me-1" style="width:10px;height:10px;background:#e23d4e"></span>Billed <span class="d-inline-block ms-3 me-1" style="width:10px;height:10px;background:#1d1a1c;opacity:.75"></span>Paid</div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="z-card h-100">
        <div class="z-card-h"><h2>{{ $lastMonth->format('F Y') }} billing</h2></div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between"><span>Projects</span><b>{{ $projectsTotal }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Attendance locked</span><b>{{ $projectsLocked }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Bills made</span><b>{{ $projectsBilled }}</b></li>
          <li class="list-group-item d-flex justify-content-between"><span>Draft bills to approve</span><b>{{ $drafts }}</b></li>
          <li class="list-group-item d-flex justify-content-between align-items-center"><span>Staff with missing bank details</span>
            <a class="badge text-bg-{{ $bankProblems ? 'danger' : 'success' }} text-decoration-none" href="{{ route('employees.index', ['bank' => 'problem']) }}">{{ $bankProblems }}</a></li>
        </ul>
        <div class="p-3 d-grid gap-2">
          <a class="btn btn-outline-secondary" href="{{ route('attendance.index', ['month' => $lastMonth->format('Y-m')]) }}"><i class="bi bi-calendar-check me-1"></i>Open attendance</a>
          <a class="btn btn-primary" href="{{ route('invoices.index') }}"><i class="bi bi-receipt me-1"></i>Make bills</a>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="z-card">
        <div class="z-card-h"><h2>Recent bills</h2><a class="ms-auto small" href="{{ route('invoices.index') }}">View all</a></div>
        <div class="table-responsive"><table class="table table-hover">
          <thead><tr><th>Bill no.</th><th>Project</th><th>Month</th><th class="num">Amount</th><th>Status</th></tr></thead>
          <tbody>
          @forelse ($recent as $i)
            <tr><td><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td><td>{{ $i->project->name }}</td><td>{{ $i->month->format('M Y') }}</td><td class="num">{{ inr($i->rounded_total) }}</td><td><x-status :value="$i->status" /></td></tr>
          @empty
            <tr><td colspan="5" class="text-center text-secondary py-4">No bills yet. Lock a month's attendance, then make its bill.</td></tr>
          @endforelse
          </tbody></table></div>
      </div>
    </div>
  </div>
</x-layouts.app>
