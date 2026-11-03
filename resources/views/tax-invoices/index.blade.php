<x-layouts.app title="Tax Invoices" crumb="Billing / <span class='text-danger'>Tax Invoices</span>">
  <x-slot:actions>@can('billing')<a class="btn btn-primary" href="{{ route('tax-invoices.create') }}"><i class="bi bi-plus-lg me-1"></i>Make tax invoice</a>@endcan</x-slot:actions>
  <div class="alert alert-light border small">A tax invoice combines a client's sub-bills for one month (one sub-bill per project) into a single GST invoice.</div>
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap align-items-end">
    <div><label class="form-label small text-secondary mb-1" for="m">Month</label><x-month-picker :value="request('month')" clearable /></div>
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->label() }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="s">Status</label><select id="s" name="status" class="form-select"><option value="">Any</option>@foreach(\App\Models\TaxInvoice::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Filter</button>
  </form>
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Tax invoice no.</th><th>Month</th><th>Date</th><th class="num">Sub-bills</th><th class="num">Before GST</th><th class="num">GST</th><th class="num">Invoice amount</th><th>Status</th></tr></thead>
    <tbody>
    @php($agencyBlock = false) @php($clientBlock = false)
    @forelse($taxInvoices as $t)
      @if($agencyBlock !== $t->agency_id)
        @php($agencyBlock = $t->agency_id) @php($clientBlock = false)
        @php($inAgency = $taxInvoices->where('agency_id', $t->agency_id))
        <tr class="table-secondary"><td colspan="8" class="py-2"><b class="fs-6">{{ $t->agency->name }}</b> <span class="badge text-bg-dark">{{ $t->agency->code }}</span>
          <span class="small text-secondary ms-2">{{ $inAgency->count() }} {{ \Illuminate\Support\Str::plural('tax invoice', $inAgency->count()) }} · {{ inr($inAgency->sum(fn ($x) => (float) $x->rounded_total)) }}</span></td></tr>
      @endif
      @if($clientBlock !== $t->client_id)
        @php($clientBlock = $t->client_id)
        @php($inClient = $taxInvoices->where('client_id', $t->client_id))
        <x-client-block :client="$t->client" colspan="8" :meta="$inClient->count().' '.\Illuminate\Support\Str::plural('tax invoice', $inClient->count()).' · '.inr($inClient->sum(fn ($x) => (float) $x->rounded_total))">
          @can('billing')<a class="btn btn-sm btn-outline-primary" href="{{ route('tax-invoices.create', ['client' => $t->client_id]) }}"><i class="bi bi-plus-lg"></i> Tax invoice for this client</a>@endcan
        </x-client-block>
      @endif
      <tr>
        <td><a class="mono" href="{{ route('tax-invoices.show', $t) }}">{{ $t->number }}</a></td>
        <td>{{ $t->month->format('M Y') }}</td><td>{{ $t->invoice_date->format('d/m/Y') }}</td>
        <td class="num">{{ $t->sub_bills_count }}</td>
        <td class="num">{{ inr($t->taxable, true) }}</td>
        <td class="num">{{ inr((float) $t->cgst + (float) $t->sgst + (float) $t->igst, true) }}</td>
        <td class="num fw-semibold">{{ inr($t->rounded_total, true) }}</td>
        <td><x-status :value="$t->status" /></td>
      </tr>
    @empty
      <tr><td colspan="8" class="text-center text-secondary py-4">No tax invoices yet. Make the month's sub-bills first, then click <b>Make tax invoice</b>.</td></tr>
    @endforelse
    </tbody></table></div></div>
</x-layouts.app>
