{{-- Reminders: wages by the 7th, EPF / ESIC by the 15th (for last month's wages), work orders ending, posts over sanctioned. --}}
@php($c = $compliance)
@php($dueText = function ($date) { $d = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false); return $d > 1 ? "in {$d} days" : ($d === 1 ? 'tomorrow' : ($d === 0 ? 'today' : abs($d).' '.\Illuminate\Support\Str::plural('day', abs($d)).' overdue')); })
@if($c['rows']->isNotEmpty() || $ending->isNotEmpty() || $overPosts->isNotEmpty())
<div class="z-card mb-3" id="reminders">
  <div class="z-card-h flex-wrap gap-2"><h2><i class="bi bi-alarm me-1"></i>Reminders</h2>
    @if($c['rows']->isNotEmpty())
      <span class="badge {{ $c['wagesPending'] ? (now()->gt($c['wagesDue']->copy()->endOfDay()) ? 'text-bg-danger' : 'text-bg-warning') : 'text-bg-success' }}">{{ $c['month']->format('M Y') }} wages: due {{ $c['wagesDue']->format('d M') }} ({{ $c['wagesPending'] ? $dueText($c['wagesDue']) : 'proof uploaded for all' }})</span>
      <span class="badge {{ $c['statutoryPending'] ? (now()->gt($c['statutoryDue']->copy()->endOfDay()) ? 'text-bg-danger' : 'text-bg-warning') : 'text-bg-success' }}">EPF / ESIC: due {{ $c['statutoryDue']->format('d M') }} ({{ $c['statutoryPending'] ? $dueText($c['statutoryDue']) : 'challans uploaded for all' }})</span>
    @endif
  </div>
  @if($c['rows']->isNotEmpty())
  <div class="table-responsive px-2"><table class="table table-sm mb-0">
    <thead><tr><th>Work order ({{ $c['month']->format('M Y') }} wages)</th><th>Salary paid (bank proof)</th><th>EPF challan</th><th>ESIC challan</th><th></th></tr></thead>
    <tbody>
    @foreach($c['rows'] as $r)
      <tr><td><span class="badge text-bg-dark">{{ $r['contract']->agency->code }}</span> <span class="mono">{{ $r['contract']->contract_no }}</span> <span class="small text-secondary">{{ $r['contract']->client->name }}</span></td>
        @foreach(['salary_proof', 'epf_challan', 'esi_challan'] as $k)
          <td>@if($r[$k] === null)<span class="small text-secondary">Not needed</span>@elseif($r[$k])<span class="badge text-bg-success">Done ✓</span>@else<span class="badge text-bg-warning">Not uploaded</span>@endif</td>
        @endforeach
        <td class="text-end"><a class="btn btn-sm btn-link" href="{{ route('contracts.show', ['contract' => $r['contract'], 'month' => $c['month']->format('Y-m')]) }}#papers">Upload papers</a></td></tr>
    @endforeach
    </tbody>
  </table></div>
  @endif
  @if($ending->isNotEmpty() || $overPosts->isNotEmpty())
  <ul class="list-group list-group-flush small">
    @foreach($ending as $k)
      <li class="list-group-item d-flex flex-wrap gap-2 align-items-center"><i class="bi bi-hourglass-split text-danger"></i>
        Work order <a class="mono" href="{{ route('contracts.show', $k) }}">{{ $k->contract_no }}</a> ({{ $k->agency->code }}, {{ $k->client->name }}) @include('contracts._expiry', ['c' => $k])
        <span class="text-secondary">– ask the client about extension / a new contract, or set it Closed.</span></li>
    @endforeach
    @foreach($overPosts as $p)
      <li class="list-group-item"><i class="bi bi-people text-danger me-1"></i>{{ $p->active_employees_count }} staff on post <b>{{ $p->designation }}</b> ({{ $p->project->code }}, work order <a class="mono" href="{{ route('contracts.show', $p->project->contract) }}">{{ $p->project->contract->contract_no }}</a>), sanctioned only {{ $p->sanctioned_qty }}.</li>
    @endforeach
  </ul>
  @endif
</div>
@endif
