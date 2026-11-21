<x-layouts.app title="Sub-bills" crumb="Billing / <span class='text-danger'>Sub-bills</span>">
  <x-slot:actions>@can('billing')<a class="btn btn-outline-primary" href="{{ route('reports.post-wise', array_filter(['month' => substr((string) request('month'), 0, 7) ?: null])) }}"><i class="bi bi-diagram-3 me-1"></i>Post-wise sub-bills</a>@endcan</x-slot:actions>
  <div class="alert alert-light border small">One sub-bill per project per month. Combine a client's sub-bills into one GST <a href="{{ route('tax-invoices.index') }}">tax invoice</a> with <b>Make tax invoice</b> on the client's block.</div>
  @can('billing')
  @php($outdated = \App\Services\Billing\InvoiceGenerator::outdated()->get(['id', 'invoice_no'])
      ->concat(\App\Services\Billing\TaxInvoiceBuilder::outdated()->get(['id', 'number'])->map(fn ($t) => (object) ['invoice_no' => $t->number.' (tax invoice)'])))
  @if($outdated->isNotEmpty())
  <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
    <div><b>{{ $outdated->count() }} {{ \Illuminate\Support\Str::plural('bill', $outdated->count()) }} made with the old rules</b> (service charge 3.85% on the amount, or old-style number):
      <span class="mono small">{{ $outdated->pluck('invoice_no')->implode(', ') }}</span>. Draft, approved and submitted bills are worked out again (send the client the new PDF of any bill already submitted); paid bills are never changed.</div>
    <form method="post" action="{{ route('invoices.recalculate-outdated') }}" class="ms-auto" onsubmit="return confirm('Recalculate these bills with the current rules? Their status stays the same; every change is recorded in the Activity log.')">@csrf
      <button class="btn btn-sm btn-warning"><i class="bi bi-arrow-repeat me-1"></i>Recalculate all</button></form>
  </div>
  @endif
  @endcan
  @if($ready->isNotEmpty())
  <div class="z-card mb-3">
    <div class="z-card-h"><h2>Ready to bill</h2><span class="small text-secondary">Locked attendance without a bill</span></div>
    <ul class="list-group list-group-flush">
      @foreach($ready as $s)
        <li class="list-group-item d-flex align-items-center gap-2 flex-wrap"><span class="badge text-bg-light border">{{ $s->project->contract->agency->code }}</span><span>{{ $s->project->contract->client->name }}</span><span class="mono small text-secondary">{{ $s->project->contract->bid_no }}</span><b>{{ $s->project->name }}</b><span class="text-secondary">{{ $s->month->format('F Y') }}</span>
          @can('billing')<form method="post" action="{{ route('invoices.store') }}" class="ms-auto">@csrf<input type="hidden" name="attendance_sheet_id" value="{{ $s->id }}"><button class="btn btn-sm btn-primary">Make bill</button></form>@endcan</li>
      @endforeach
    </ul>
  </div>
  @endif
  @if($ready->isEmpty())
  <div class="z-card p-3 mb-3">
    <h2 class="h6 fw-bold">How a bill is made</h2>
    <ol class="mb-2 small">
      <li>Open <a href="{{ route('attendance.index') }}">Attendance</a>, choose the month and click <b>Start sheet</b> on the project.</li>
      <li>Check the daily codes, upload the certificate signed by the PI and click <b>Lock month</b>.</li>
      <li>The month then appears here under <b>Ready to bill</b>. Click <b>Make bill</b>.</li>
    </ol>
    @if($drafts->isNotEmpty())
      <div class="small text-secondary mb-1">Attendance started but not locked yet:</div>
      <ul class="list-group list-group-flush small">
        @foreach($drafts as $s)
          <li class="list-group-item d-flex gap-2 align-items-center px-0"><span class="badge text-bg-light border">{{ $s->project->contract->agency->code }}</span><span>{{ $s->project->contract->client->name }}</span><span class="mono small text-secondary">{{ $s->project->contract->bid_no }}</span><b>{{ $s->project->name }}</b><span class="text-secondary">{{ $s->month->format('F Y') }}</span>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="{{ route('attendance.show', $s) }}">Open and lock</a></li>
        @endforeach
      </ul>
    @else
      <div class="small text-secondary">No attendance is waiting to be locked.</div>
    @endif
  </div>
  @endif
  <form class="z-card p-3 mb-3 d-flex gap-2 flex-wrap align-items-end">
    <div><label class="form-label small text-secondary mb-1" for="m">Bill month</label><x-month-picker :value="request('month')" clearable /></div>
    <div><label class="form-label small text-secondary mb-1" for="f-agency">Agency</label>
      <select id="f-agency" name="agency" class="form-select"><option value="">All agencies</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected($agencyId === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-client">Client</label>
      <select id="f-client" name="client" class="form-select"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-contract">Work order</label>
      <select id="f-contract" name="contract" class="form-select"><option value="">All contracts</option>@foreach($contracts as $k)<option value="{{ $k->id }}" data-agency="{{ $k->agency_id }}" data-client="{{ $k->client_id }}" @selected(request('contract') == $k->id)>{{ $k->pickLabel() }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="f-project">Project</label>
      <select id="f-project" name="project" class="form-select"><option value="">All projects</option>@foreach($allProjects as $pp)<option value="{{ $pp->id }}" data-agency="{{ $pp->contract->agency_id }}" data-client="{{ $pp->contract->client_id }}" data-contract="{{ $pp->gem_contract_id }}" @selected(request('project') == $pp->id)>{{ $pp->label() }}</option>@endforeach</select></div>
    <div><label class="form-label small text-secondary mb-1" for="s">Status</label><select id="s" name="status" class="form-select"><option value="">Any</option>@foreach(\App\Models\Invoice::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-outline-secondary">Filter</button>
  </form>
  @can('billing')
  @php($zipAgency = $agencyId ?: ($agencies->count() === 1 ? $agencies->first()->id : null))
  @if(request('month') && $zipAgency)
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
      <a class="btn btn-outline-secondary" href="{{ route('month-documents.zip', ['agency' => $zipAgency, 'month' => substr(request('month'), 0, 7)]) }}"><i class="bi bi-file-zip me-1"></i>Download all PDFs of this month (ZIP)</a>
      <span class="small text-secondary">Sub-bills, covering letters, tax invoices{{ \Illuminate\Support\Facades\Gate::allows('view-bank-details') ? ' and the bank letter' : '' }} – sign them together with the DSC token, then upload each signed copy on its page.</span>
      @error('zip')<span class="small text-danger">{{ $message }}</span>@enderror
    </div>
  @elseif(request('month'))
    <div class="small text-secondary mb-3">Choose one agency to download this month's PDFs as a ZIP.</div>
  @endif
  @endcan
  <div class="z-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Bill no.</th><th>Agency</th><th>Client</th><th>Work order</th><th>Project</th><th>Month</th><th class="num">Before GST</th><th class="num">Bill amount</th><th>Status</th></tr></thead>
    <tbody>
    @php($block = false)
    @forelse($invoices as $i)
      @if($block === false || $block !== $i->agency_id)
        @php($clientBlock = false)
        @php($continued = $block === false && $carriedAgency !== false && $carriedAgency === $i->agency_id)
        @php($block = $i->agency_id)
        @php($t = $blockTotals[$i->agency_id] ?? null)
        <tr class="table-secondary"><td colspan="9" class="py-2">
          <b class="fs-6">{{ $i->contract->agency->name }}</b> <span class="badge text-bg-dark">{{ $i->contract->agency->code }}</span>
          @if($t)<span class="small text-secondary ms-2">{{ $t->bills }} {{ \Illuminate\Support\Str::plural('bill', $t->bills) }} · {{ inr($t->billed) }} billed · {{ inr($t->unpaid) }} awaiting payment{{ $continued ? ' · continued from the previous page' : '' }}</span>@endif
        </td></tr>
      @endif
      @if($clientBlock === false || $clientBlock !== $i->contract->client_id)
        @php($clientBlock = $i->contract->client_id)
        @php($ct = $clientTotals[$i->contract->client_id] ?? null)
        <x-client-block :client="$i->contract->client" colspan="9" :meta="$ct ? $ct->bills.' '.\Illuminate\Support\Str::plural('sub-bill', $ct->bills).' · '.inr($ct->billed).' billed · '.inr($ct->unpaid).' awaiting payment' : null">
          @can('billing')<a class="btn btn-sm btn-outline-primary" href="{{ route('tax-invoices.create', ['contract' => $i->gem_contract_id, 'month' => $i->month->format('Y-m')]) }}"><i class="bi bi-file-earmark-ruled"></i> Make tax invoice</a>@endcan
        </x-client-block>
      @endif
      <tr><td><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->invoice_no }}</a></td><td><span class="badge text-bg-light border" title="{{ $i->contract->agency->name }}">{{ $i->contract->agency->code }}</span></td><td>{{ $i->contract->client->name }}</td>
        <td><a class="mono text-decoration-none" href="{{ route('contracts.show', $i->contract) }}">{{ $i->contract->contract_no }}</a>@if($i->contract->bid_no)<div class="small text-secondary">Bid {{ $i->contract->bid_no }}</div>@endif</td>
        <td><span class="fw-semibold">{{ $i->project->name }}</span>@if($i->project->pi_name) <div class="small text-secondary">PI: {{ $i->project->pi_name }}</div> @endif</td><td>{{ $i->month->format('M Y') }}</td>
        <td class="num">{{ inr($i->taxable, true) }}</td><td class="num fw-semibold">{{ inr($i->rounded_total, true) }}</td><td><x-status :value="$i->status" />@if($i->tax_invoice_id) <div class="small"><a href="{{ route('tax-invoices.show', $i->tax_invoice_id) }}">In tax invoice</a></div>@endif</td></tr>
    @empty
      <tr><td colspan="9" class="text-center text-secondary py-4">No bills match. Bills appear here after you click <b>Make bill</b> on a locked month.</td></tr>
    @endforelse
    </tbody></table></div></div>
  <div class="mt-3">{{ $invoices->links() }}</div>
  <script>
    (function () {
      // Agency narrows clients; agency and client narrow contracts; contract narrows projects.
      const agency = document.getElementById('f-agency'), client = document.getElementById('f-client'),
            contract = document.getElementById('f-contract'), project = document.getElementById('f-project');
      function narrow(select, test) {
        [...select.options].forEach(o => { if (o.value) { o.hidden = o.disabled = !test(o); } });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
      }
      function update() {
        narrow(client, o => !agency.value || o.dataset.agency === agency.value);
        const fits = o => (!agency.value || o.dataset.agency === agency.value) && (!client.value || o.dataset.client === client.value);
        narrow(contract, fits);
        narrow(project, o => fits(o) && (!contract.value || o.dataset.contract === contract.value));
      }
      [agency, client, contract].forEach(el => el.addEventListener('change', update)); update();
    })();
  </script>
</x-layouts.app>
