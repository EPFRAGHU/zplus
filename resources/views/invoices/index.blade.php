<x-layouts.app title="Bills" crumb="Billing / <span class='text-danger'>Bills</span>">
  @if($ready->isNotEmpty())
  <div class="z-card mb-3">
    <div class="z-card-h"><h2>Ready to bill</h2><span class="small text-secondary">Locked attendance without a bill</span></div>
    <ul class="list-group list-group-flush">
      @foreach($ready as $s)
        <li class="list-group-item d-flex align-items-center gap-2 flex-wrap"><b>{{ $s->project->name }}</b><span class="text-secondary">{{ $s->month->format('F Y') }}</span>
          @can('billing')<form method="post" action="{{ route('invoices.store') }}" class="ms-auto">@csrf<input type="hidden" name="attendance_sheet_id" value="{{ $s->id }}"><button class="btn btn-sm btn-primary">Make bill</button></form>@endcan</li>
      @endforeach
    </ul>
  </div>
  @endif
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap align-items-end">
    <div><label class="form-label small text-secondary mb-1" for="m">Bill month</label><input id="m" type="month" name="month" value="{{ request('month') }}" class="form-control"></div>
    <div><label class="form-label small text-secondary mb-1" for="s">Status</label><select id="s" name="status" class="form-select"><option value="">Any</option>@foreach(\App\Models\Invoice::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Filter</button>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Bill no.</th><th>Agency</th><th>Project</th><th>Month</th><th class="num">Before GST</th><th class="num">Bill amount</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($invoices as $i)
      <tr><td><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td><td>{{ $i->contract->agency->code }}</td><td>{{ $i->project->name }}</td><td>{{ $i->month->format('M Y') }}</td>
        <td class="num">{{ inr($i->taxable, true) }}</td><td class="num fw-semibold">{{ inr($i->rounded_total, true) }}</td><td><x-status :value="$i->status" /></td></tr>
    @empty
      <tr><td colspan="7" class="text-center text-secondary py-4">No bills yet.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $invoices->links() }}</div>
</x-layouts.app>
