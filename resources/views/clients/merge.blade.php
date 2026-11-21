<x-layouts.app title="Merge into one client" crumb="Masters / <a href='{{ route('clients.index') }}'>Clients</a> / <span class='text-danger'>Merge</span>">
  <div class="alert alert-warning small">
    <b>Take a database backup first</b> and check it ends with <span class="mono">-- Dump completed</span>.
    For each agency, the work orders, employees and tax invoices of every other client move to the client you keep, which is renamed below;
    the emptied clients are then removed. Bill amounts and bill numbers do not change. Every move is recorded in the Activity log.
  </div>
  @forelse($rows as $r)
    @php($a = $r['agency'])
    <form method="post" action="{{ route('clients.merge.store') }}" class="z-card mb-3">
      @csrf
      <input type="hidden" name="agency_id" value="{{ $a->id }}">
      <div class="z-card-h"><h2>{{ $a->name }} <span class="badge text-bg-dark">{{ $a->code }}</span></h2>
        <span class="small text-secondary">{{ $r['clients']->count() }} {{ \Illuminate\Support\Str::plural('client', $r['clients']->count()) }}</span></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th style="width:9%">Keep</th><th>Client now</th>@foreach($labels as $label)<th class="num">{{ ucfirst($label) }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($r['clients'] as $row)
          <tr><td><input class="form-check-input" type="radio" name="target_id" value="{{ $row['client']->id }}" id="t-{{ $row['client']->id }}" @checked($r['suggested']?->id === $row['client']->id) required></td>
            <td><label for="t-{{ $row['client']->id }}">{{ $row['client']->name }}</label></td>
            @foreach($labels as $table => $label)<td class="num">{{ $row['counts'][$table] }}</td>@endforeach</tr>
        @endforeach
        </tbody></table></div>
      <div class="p-3 row g-2 align-items-end">
        <div class="col-md-7"><label class="form-label small text-secondary mb-1" for="n-{{ $a->id }}">Name of the client after the merge</label>
          <input class="form-control" id="n-{{ $a->id }}" name="name" value="{{ $name }}" required maxlength="255"></div>
        <div class="col-md-3"><label class="form-label small text-secondary mb-1" for="c-{{ $a->id }}">Type MERGE to confirm</label>
          <input class="form-control mono" id="c-{{ $a->id }}" name="confirm" autocomplete="off" required pattern="MERGE"></div>
        <div class="col-md-2"><button class="btn btn-danger w-100" @disabled($r['clients']->count() === 1 && $r['clients']->first()['client']->name === $name)>Merge</button></div>
        @if($r['clients']->count() === 1 && $r['clients']->first()['client']->name === $name)<div class="col-12 small text-success"><i class="bi bi-check-circle me-1"></i>Already one client with this name.</div>@endif
      </div>
    </form>
  @empty
    <div class="z-card p-4 text-center text-secondary">No clients yet.</div>
  @endforelse
</x-layouts.app>
