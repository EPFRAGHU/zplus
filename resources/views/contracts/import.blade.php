<x-layouts.app :title="'Import from GeM contract – '.$contract->contract_no" :crumb="'Masters / <a href=\''.route('contracts.index').'\'>Work Orders</a> / <a href=\''.route('contracts.show', $contract).'\'>'.e($contract->contract_no).'</a>'">
  <div class="d-flex flex-wrap gap-3 small mb-3">
    <span>Agency: <b>{{ $contract->agency->name }}</b></span><span>Client: <b>{{ $contract->client->name }}</b></span>
    <span>Work order: <span class="mono">{{ $contract->contract_no }}</span></span>
  </div>
  @php($check = $read['check'])
  <div class="alert {{ $check['matches'] ? 'alert-success' : 'alert-warning' }} small">
    @if($check['matches'])
      <i class="bi bi-check-circle me-1"></i><b>Read correctly.</b> The posts below, worked out with GeM's formula (basic × 1.18 + basic × service charge %) × months × number, come to
      {{ inr($check['worked_out']) }}, the same as the contract's printed total {{ inr($check['printed']) }}.
    @else
      <i class="bi bi-exclamation-triangle me-1"></i><b>Please check every row against the PDF.</b> Worked out from the rows: {{ inr($check['worked_out']) }};
      printed on the contract: {{ $check['printed'] !== null ? inr($check['printed']) : 'not found' }}.@if($check['missing']) Some numbers could not be read and are blank.@endif
    @endif
  </div>

  <form method="post" action="{{ route('contracts.import.store', $contract) }}">@csrf
    <div class="z-card p-3 mb-3">
      <h2 class="h6 fw-bold">Contract details found in the PDF</h2>
      <table class="table table-sm small mb-0" style="max-width:820px">
        <thead><tr><th></th><th>In the PDF</th><th>Saved on this work order</th><th>Fill in</th></tr></thead>
        <tbody>
        <tr><td>Contract no. · bid no.</td><td class="mono">{{ $read['contract_no'] ?? '—' }} · {{ $read['bid_no'] ?? '—' }}</td><td class="mono">{{ $contract->contract_no }} · {{ $contract->bid_no ?: '—' }}</td><td></td></tr>
        @foreach(['contract_date' => 'Contract date', 'start_date' => 'Service start date', 'end_date' => 'Service end date', 'contract_value' => 'Total contract value'] as $field => $label)
          @php($pdf = $read[$field]) @php($saved = $contract->{$field})
          <tr><td>{{ $label }}</td>
            <td>{{ $pdf === null ? '—' : ($field === 'contract_value' ? inr($pdf) : \Carbon\Carbon::parse($pdf)->format('d.m.Y')) }}</td>
            <td>{{ $saved === null ? '—' : ($field === 'contract_value' ? inr($saved) : $saved->format('d.m.Y')) }}</td>
            <td>@if($pdf !== null)<input class="form-check-input" type="checkbox" name="fill[]" value="{{ $field }}" aria-label="Fill in {{ $label }}" @checked($saved === null)>@endif</td></tr>
        @endforeach
        </tbody>
      </table>
      <div class="form-text">Ticked details are saved on the work order. Blank ones are ticked for you; a saved value is only replaced if you tick it.</div>
    </div>

    <div class="z-card mb-3">
      <div class="z-card-h flex-wrap gap-2"><h2>Posts in the contract ({{ count($read['posts']) }})</h2>
        <div class="ms-auto d-flex gap-2 align-items-center"><label class="small text-secondary" for="imp-project">Add the ticked posts to project</label>
          <select id="imp-project" name="project_id" class="form-select form-select-sm w-auto @error('project_id') is-invalid @enderror" required>
            <option value="">Choose project</option>
            @foreach($contract->projects as $p)<option value="{{ $p->id }}" @selected(old('project_id') == $p->id || $contract->projects->count() === 1)>{{ $p->name }} ({{ $p->code }})</option>@endforeach
          </select></div>
      </div>
      @error('project_id')<div class="alert alert-danger small m-3">{{ $message }}</div>@enderror
      @if($contract->projects->isEmpty())<div class="alert alert-warning small m-3">This work order has no project yet. <a href="{{ route('contracts.show', $contract) }}#add-project">Add the project first</a>, then import again.</div>@endif
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Add</th><th>Post / designation</th><th>GeM profile</th><th class="num">Number</th><th class="num">Basic pay / month (₹)</th><th class="num">Service charge</th><th>Already in a project?</th></tr></thead>
        <tbody>
        @foreach($read['posts'] as $i => $post)
          @php($there = $contract->projects->first(fn ($p) => $p->posts->contains(fn ($x) => $x->designation === $post['designation'] && (float) $x->rate === (float) $post['rate'])))
          <tr>
            <td><input class="form-check-input" type="checkbox" name="posts[{{ $i }}][add]" value="1" aria-label="Add this post" @checked(! $there)></td>
            <td style="min-width:300px"><input class="form-control form-control-sm" name="posts[{{ $i }}][designation]" value="{{ old("posts.$i.designation", $post['designation']) }}" maxlength="255"></td>
            <td class="small text-secondary">{{ $post['profile'] }}</td>
            <td><input class="form-control form-control-sm text-end" style="width:80px" type="number" min="0" name="posts[{{ $i }}][sanctioned_qty]" value="{{ old("posts.$i.sanctioned_qty", $post['qty']) }}"></td>
            <td><input class="form-control form-control-sm text-end" style="width:130px" type="number" step="0.01" min="0" name="posts[{{ $i }}][rate]" value="{{ old("posts.$i.rate", $post['rate']) }}"></td>
            <td class="num small">{{ $post['service_charge'] !== null ? $post['service_charge'].'%' : '—' }}@if($post['extras'] > 0)<div class="text-danger">+ ₹{{ $post['extras'] }} PF/ESI/other</div>@endif</td>
            <td class="small">@if($there)<span class="badge text-bg-secondary">In {{ $there->code }}</span>@endif</td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
    </div>
    <div class="small text-secondary mb-2">A post with the same designation and rate already in the chosen project is not added twice. You can still edit or remove posts on the work order page afterwards.</div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('contracts.show', $contract) }}">Cancel</a>
      <button class="btn btn-primary" @disabled($contract->projects->isEmpty())><i class="bi bi-download me-1"></i>Add ticked posts</button></div>
  </form>
</x-layouts.app>
