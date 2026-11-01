{{-- "Add new post" window. Include outside any other <form>. $onSaved: 'select' (employee form) or 'reload' (Posts page). --}}
<div class="modal fade" id="addPostModal" tabindex="-1" aria-labelledby="addPostTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form class="modal-content" id="qa-form" action="{{ route('posts.quick') }}" method="post" novalidate>
      @csrf
      <div class="modal-header">
        <h2 class="modal-title h5" id="addPostTitle">Add new post</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger small py-2" id="qa-errors" hidden></div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1" for="qa-agency">Agency *</label>
            <select class="form-select" id="qa-agency" name="agency_id" required><option value="">Choose agency</option>@foreach($agencies as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->code }})</option>@endforeach</select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1" for="qa-client">Client *</label>
            <select class="form-select" id="qa-client" name="client_id" required><option value="">Choose client</option>@foreach($clients as $c)<option value="{{ $c->id }}" data-agency="{{ $c->agency_id }}">{{ $c->name }}</option>@endforeach</select></div>

          <div class="col-12"><hr class="my-1"><div class="d-flex gap-3 align-items-center flex-wrap">
            <span class="small fw-semibold">GeM contract</span>
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="radio" name="contract_mode" id="qa-cm-existing" value="existing" checked><label class="form-check-label small" for="qa-cm-existing">Existing contract</label></div>
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="radio" name="contract_mode" id="qa-cm-new" value="new"><label class="form-check-label small" for="qa-cm-new">New contract</label></div>
          </div></div>
          <div class="col-12" id="qa-contract-existing"><select class="form-select" id="qa-contract" name="gem_contract_id" aria-label="GeM contract"><option value="">Choose contract</option>
            @foreach($pickContracts as $k)<option value="{{ $k->id }}" data-client="{{ $k->client_id }}">{{ $k->bid_no }} · {{ $k->contract_no }}</option>@endforeach</select>
            <div class="form-text" id="qa-no-contracts" hidden>This client has no GeM contract yet. Enter it under "New contract".</div></div>
          <div class="col-12" id="qa-contract-new" hidden><div class="row g-2">
            <div class="col-md-4"><input class="form-control mono" name="bid_no" id="qa-bid" placeholder="GeM bid no., e.g. GEM/2026/B/7972720" aria-label="GeM bid number"></div>
            <div class="col-md-4"><input class="form-control mono" name="contract_no" id="qa-cno" placeholder="Contract no., e.g. GEMC-5116…" aria-label="GeM contract number"></div>
            <div class="col-md-4"><select class="form-select" name="billing_type" aria-label="Billing type"><option value="monthly">Monthly rate</option><option value="daily">Daily rate + EPF/ESIC</option></select></div>
            <div class="col-12 form-text mt-0">Service charge 3.85% and GST 18% are set by default; change them later on the contract page.</div>
          </div></div>

          <div class="col-12"><hr class="my-1"><div class="d-flex gap-3 align-items-center flex-wrap">
            <span class="small fw-semibold">Project</span>
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="radio" name="project_mode" id="qa-pm-existing" value="existing" checked><label class="form-check-label small" for="qa-pm-existing">Existing project</label></div>
            <div class="form-check form-check-inline m-0"><input class="form-check-input" type="radio" name="project_mode" id="qa-pm-new" value="new"><label class="form-check-label small" for="qa-pm-new">New project</label></div>
          </div></div>
          <div class="col-12" id="qa-project-existing"><select class="form-select" id="qa-project" name="project_id" aria-label="Project"><option value="">Choose project</option>
            @foreach($pickProjects as $p)<option value="{{ $p->id }}" data-contract="{{ $p->gem_contract_id }}">{{ $p->name }}{{ $p->pi_name ? ' · '.$p->pi_name : '' }}</option>@endforeach</select></div>
          <div class="col-12" id="qa-project-new" hidden><div class="row g-2">
            <div class="col-md-5"><input class="form-control" name="project_name" id="qa-pname" placeholder="Project name, e.g. MPH" aria-label="Project name"></div>
            <div class="col-md-3"><input class="form-control mono" name="project_code" maxlength="12" placeholder="Code for bill no." aria-label="Project code"></div>
            <div class="col-md-4"><input class="form-control" name="pi_name" placeholder="PI name" aria-label="PI name"></div>
          </div></div>

          <div class="col-12"><hr class="my-1"><span class="small fw-semibold">Post</span></div>
          <div class="col-md-6"><label class="form-label small text-secondary mb-1" for="qa-desig">Designation *</label><input class="form-control" id="qa-desig" name="designation" placeholder="e.g. Academic Programme Assistant" required></div>
          <div class="col-md-2"><label class="form-label small text-secondary mb-1" for="qa-qty">Sanctioned *</label><input class="form-control" type="number" min="1" id="qa-qty" name="sanctioned_qty" value="1" required></div>
          <div class="col-md-4"><label class="form-label small text-secondary mb-1" for="qa-rate">Rate (₹) *</label><input class="form-control" type="number" min="0" step="0.01" id="qa-rate" name="rate" placeholder="Per month, or per day" required></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="qa-save">Save post</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  const $ = id => document.getElementById(id);
  const form = $('qa-form'), agency = $('qa-agency'), client = $('qa-client'), contract = $('qa-contract'), project = $('qa-project');
  const errors = $('qa-errors'), onSaved = @json($onSaved ?? 'reload');
  const radios = n => [...form.querySelectorAll('input[name="' + n + '"]')];
  const mode = n => form.querySelector('input[name="' + n + '"]:checked').value;
  const setMode = (n, v) => { form.querySelector('input[name="' + n + '"][value="' + v + '"]').checked = true; };
  function narrow(select, test) {
    let left = 0;
    [...select.options].forEach(o => { if (o.value) { const ok = test(o); o.hidden = o.disabled = !ok; if (ok) left++; } });
    if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
    return left;
  }
  function refresh() {
    narrow(client, o => !agency.value || o.dataset.agency === agency.value);
    const contracts = narrow(contract, o => !!client.value && o.dataset.client === client.value);
    $('qa-no-contracts').hidden = !(client.value && contracts === 0);
    $('qa-cm-existing').disabled = !!client.value && contracts === 0;
    if ($('qa-cm-existing').disabled) setMode('contract_mode', 'new');
    const newContract = mode('contract_mode') === 'new';
    $('qa-contract-existing').hidden = newContract; $('qa-contract-new').hidden = !newContract;
    const projects = newContract ? 0 : narrow(project, o => !!contract.value && o.dataset.contract === contract.value);
    $('qa-pm-existing').disabled = newContract || (!!contract.value && projects === 0);
    if ($('qa-pm-existing').disabled) setMode('project_mode', 'new');
    const newProject = mode('project_mode') === 'new';
    $('qa-project-existing').hidden = newProject; $('qa-project-new').hidden = !newProject;
  }
  [agency, client, contract].forEach(el => el.addEventListener('change', refresh));
  radios('contract_mode').concat(radios('project_mode')).forEach(r => r.addEventListener('change', refresh));

  // Opening from the employee form carries over its agency and client.
  $('addPostModal').addEventListener('show.bs.modal', () => {
    const ea = document.getElementById('f-agency_id'), ec = document.getElementById('f-client_id');
    if (ea && ea.value) agency.value = ea.value;
    refresh();
    if (ec && ec.value) client.value = ec.value;
    errors.hidden = true; refresh();
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    $('qa-save').disabled = true; errors.hidden = true;
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } });
      const body = await res.json().catch(() => ({}));
      if (res.status === 422) {
        errors.innerHTML = '<ul class="mb-0 ps-3">' + Object.values(body.errors || {}).flat().map(m => '<li>' + m.replace(/</g, '&lt;') + '</li>').join('') + '</ul>';
        errors.hidden = false; return;
      }
      if (!res.ok) { errors.textContent = 'The post could not be saved (error ' + res.status + '). Try again.'; errors.hidden = false; return; }
      if (onSaved === 'select') {
        const p = body.post, sel = document.getElementById('f-post_id');
        const opt = new Option(p.label, p.id, true, true);
        opt.dataset.agency = p.agency_id; opt.dataset.client = p.client_id;
        sel.add(opt);
        const ao = document.querySelector('#f-agency_id option[value="' + p.agency_id + '"]');
        if (ao) { const n = (parseInt(ao.dataset.posts || '0', 10) || 0) + 1; ao.dataset.posts = n; ao.textContent = ao.textContent.replace(/· \d+ posts?$/, '· ' + n + (n === 1 ? ' post' : ' posts')); }
        document.getElementById('f-agency_id').value = p.agency_id;
        document.getElementById('f-client_id').value = p.client_id;
        ['f-agency_id', 'f-client_id'].forEach(id => document.getElementById(id).dispatchEvent(new Event('change')));
        sel.value = p.id;
        bootstrap.Modal.getInstance($('addPostModal')).hide();
        form.reset(); refresh();
        const note = document.getElementById('post-added'); if (note) { note.hidden = false; }
      } else {
        window.location.reload();
      }
    } finally { $('qa-save').disabled = false; }
  });
  refresh();
})();
</script>
