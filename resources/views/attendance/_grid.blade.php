{{-- Monthly attendance grid. Expects $sheet, $codes (code => label), $editable, $action (form URL). --}}
@php($days = $sheet->month->daysInMonth)
<form method="post" action="{{ $action }}">
  @csrf @method('put')
  <div class="z-card mb-3">
    <div class="z-card-h"><h2>Daily codes</h2>
      <div class="small text-secondary">@foreach($codes as $k => $v)<span class="me-2"><b class="mono">{{ $k }}</b> {{ $v }}</span>@endforeach</div></div>
    <div class="table-responsive"><table class="table table-sm att-grid mb-0">
      <thead><tr><th>Staff</th>
        @for($d = 1; $d <= $days; $d++)<th>{{ $d }}<div class="fw-normal text-secondary">{{ substr($sheet->month->setDay($d)->format('D'), 0, 2) }}</div></th>@endfor
        <th class="num">Paid days</th></tr></thead>
      <tbody>
      @forelse($sheet->entries->sortBy('employee.name') as $entry)
        <tr><td><div class="fw-semibold">{{ $entry->employee->name }}</div><div class="small text-secondary">{{ $entry->employee->post?->designation }}</div></td>
          @for($d = 1; $d <= $days; $d++)
            @php($c = $entry->codes[$d] ?? $entry->codes[(string) $d] ?? 'A')
            <td class="c-{{ $c }}">@if(! $editable)<span class="mono small">{{ $c }}</span>@else
              <select name="codes[{{ $entry->id }}][{{ $d }}]" class="form-select c-{{ $c }}" aria-label="{{ $entry->employee->name }}, day {{ $d }}" onchange="this.className='form-select c-'+this.value;this.parentNode.className='c-'+this.value">
                @foreach(array_keys($codes) as $k)<option @selected($k === $c)>{{ $k }}</option>@endforeach
              </select>@endif</td>
          @endfor
          <td class="num fw-bold">{{ rtrim(rtrim((string) $entry->paid_days, '0'), '.') }}</td></tr>
      @empty
        <tr><td colspan="{{ $days + 2 }}" class="text-center text-secondary py-4">No staff on this project yet.</td></tr>
      @endforelse
      </tbody></table></div>
    <div class="d-flex gap-2 justify-content-between align-items-center p-3 border-top flex-wrap">
      <span class="small text-secondary">Paid days count every code except A and L. HD counts as half a day. Days before joining or after exit start as A.</span>
      @if($editable)<button class="btn btn-outline-primary">Save attendance</button>@endif
    </div>
  </div>
</form>
