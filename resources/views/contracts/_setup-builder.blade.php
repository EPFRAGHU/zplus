{{-- One-page entry (owner, 21 Nov 2026): projects of a work order, each with its PI, posts and staff. Used on "Add work order"
     and on an existing work order's page. A PI typed once can be chosen again in the other projects. --}}
<div id="wo-builder" data-agency="{{ $fixedAgency ?? '' }}">
<div class="d-flex align-items-center gap-2 flex-wrap mb-2">
  <h2 class="h6 fw-bold mb-0"><span class="badge rounded-pill text-bg-dark me-1">{{ $step ?? 3 }}</span>Projects, Project In-charge, posts and staff</h2>
  <span class="small text-secondary">For each project: its PI, then its posts, then the staff on those posts. One PI can look after several projects. Bank, PAN and Aadhaar of staff are completed later under Employees.</span>
</div>
<div id="wo-projects"></div>
<button type="button" class="btn btn-outline-primary btn-sm" id="wo-add-project"><i class="bi bi-plus-lg me-1"></i>Add another project</button>
</div>

<template id="tpl-project">
  <div class="border rounded-3 p-3 mb-3 bg-body-tertiary wo-project">
    <div class="d-flex align-items-center mb-2"><b class="small text-uppercase text-secondary wo-title">Project</b>
      <button type="button" class="btn btn-sm btn-link text-danger ms-auto wo-remove-project">Remove project</button></div>
    <div class="row g-2">
      <div class="col-md-3"><label class="form-label small mb-1">Short project name *</label><input class="form-control form-control-sm mono text-uppercase" data-f="code" maxlength="20" placeholder="e.g. MPH"></div>
      <div class="col-md-3"><label class="form-label small mb-1">Project name *</label><input class="form-control form-control-sm" data-f="name" maxlength="255" placeholder="e.g. MPH Academic Programme"></div>
      <div class="col-md-3"><label class="form-label small mb-1">Project In-charge</label>
        <select class="form-select form-select-sm wo-pi" data-f="project_incharge_id"><option value="">New PI (type below) / none</option></select></div>
      <div class="col-md-3 wo-pi-new"><label class="form-label small mb-1">New PI name and mobile</label>
        <div class="input-group input-group-sm"><input class="form-control" data-f="pi_name" placeholder="Dr. ..." maxlength="255"><input class="form-control" data-f="pi_mobile" placeholder="Mobile" inputmode="numeric" maxlength="10"></div></div>
    </div>
    <div class="mt-3 small fw-semibold"><i class="bi bi-diagram-3 me-1"></i>Posts of this project</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-1">
      <thead><tr><th>Designation *</th><th style="width:16%">Sanctioned *</th><th style="width:20%">Rate (₹) *</th><th style="width:5%"></th></tr></thead>
      <tbody class="wo-posts"></tbody></table></div>
    <button type="button" class="btn btn-sm btn-outline-secondary wo-add-post"><i class="bi bi-plus"></i> Add post</button>
    <div class="mt-3 small fw-semibold"><i class="bi bi-people me-1"></i>Staff on these posts</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-1">
      <thead><tr><th>Name *</th><th style="width:24%">Post *</th><th style="width:14%">Joining date</th><th style="width:13%">Mobile</th><th style="width:15%">EPF / ESI / TDS</th><th style="width:5%"></th></tr></thead>
      <tbody class="wo-staff"></tbody></table></div>
    <button type="button" class="btn btn-sm btn-outline-secondary wo-add-staff"><i class="bi bi-plus"></i> Add staff</button>
  </div>
</template>
<template id="tpl-post">
  <tr class="wo-post"><td><input class="form-control form-control-sm" data-f="designation" maxlength="255" placeholder="e.g. Project Technical Support-II"></td>
    <td><input class="form-control form-control-sm" type="number" min="1" max="999" data-f="sanctioned_qty" value="1"></td>
    <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" data-f="rate" placeholder="Per month or per day"></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger wo-remove" aria-label="Remove post"><i class="bi bi-x-lg"></i></button></td></tr>
</template>
<template id="tpl-staff">
  <tr class="wo-staff-row"><td><input class="form-control form-control-sm" data-f="name" maxlength="255" placeholder="Full name"></td>
    <td><select class="form-select form-select-sm wo-post-pick" aria-label="Post"></select><input type="hidden" data-f="post"></td>
    <td><input class="form-control form-control-sm" type="date" data-f="joining_date"></td>
    <td><input class="form-control form-control-sm" data-f="mobile" inputmode="numeric" maxlength="10"></td>
    <td class="text-nowrap small"><label class="me-1"><input type="checkbox" class="form-check-input" data-f="pf" value="1"> EPF</label><label class="me-1"><input type="checkbox" class="form-check-input" data-f="esic" value="1"> ESI</label><label><input type="checkbox" class="form-check-input" data-f="tds" value="1"> TDS</label></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger wo-remove" aria-label="Remove staff"><i class="bi bi-x-lg"></i></button></td></tr>
</template>

<script>
(function () {
  const root = document.getElementById('wo-builder'), wrap = document.getElementById('wo-projects'), form = root.closest('form');
  const agencySel = document.getElementById('f-agency_id');
  const agencyVal = () => agencySel ? agencySel.value : root.dataset.agency;
  const incharges = @json($incharges), old = @json(old('setup', []));
  const tpl = id => document.getElementById(id).content.firstElementChild.cloneNode(true);
  const cards = () => [...wrap.querySelectorAll('.wo-project')];
  let seq = 0;

  // PI list: the agency's saved PIs, plus new PIs typed in the other projects of this form (value "name:...").
  function fillPis(card) {
    const sel = card.querySelector('.wo-pi'), keep = sel.value;
    sel.querySelectorAll('option[data-pi]').forEach(o => o.remove());
    incharges.filter(p => !agencyVal() || String(p.agency_id) === String(agencyVal()))
      .forEach(p => { const o = new Option(p.name, p.id); o.dataset.pi = '1'; sel.add(o); });
    const typed = new Set();
    cards().forEach(c => { if (c !== card && !c.querySelector('.wo-pi').value) { const n = c.querySelector('[data-f=pi_name]').value.trim(); if (n) typed.add(n); } });
    typed.forEach(n => { const o = new Option(n + ' (new, entered above/below)', 'name:' + n); o.dataset.pi = '1'; sel.add(o); });
    sel.value = [...sel.options].some(o => o.value === keep) ? keep : '';
    card.querySelector('.wo-pi-new').hidden = !!sel.value;
  }
  const fillAllPis = () => cards().forEach(fillPis);
  function refreshPostPicks(card) {
    const posts = [...card.querySelectorAll('.wo-post')];
    card.querySelectorAll('.wo-post-pick').forEach(sel => {
      const keep = sel.value;
      sel.innerHTML = '<option value="">Choose post</option>';
      posts.forEach((row, i) => sel.add(new Option(row.querySelector('[data-f=designation]').value || ('Post ' + (i + 1)), row.dataset.key)));
      sel.value = [...sel.options].some(o => o.value === keep) ? keep : '';
    });
  }
  function addPost(card, data) {
    const row = tpl('tpl-post'); row.dataset.key = 'k' + (seq++);
    Object.entries(data || {}).forEach(([k, v]) => { const i = row.querySelector('[data-f="' + k + '"]'); if (i) i.value = v; });
    card.querySelector('.wo-posts').append(row);
    row.querySelector('[data-f=designation]').addEventListener('input', () => refreshPostPicks(card));
    refreshPostPicks(card);
  }
  function addStaff(card, data) {
    const row = tpl('tpl-staff');
    card.querySelector('.wo-staff').append(row);
    refreshPostPicks(card);
    if (data) {
      ['name', 'joining_date', 'mobile'].forEach(k => { if (data[k] != null) row.querySelector('[data-f="' + k + '"]').value = data[k]; });
      ['pf', 'esic', 'tds'].forEach(k => { row.querySelector('[data-f="' + k + '"]').checked = !!Number(data[k] || 0); });
      const posts = card.querySelectorAll('.wo-post');
      if (data.post != null && posts[data.post]) row.querySelector('.wo-post-pick').value = posts[data.post].dataset.key;
    }
  }
  function addProject(data) {
    const card = tpl('tpl-project');
    wrap.append(card);
    ['code', 'name', 'pi_name', 'pi_mobile'].forEach(k => { if (data && data[k] != null) card.querySelector('[data-f="' + k + '"]').value = data[k]; });
    fillPis(card);
    if (data && data.project_incharge_id) { card.querySelector('.wo-pi').value = data.project_incharge_id; }
    card.querySelector('.wo-pi').addEventListener('change', () => fillAllPis());
    card.querySelector('[data-f=pi_name]').addEventListener('input', () => cards().filter(c => c !== card).forEach(fillPis));
    (data && data.posts && data.posts.length ? data.posts : [{}]).forEach(p => addPost(card, p));
    (data && data.staff ? data.staff : []).forEach(st => addStaff(card, st));
    card.querySelector('.wo-add-post').addEventListener('click', () => addPost(card));
    card.querySelector('.wo-add-staff').addEventListener('click', () => addStaff(card));
    card.querySelector('.wo-remove-project').addEventListener('click', () => { card.remove(); renumber(); fillAllPis(); });
    card.addEventListener('click', e => { const b = e.target.closest('.wo-remove'); if (b) { b.closest('tr').remove(); refreshPostPicks(card); } });
    renumber(); fillAllPis();
  }
  function renumber() {
    cards().forEach((c, i) => c.querySelector('.wo-title').textContent = 'Project ' + (i + 1));
  }
  // Names are given only on submit, so removed rows leave no gaps; blank post rows are skipped the same way the server skips them.
  form.addEventListener('submit', () => {
    cards().forEach((card, p) => {
      const base = 'setup[' + p + ']', pi = card.querySelector('.wo-pi');
      if (pi.value.startsWith('name:')) {   // a new PI typed in another project: same name, saved once
        card.querySelector('[data-f=pi_name]').value = pi.value.slice(5);
        pi.value = '';
      }
      card.querySelectorAll(':scope > .row [data-f]').forEach(i => i.name = base + '[' + i.dataset.f + ']');
      const index = {}; let n = 0;
      card.querySelectorAll('.wo-post').forEach(row => {
        const blank = !row.querySelector('[data-f=designation]').value.trim() && !row.querySelector('[data-f=rate]').value.trim();
        row.querySelectorAll('[data-f]').forEach(i => i.name = blank ? '' : base + '[posts][' + n + '][' + i.dataset.f + ']');
        if (!blank) { index[row.dataset.key] = n; n++; }
      });
      card.querySelectorAll('.wo-staff-row').forEach((row, s) => {
        const pick = row.querySelector('.wo-post-pick').value;
        row.querySelector('[data-f=post]').value = pick in index ? index[pick] : '';
        row.querySelectorAll('[data-f]').forEach(i => i.name = base + '[staff][' + s + '][' + i.dataset.f + ']');
      });
    });
  });
  if (agencySel) agencySel.addEventListener('change', fillAllPis);
  document.getElementById('wo-add-project').addEventListener('click', () => addProject());
  (old.length ? old : [null]).forEach(addProject);
})();
</script>
