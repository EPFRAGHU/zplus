{{-- One-page entry (owner, 21 Nov 2026): projects of this work order, each with its PI, posts and staff. --}}
<hr class="my-4">
<div class="d-flex align-items-center gap-2 mb-2">
  <h2 class="h6 fw-bold mb-0"><i class="bi bi-diagram-3 me-1"></i>Projects, posts and staff</h2>
  <span class="small text-secondary">Optional here; you can also add them later on the work order page. Bank, PAN and Aadhaar of staff are completed later under Employees.</span>
</div>
<div id="wo-projects"></div>
<button type="button" class="btn btn-outline-primary btn-sm" id="wo-add-project"><i class="bi bi-plus-lg me-1"></i>Add project</button>

<template id="tpl-project">
  <div class="border rounded-3 p-3 mb-3 bg-body-tertiary wo-project">
    <div class="d-flex align-items-center mb-2"><b class="small text-uppercase text-secondary wo-title">Project</b>
      <button type="button" class="btn btn-sm btn-link text-danger ms-auto wo-remove-project">Remove project</button></div>
    <div class="row g-2">
      <div class="col-md-2"><label class="form-label small mb-1">Short project name *</label><input class="form-control form-control-sm mono text-uppercase" data-f="code" maxlength="12" placeholder="MPH"></div>
      <div class="col-md-4"><label class="form-label small mb-1">Project name *</label><input class="form-control form-control-sm" data-f="name" maxlength="255" placeholder="MPH Academic Programme"></div>
      <div class="col-md-3"><label class="form-label small mb-1">Project In-charge</label>
        <select class="form-select form-select-sm wo-pi" data-f="project_incharge_id"><option value="">New / none</option></select></div>
      <div class="col-md-3 wo-pi-new"><label class="form-label small mb-1">New PI name and mobile</label>
        <div class="input-group input-group-sm"><input class="form-control" data-f="pi_name" placeholder="Dr. ..." maxlength="255"><input class="form-control" data-f="pi_mobile" placeholder="Mobile" inputmode="numeric" maxlength="10"></div></div>
    </div>
    <div class="mt-3 small fw-semibold">Posts</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-1">
      <thead><tr><th>Designation *</th><th style="width:16%">Sanctioned *</th><th style="width:20%">Rate (₹) *</th><th style="width:5%"></th></tr></thead>
      <tbody class="wo-posts"></tbody></table></div>
    <button type="button" class="btn btn-sm btn-outline-secondary wo-add-post"><i class="bi bi-plus"></i> Add post</button>
    <div class="mt-3 small fw-semibold">Staff</div>
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
    <td class="text-nowrap small"><label class="me-1"><input type="checkbox" class="form-check-input" data-f="pf" value="1"> EPF</label><label class="me-1"><input type="checkbox" class="form-check-input" data-f="esic" value="1"> ESI</label><label><input type="checkbox" class="form-check-input" data-f="tds" value="1" checked> TDS</label></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger wo-remove" aria-label="Remove staff"><i class="bi bi-x-lg"></i></button></td></tr>
</template>

<script>
(function () {
  const wrap = document.getElementById('wo-projects'), agency = document.getElementById('f-agency_id');
  const incharges = @json($incharges), old = @json(old('setup', []));
  const tpl = id => document.getElementById(id).content.firstElementChild.cloneNode(true);
  let seq = 0;

  function fillPis(sel) {
    const keep = sel.value;
    sel.querySelectorAll('option[data-pi]').forEach(o => o.remove());
    incharges.filter(p => !agency.value || String(p.agency_id) === agency.value)
      .forEach(p => { const o = new Option(p.name, p.id); o.dataset.pi = '1'; sel.add(o); });
    sel.value = [...sel.options].some(o => o.value === keep) ? keep : '';
  }
  function toggleNewPi(card) {
    card.querySelector('.wo-pi-new').hidden = !!card.querySelector('.wo-pi').value;
  }
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
    return row;
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
    const pi = card.querySelector('.wo-pi'); fillPis(pi);
    ['code', 'name', 'pi_name', 'pi_mobile', 'project_incharge_id'].forEach(k => { if (data && data[k] != null) card.querySelector('[data-f="' + k + '"]').value = data[k]; });
    pi.addEventListener('change', () => toggleNewPi(card)); toggleNewPi(card);
    (data && data.posts && data.posts.length ? data.posts : [{}]).forEach(p => addPost(card, p));
    (data && data.staff ? data.staff : []).forEach(st => addStaff(card, st));
    card.querySelector('.wo-add-post').addEventListener('click', () => addPost(card));
    card.querySelector('.wo-add-staff').addEventListener('click', () => addStaff(card));
    card.querySelector('.wo-remove-project').addEventListener('click', () => { card.remove(); renumber(); });
    card.addEventListener('click', e => { const b = e.target.closest('.wo-remove'); if (b) { b.closest('tr').remove(); refreshPostPicks(card); } });
    renumber();
  }
  function renumber() {
    wrap.querySelectorAll('.wo-project').forEach((c, i) => c.querySelector('.wo-title').textContent = 'Project ' + (i + 1));
  }
  // Names are given only on submit, so removed rows leave no gaps; blank post rows are skipped the same way the server skips them.
  agency.form.addEventListener('submit', () => {
    wrap.querySelectorAll('.wo-project').forEach((card, p) => {
      const base = 'setup[' + p + ']';
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
  agency.addEventListener('change', () => wrap.querySelectorAll('.wo-pi').forEach(sel => { fillPis(sel); toggleNewPi(sel.closest('.wo-project')); }));
  document.getElementById('wo-add-project').addEventListener('click', () => addProject());
  (old.length ? old : [null]).forEach(addProject);
})();
</script>
