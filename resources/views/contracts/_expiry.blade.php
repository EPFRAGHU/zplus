{{-- Expiry badge of a work order ($c): ended / ends in N days (30 days or less). --}}
@if($c->expiry() === 'ended')
  <span class="badge text-bg-danger" title="Service end date {{ $c->end_date->format('d.m.Y') }}">Ended {{ $c->end_date->format('d.m.Y') }}</span>
@elseif($c->expiry() === 'ending')
  <span class="badge text-bg-warning" title="Service end date {{ $c->end_date->format('d.m.Y') }}">Ends in {{ $c->daysLeft() }} {{ \Illuminate\Support\Str::plural('day', $c->daysLeft()) }} ({{ $c->end_date->format('d.m.Y') }})</span>
@endif
