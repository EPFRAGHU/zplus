<style>
  .wo{--wo-navy:#0f2a5c;--wo-blue:#1d4ed8;--wo-blue-50:#eef3ff;--wo-line:#e3e8f0;--wo-muted:#64748b;--wo-text:#0f172a;font-family:Inter,system-ui,sans-serif;color:var(--wo-text)}
  .wo-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px}
  .wo-head-icon{width:46px;height:46px;border-radius:12px;border:1px solid var(--wo-line);background:#fff;display:grid;place-items:center;color:var(--wo-blue);font-size:1.35rem;box-shadow:0 1px 2px rgba(15,23,42,.05)}
  .wo-head h1{font-size:1.45rem;font-weight:700;margin:0;letter-spacing:-.01em}
  .wo-head p{margin:0;color:var(--wo-muted)}
  .wo-btn-primary{background:var(--wo-blue);border-color:var(--wo-blue);color:#fff;font-weight:600}
  .wo-btn-primary:hover{background:#1e40af;border-color:#1e40af;color:#fff}
  .wo-btn-line{background:#fff;border:1px solid var(--wo-line);color:var(--wo-text);font-weight:500}
  .wo-btn-line:hover{border-color:#c7d2e3;background:#f8fafc;color:var(--wo-text)}
  .wo-btn-blue{background:#fff;border:1px solid #bfd0f5;color:var(--wo-blue);font-weight:600}
  .wo-btn-blue:hover{background:var(--wo-blue-50);color:var(--wo-blue)}
  .wo-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}
  .wo-kpi{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:14px;border:1px solid var(--wo-line);background:var(--k-bg)}
  .wo-kpi i{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;font-size:1.3rem;color:var(--k-fg);background:var(--k-ring)}
  .wo-kpi .l{color:#334155;font-size:.84rem;font-weight:500}
  .wo-kpi .v{font-size:1.55rem;font-weight:700;line-height:1.1;font-variant-numeric:tabular-nums}
  .wo-kpi .v small{font-size:.95rem;font-weight:600}
  .k-blue{--k-bg:#f3f7ff;--k-ring:#e0eaff;--k-fg:#2563eb} .k-violet{--k-bg:#f7f4ff;--k-ring:#ece5ff;--k-fg:#7c3aed}
  .k-amber{--k-bg:#fffaf0;--k-ring:#fdeccb;--k-fg:#b45309} .k-green{--k-bg:#f2fbf5;--k-ring:#d9f2e2;--k-fg:#15803d}
  .k-red{--k-bg:#fff5f5;--k-ring:#fde0e0;--k-fg:#dc2626} .k-red .v{color:#b91c1c}
  .wo-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
  .wo-tabs{display:inline-flex;gap:2px;padding:4px;border:1px solid var(--wo-line);border-radius:12px;background:#fff}
  .wo-tabs a{padding:6px 14px;border-radius:9px;color:#334155;text-decoration:none;font-weight:500;font-size:.88rem;white-space:nowrap}
  .wo-tabs a:hover{background:#f1f5f9}
  .wo-tabs a.on{background:var(--wo-blue);color:#fff;font-weight:600}
  .wo-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;vertical-align:1px}
  .wo-pick{display:flex;align-items:center;border:1px solid var(--wo-line);border-radius:10px;background:#fff;padding-left:12px}
  .wo-pick span{color:var(--wo-muted);font-size:.86rem;white-space:nowrap}
  .wo-pick select{border:0;box-shadow:none;background-color:transparent;font-weight:500;font-size:.88rem;min-width:110px}
  .wo-search{flex:1 1 220px;position:relative}
  .wo-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--wo-muted)}
  .wo-search input{padding-left:36px;border-radius:10px;border-color:var(--wo-line)}
  .wo-views{display:inline-flex;border:1px solid var(--wo-line);border-radius:10px;overflow:hidden;background:#fff}
  .wo-views button{border:0;background:#fff;padding:7px 11px;color:#475569}
  .wo-views button.on{background:var(--wo-blue);color:#fff}
  .wo-card{background:#fff;border:1px solid var(--wo-line);border-radius:14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
  .wo-row{display:flex;align-items:center;gap:14px;padding:14px 16px;flex-wrap:wrap}
  .wo-toggle{border:0;background:none;padding:4px;color:#334155;display:flex;align-items:center;gap:12px;text-align:left;flex:1 1 320px;min-width:0}
  .wo-toggle .chev{transition:transform .15s;font-size:1rem}
  .wo-toggle[aria-expanded="true"] .chev{transform:rotate(90deg)}
  .wo-toggle .doc{font-size:1.35rem;color:#334155}
  .wo-no{font-weight:700;font-size:1rem;color:var(--wo-text);word-break:break-all}
  .wo-meta{color:var(--wo-muted);font-size:.85rem}
  .wo-meta b{color:#334155;font-weight:500}
  .wo-type{background:#d6f5fb;color:#075e72;font-weight:600;border-radius:999px;padding:2px 10px;font-size:.78rem;white-space:nowrap}
  .wo-type.off{background:#eef0f4;color:#475569}
  .wo-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-left:auto}
  .wo-fill{border-radius:999px;padding:4px 12px;font-weight:600;font-size:.82rem;white-space:nowrap}
  .wo-fill.full{background:#dcfce7;color:#166534} .wo-fill.part{background:#fef3c7;color:#92400e} .wo-fill.none{background:#f1f5f9;color:#475569}
  .wo-body{border-top:1px solid var(--wo-line);padding:0 12px 12px}
  .wo-table{margin:12px 0 0;font-size:.88rem}
  .wo-table thead th{background:#f8fafc;color:#334155;font-weight:600;text-transform:none;letter-spacing:0;font-size:.82rem;border-color:var(--wo-line)}
  .wo-table td{border-color:var(--wo-line)}
  .wo-code{background:#e0464f;color:#fff;border-radius:7px;padding:3px 9px;font-weight:600;font-size:.8rem;white-space:nowrap}
  .wo-icon-btn{border:0;background:none;color:var(--wo-blue);padding:4px 6px;font-size:1.05rem;line-height:1}
  .wo-icon-btn:hover{color:#1e40af}
  .wo-empty{background:#fff;border:1px dashed #cbd5e1;border-radius:14px;padding:32px;text-align:center;color:var(--wo-muted)}
  .wo-list.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .wo-list.grid .wo-card{margin:0}
  .wo-list.grid .wo-right{margin-left:0;width:100%}
  .wo-list.grid .wo-prog{flex:1}
  @media (max-width:1200px){.wo-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media (max-width:768px){
    .wo-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    .wo-list.grid{grid-template-columns:1fr}
    .wo-right{margin-left:0;width:100%}
    .wo-right .wo-prog{flex:1 1 150px;min-width:150px}
    .wo-kpi{padding:12px;gap:10px} .wo-kpi i{width:38px;height:38px;font-size:1.1rem} .wo-kpi .v{font-size:1.3rem}
    .wo-tabs{width:100%;overflow-x:auto}
    .wo-pick,.wo-search{flex:1 1 100%}
    .wo-head .wo-actions{width:100%}
  }
</style>
