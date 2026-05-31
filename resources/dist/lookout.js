/* ============================================================
   Lookout — shared application engine (vanilla JS, no build step)
   Builds the sidebar + topbar shell, hydrates charts, and wires
   all interactions. Each page sets window.LK_PAGE before this loads.
   ============================================================ */
(function () {
  "use strict";

  /* ---------------- Navigation model ---------------- */
  const DEFAULT_NAV = [
    { group: "Overview", items: [
      { id: "overview", name: "Overview", icon: "grid", href: "/lookout" },
    ]},
    { group: "Traffic", items: [
      { id: "requests", name: "Requests", icon: "activity", href: "/lookout/requests" },
      { id: "exceptions", name: "Exceptions", icon: "alert", href: "/lookout/exceptions" },
      { id: "queries", name: "Queries", icon: "database", href: "/lookout/queries" },
      { id: "outgoing", name: "Outgoing HTTP", icon: "send", href: "/lookout/outgoing" },
    ]},
    { group: "Background", items: [
      { id: "jobs", name: "Jobs", icon: "layers", href: "/lookout/jobs" },
      { id: "scheduled", name: "Scheduled", icon: "clock", href: "/lookout/scheduled" },
      { id: "commands", name: "Commands", icon: "terminal", href: "/lookout/commands" },
    ]},
    { group: "Application Events", items: [
      { id: "cache", name: "Cache", icon: "zap", href: "/lookout/cache" },
      { id: "mail", name: "Mail", icon: "mail", href: "/lookout/mail" },
      { id: "notifications", name: "Notifications", icon: "bell", href: "/lookout/notifications" },
      { id: "logs", name: "Logs", icon: "file", href: "/lookout/logs" },
    ]},
    { group: "Operations", items: [
      { id: "alerts", name: "Alerts", icon: "siren", href: "/lookout/alerts" },
      { id: "audit", name: "Audit", icon: "shield", href: "/lookout/audit" },
      { id: "health", name: "Health", icon: "heart", href: "/lookout/health" },
    ]},
  ];
  const NAV = Array.isArray(window.LK_NAV) && window.LK_NAV.length ? window.LK_NAV : DEFAULT_NAV;

  const ICONS = {
    grid:'<rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/><rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>',
    activity:'<path d="M1 8h3l2-5 3 10 2-5h3"/>',
    alert:'<path d="M8 1.5l6.5 12H1.5z"/><path d="M8 6.5v3.2"/><circle cx="8" cy="11.7" r="0.5" fill="currentColor" stroke="none"/>',
    database:'<ellipse cx="8" cy="3.5" rx="5.5" ry="2"/><path d="M2.5 3.5v8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-8"/><path d="M2.5 7.5c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2"/>',
    send:'<path d="M14.5 1.5L7.5 9"/><path d="M14.5 1.5l-4.6 13-2.4-5.4-5.4-2.4z"/>',
    layers:'<path d="M8 1.5L1.5 5 8 8.5 14.5 5z"/><path d="M2 8.2L8 11.5l6-3.3"/><path d="M2 11.2L8 14.5l6-3.3"/>',
    clock:'<circle cx="8" cy="8" r="6.5"/><path d="M8 4.5V8l2.5 1.5"/>',
    terminal:'<rect x="1.5" y="2.5" width="13" height="11" rx="1.5"/><path d="M4 6l2.5 2L4 10"/><path d="M8.5 10.5H11"/>',
    zap:'<path d="M9 1.5L3 9h4l-1 5.5L13 7H9z"/>',
    mail:'<rect x="1.5" y="3" width="13" height="10" rx="1.5"/><path d="M2 4.2l6 4.3 6-4.3"/>',
    bell:'<path d="M8 1.5a4 4 0 00-4 4c0 4-1.5 5-1.5 5h11s-1.5-1-1.5-5a4 4 0 00-4-4z"/><path d="M6.5 13a1.5 1.5 0 003 0"/>',
    file:'<path d="M3 1.5h6l4 4v9H3z"/><path d="M9 1.5v4h4"/>',
    siren:'<path d="M4 13V8a4 4 0 018 0v5"/><rect x="2.5" y="13" width="11" height="1.5" rx="0.75"/><path d="M8 1.2v1.6"/>',
    shield:'<path d="M8 1.5l5.5 2v4c0 4-2.5 6-5.5 7-3-1-5.5-3-5.5-7v-4z"/>',
    heart:'<path d="M8 13.5S2 10 2 5.8A3 3 0 018 4a3 3 0 016 1.8C14 10 8 13.5 8 13.5z"/>',
    search:'<circle cx="7" cy="7" r="5"/><path d="M11 11l3.5 3.5"/>',
    menu:'<path d="M2 4h12M2 8h12M2 12h12"/>',
    gear:'<circle cx="8" cy="8" r="2.3"/><path d="M8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.4 3.4l1.4 1.4M11.2 11.2l1.4 1.4M3.4 12.6l1.4-1.4M11.2 4.8l1.4-1.4"/>',
    cal:'<rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6h12M5.5 1.5v3M10.5 1.5v3"/>',
    inbox:'<path d="M1.5 9.5L3.5 3h9l2 6.5v3.5a1 1 0 01-1 1h-11a1 1 0 01-1-1z"/><path d="M1.5 9.5h3l1 2h5l1-2h3"/>',
  };
  function icon(n, cls) {
    return '<svg class="' + (cls||'ico') + '" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[n]||"") + '</svg>';
  }
  window.LKIcon = icon;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  const P = window.LK_PAGE || { id: "overview", title: "Overview" };

  /* ---------------- Theme / density / accent persistence ---------------- */
  const store = {
    get t(){ return localStorage.getItem("lk-theme") || "light"; },
    get d(){ return localStorage.getItem("lk-density") || "compact"; },
    get a(){ return localStorage.getItem("lk-accent") || "emerald"; },
  };
  const ACCENTS = {
    emerald: { a:"#10b981", h:"#059669", w:"#ecfdf5", t:"#047857", r:"rgba(16,185,129,0.22)" },
    blue:    { a:"#3b82f6", h:"#2563eb", w:"#eff6ff", t:"#1d4ed8", r:"rgba(59,130,246,0.22)" },
    violet:  { a:"#7c3aed", h:"#6d28d9", w:"#f5f3ff", t:"#6d28d9", r:"rgba(124,58,237,0.22)" },
    slate:   { a:"#475569", h:"#334155", w:"#f1f5f9", t:"#334155", r:"rgba(71,85,105,0.22)" },
  };
  function applyTheme() {
    document.documentElement.setAttribute("data-theme", store.t);
    document.documentElement.setAttribute("data-density", store.d);
    const ac = ACCENTS[store.a] || ACCENTS.emerald;
    const r = document.documentElement.style;
    r.setProperty("--accent", ac.a); r.setProperty("--accent-hover", ac.h);
    r.setProperty("--accent-weak", store.t==="dark" ? "rgba(255,255,255,0.07)" : ac.w);
    r.setProperty("--accent-text", store.t==="dark" ? ac.a : ac.t);
    r.setProperty("--accent-ring", ac.r);
    if (store.t === "dark") r.setProperty("--accent-weak", hexA(ac.a, 0.14));
  }
  function hexA(hex, a){ const n=parseInt(hex.slice(1),16); return "rgba("+(n>>16&255)+","+(n>>8&255)+","+(n&255)+","+a+")"; }
  applyTheme();

  /* ---------------- Build sidebar ---------------- */
  function buildSidebar() {
    const meta = window.LK_META || {};
    let h = '<div class="brand"><div class="mark"></div><span class="name">Lookout</span><span class="env">' + escapeHtml(meta.environment || "local") + '</span></div>';
    h += '<nav class="lk-nav">';
    for (const g of NAV) {
      h += '<div class="nav-group"><div class="lbl">' + g.group + '</div>';
      for (const it of g.items) {
        const active = it.id === P.id ? " active" : "";
        const cnt = it.count ? '<span class="count' + (it.danger ? " danger" : "") + '">' + it.count + '</span>' : "";
        h += '<a class="nav-item' + active + '" href="' + it.href + '">' + icon(it.icon) + '<span>' + it.name + '</span>' + cnt + '</a>';
      }
      h += '</div>';
    }
    h += '</nav>';
    h += '<div class="side-foot"><div class="user-chip"><div class="av">' + escapeHtml(meta.userInitials || "LO") + '</div><div class="who"><div class="n">' + escapeHtml(meta.userName || "Lookout") + '</div><div class="e">' + escapeHtml(meta.userLabel || "Observability") + '</div></div></div></div>';
    return h;
  }

  /* ---------------- Build topbar ---------------- */
  function buildCrumbs() {
    if (P.crumbs && P.crumbs.length) {
      let h = '<div class="crumbs">';
      P.crumbs.forEach(function (c, i) {
        if (i > 0) h += '<span class="sep">/</span>';
        if (c.href) h += '<a class="crumb-link" href="' + c.href + '">' + c.label + '</a>';
        else h += '<span class="leaf">' + c.label + '</span>';
      });
      h += '</div>';
      return h;
    }
    return '<div class="crumbs"><span class="h1">' + (P.title || "") + '</span>' + (P.sub ? '<span class="sep">·</span><span class="crumb-link">' + P.sub + '</span>' : '') + '</div>';
  }
  function buildTopbar() {
    let h = '<button class="menu-btn icon-btn" id="menuBtn">' + icon("menu") + '</button>';
    h += buildCrumbs();
    h += '<div class="spacer"></div>';
    if (P.search !== false) h += '<div class="search" id="searchBtn">' + icon("search","") + '<span>Search…</span><kbd>⌘K</kbd></div>';
    if (P.range !== false) h += '<button class="pill" id="rangeBtn"><span class="dot-live"></span>' + (P.rangeLabel || "Last 24h") + '</button>';
    h += '<button class="icon-btn" id="settingsBtn" title="Settings">' + icon("gear","") + '</button>';
    return h;
  }

  /* ---------------- Settings popover ---------------- */
  function buildPopover() {
    return '<div class="ph">Appearance</div>' +
      '<div class="opt"><span class="lab">Theme</span><div class="seg-toggle" id="themeSeg">' +
        '<button data-v="light">Light</button><button data-v="dark">Dark</button></div></div>' +
      '<div class="opt"><span class="lab">Density</span><div class="seg-toggle" id="densitySeg">' +
        '<button data-v="compact">Compact</button><button data-v="comfortable">Cozy</button></div></div>' +
      '<div class="opt"><span class="lab">Accent</span><div class="swatches" id="accentSw">' +
        Object.keys(ACCENTS).map(function(k){ return '<span class="sw" data-v="'+k+'" style="background:'+ACCENTS[k].a+'"></span>'; }).join("") +
      '</div></div>';
  }

  /* ---------------- Mount shell ---------------- */
  function mount() {
    const side = document.getElementById("lk-sidebar");
    if (side) side.innerHTML = buildSidebar();
    const top = document.getElementById("lk-topbar");
    if (top) top.innerHTML = buildTopbar();

    // settings popover + scrim
    const scrim = document.createElement("div"); scrim.className = "scrim"; scrim.id = "scrim";
    const pop = document.createElement("div"); pop.className = "pop"; pop.id = "pop"; pop.innerHTML = buildPopover();
    const back = document.createElement("div"); back.className = "backdrop"; back.id = "backdrop";
    document.body.appendChild(scrim); document.body.appendChild(pop); document.body.appendChild(back);

    wire();
    syncSegs();
    hydrate();
  }

  function syncSegs() {
    document.querySelectorAll('#themeSeg button').forEach(b => b.classList.toggle("on", b.dataset.v === store.t));
    document.querySelectorAll('#densitySeg button').forEach(b => b.classList.toggle("on", b.dataset.v === store.d));
    document.querySelectorAll('#accentSw .sw').forEach(s => s.classList.toggle("on", s.dataset.v === store.a));
  }

  /* ---------------- Wiring ---------------- */
  function wire() {
    const menuBtn = document.getElementById("menuBtn");
    const side = document.getElementById("lk-sidebar");
    const back = document.getElementById("backdrop");
    if (menuBtn) menuBtn.onclick = () => { side.classList.add("open"); back.classList.add("open"); };
    if (back) back.onclick = () => { side.classList.remove("open"); back.classList.remove("open"); };

    const settingsBtn = document.getElementById("settingsBtn");
    const pop = document.getElementById("pop");
    const scrim = document.getElementById("scrim");
    if (settingsBtn) settingsBtn.onclick = (e) => { e.stopPropagation(); pop.classList.toggle("open"); scrim.classList.toggle("open"); };
    if (scrim) scrim.onclick = () => { pop.classList.remove("open"); scrim.classList.remove("open"); closeSearch(); };

    document.querySelectorAll('#themeSeg button').forEach(b => b.onclick = () => { localStorage.setItem("lk-theme", b.dataset.v); applyTheme(); syncSegs(); });
    document.querySelectorAll('#densitySeg button').forEach(b => b.onclick = () => { localStorage.setItem("lk-density", b.dataset.v); applyTheme(); syncSegs(); });
    document.querySelectorAll('#accentSw .sw').forEach(s => s.onclick = () => { localStorage.setItem("lk-accent", s.dataset.v); applyTheme(); syncSegs(); });

    // search palette
    const searchBtn = document.getElementById("searchBtn");
    if (searchBtn) searchBtn.onclick = openSearch;
    document.addEventListener("keydown", (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") { e.preventDefault(); openSearch(); }
      if (e.key === "Escape") { closeSearch(); document.getElementById("pop").classList.remove("open"); document.getElementById("scrim").classList.remove("open"); }
    });

    // expandable rows (event delegation)
    document.addEventListener("click", (e) => {
      const row = e.target.closest("tr.row");
      if (row && row.dataset.expand !== undefined) {
        const next = row.nextElementSibling;
        if (next && next.classList.contains("detail-row")) {
          const open = next.classList.toggle("show");
          next.querySelector(".detail-inner").style.display = open ? "block" : "none";
          row.classList.toggle("open", open);
        }
      }
    });

    // generic filters
    document.querySelectorAll("[data-filter]").forEach(ctrl => {
      ctrl.addEventListener("input", applyFilters);
      ctrl.addEventListener("change", applyFilters);
    });
    document.querySelectorAll(".seg-toggle[data-filter-group] button").forEach(btn => {
      btn.addEventListener("click", () => {
        btn.parentElement.querySelectorAll("button").forEach(b => b.classList.remove("on"));
        btn.classList.add("on");
        applyFilters();
      });
    });

    // action buttons (resolve / ignore / export) -> toast
    document.querySelectorAll("[data-action]").forEach(btn => {
      btn.addEventListener("click", () => toast(btn.dataset.action, btn.dataset.toast || "Done.", btn.dataset.tone || "ok"));
    });
  }

  /* ---------------- Filtering ---------------- */
  function applyFilters() {
    const table = document.querySelector("table.lk[data-filterable]");
    if (!table) return;
    const controls = document.querySelectorAll("[data-filter]");
    const segs = document.querySelectorAll(".seg-toggle[data-filter-group]");
    const rows = table.querySelectorAll("tbody tr.row");
    let shown = 0;
    rows.forEach(r => {
      let ok = true;
      controls.forEach(c => {
        if (c.disabled) return;
        const col = c.dataset.filter; const val = (c.value || "").toLowerCase().trim();
        if (!val) return;
        const cell = (r.dataset[col] || "").toLowerCase();
        if (c.dataset.match === "min") { if (parseFloat(cell) < parseFloat(val)) ok = false; }
        else if (c.dataset.match === "contains") { if (cell.indexOf(val) === -1) ok = false; }
        else { if (cell !== val) ok = false; }
      });
      segs.forEach(s => {
        const col = s.dataset.filterGroup; const on = s.querySelector("button.on");
        const val = on ? (on.dataset.v || "").toLowerCase() : "";
        if (val && val !== "all") { if ((r.dataset[col] || "").toLowerCase() !== val) ok = false; }
      });
      // also collapse any open detail row
      const det = r.nextElementSibling;
      r.style.display = ok ? "" : "none";
      if (det && det.classList.contains("detail-row")) det.style.display = ok ? (det.classList.contains("show") ? "" : "") : "none";
      if (ok) shown++;
    });
    const meta = document.querySelector(".result-meta");
    if (meta && meta.dataset.total) meta.textContent = shown + " of " + meta.dataset.total + " shown";
    const empty = document.getElementById("tableEmpty");
    if (empty) empty.style.display = shown === 0 ? "" : "none";
  }

  /* ---------------- Toast / feedback ---------------- */
  function toast(action, msg, tone) {
    const host = document.getElementById("feedbackHost") || (function () {
      const w = document.querySelector(".wrap"); const d = document.createElement("div");
      d.id = "feedbackHost"; if (w) w.insertBefore(d, w.firstChild); return d;
    })();
    host.innerHTML = '<div class="feedback ' + tone + '">' +
      '<span>' + msg + '</span><span class="x">✕</span></div>';
    const fb = host.querySelector(".feedback");
    fb.querySelector(".x").onclick = () => host.innerHTML = "";
    clearTimeout(host._t); host._t = setTimeout(() => { host.innerHTML = ""; }, 3200);
  }
  window.LKToast = toast;

  /* ---------------- Search palette ---------------- */
  let searchEl = null;
  function openSearch() {
    if (searchEl) { searchEl.classList.add("open"); searchEl.querySelector("input").focus(); return; }
    searchEl = document.createElement("div");
    searchEl.id = "cmdk";
    searchEl.innerHTML =
      '<div class="cmdk-scrim"></div><div class="cmdk-box">' +
      '<div class="cmdk-input">' + icon("search","") + '<input placeholder="Go to page…" /></div>' +
      '<div class="cmdk-list"></div></div>';
    document.body.appendChild(searchEl);
    const style = document.createElement("style");
    style.textContent =
      '#cmdk{position:fixed;inset:0;z-index:80;display:none;}' +
      '#cmdk.open{display:block;}' +
      '#cmdk .cmdk-scrim{position:absolute;inset:0;background:rgba(0,0,0,.35);}' +
      '#cmdk .cmdk-box{position:relative;max-width:520px;margin:80px auto 0;background:var(--panel);border:1px solid var(--border-strong);border-radius:12px;box-shadow:var(--shadow-lg);overflow:hidden;}' +
      '#cmdk .cmdk-input{display:flex;align-items:center;gap:10px;padding:13px 15px;border-bottom:1px solid var(--border);color:var(--muted);}' +
      '#cmdk .cmdk-input svg{width:22px;height:22px;flex:none;}' +
      '#cmdk .cmdk-input input{flex:1;border:none;background:transparent;outline:none;font:inherit;font-size:15px;color:var(--text);}' +
      '#cmdk .cmdk-list{max-height:340px;overflow:auto;padding:6px;}' +
      '#cmdk .cmdk-item{display:flex;align-items:center;gap:11px;padding:9px 11px;border-radius:8px;cursor:pointer;font-size:13.5px;color:var(--text-2);}' +
      '#cmdk .cmdk-item .ico{width:15px;height:15px;color:var(--faint);}' +
      '#cmdk .cmdk-item .g{margin-left:auto;font-size:11px;color:var(--faint);}' +
      '#cmdk .cmdk-item.sel,#cmdk .cmdk-item:hover{background:var(--accent-weak);color:var(--accent-text);}' +
      '#cmdk .cmdk-item.sel .ico{color:var(--accent);}';
    document.head.appendChild(style);
    const input = searchEl.querySelector("input");
    const list = searchEl.querySelector(".cmdk-list");
    const flat = [];
    NAV.forEach(g => g.items.forEach(it => flat.push({ ...it, group: g.group })));
    let sel = 0;
    function render(q) {
      const items = flat.filter(it => it.name.toLowerCase().includes(q.toLowerCase()) || it.group.toLowerCase().includes(q.toLowerCase()));
      sel = Math.min(sel, Math.max(0, items.length - 1));
      list.innerHTML = items.map((it, i) =>
        '<a class="cmdk-item ' + (i === sel ? "sel" : "") + '" href="' + it.href + '">' + icon(it.icon) + '<span>' + it.name + '</span><span class="g">' + it.group + '</span></a>'
      ).join("") || '<div style="padding:18px;text-align:center;color:var(--muted);font-size:13px">No matches</div>';
      list._items = items;
    }
    render("");
    input.oninput = () => render(input.value);
    input.onkeydown = (e) => {
      const items = list._items || [];
      if (e.key === "ArrowDown") { sel = Math.min(sel + 1, items.length - 1); render(input.value); e.preventDefault(); }
      else if (e.key === "ArrowUp") { sel = Math.max(sel - 1, 0); render(input.value); e.preventDefault(); }
      else if (e.key === "Enter") { if (items[sel]) location.href = items[sel].href; }
    };
    searchEl.querySelector(".cmdk-scrim").onclick = closeSearch;
    searchEl.classList.add("open"); input.focus();
  }
  function closeSearch() { if (searchEl) searchEl.classList.remove("open"); }

  /* ---------------- Chart hydration ---------------- */
  function hydrate() {
    // volume bar charts: data-values="..." data-x="00:00|06:00|..." data-tipunit="req"
    document.querySelectorAll(".js-bars").forEach(el => {
      const vals = (el.dataset.values || "").split(",").map(Number);
      const labels = (el.dataset.labels || "").split("|");
      const unit = el.dataset.tipunit || "";
      const max = Math.max.apply(null, vals.concat([1]));
      const errIdx = (el.dataset.err || "").split(",").map(Number);
      let bars = vals.map((v, i) => {
        const cls = errIdx.indexOf(i) > -1 ? "err" : (v < max * 0.18 ? "lo" : "");
        const tip = (labels[i] ? labels[i] + " · " : "") + v.toLocaleString() + (unit ? " " + unit : "");
        return '<div class="bar ' + cls + '" style="height:' + Math.max(3, v / max * 100) + '%" data-tip="' + tip + '"></div>';
      }).join("");
      const xl = el.dataset.x ? '<div class="chart-x">' + el.dataset.x.split("|").map(s => "<span>" + s + "</span>").join("") + '</div>' : "";
      el.innerHTML = '<div class="chart">' + bars + '</div>' + xl;
    });
    // sparklines: data-values="..." data-hi="3" (last n highlighted) optional data-color
    document.querySelectorAll(".js-spark").forEach(el => {
      const vals = (el.dataset.values || "").split(",").map(Number);
      const max = Math.max.apply(null, vals.concat([1]));
      const hi = parseInt(el.dataset.hi || "3", 10);
      const color = el.dataset.color;
      el.classList.add("k-spark");
      el.innerHTML = vals.map((v, i) => '<span class="' + (i >= vals.length - hi ? "hi" : "") + '" style="height:' + Math.max(8, v / max * 100) + '%' + (color ? ";background:" + color : "") + '"></span>').join("");
    });
    // histograms: data-values="..." data-x="0|.." data-tail="6,7" data-tail2="8,9"
    document.querySelectorAll(".js-histo").forEach(el => {
      const vals = (el.dataset.values || "").split(",").map(Number);
      const max = Math.max.apply(null, vals.concat([1]));
      const tail = (el.dataset.tail || "").split(",").map(Number);
      const tail2 = (el.dataset.tail2 || "").split(",").map(Number);
      const bars = vals.map((v, i) => {
        const c = tail2.indexOf(i) > -1 ? "tail2" : (tail.indexOf(i) > -1 ? "tail" : "");
        return '<div class="hb ' + c + '" style="height:' + Math.max(2, v / max * 100) + '%" title="' + v + '"></div>';
      }).join("");
      const xl = el.dataset.x ? '<div class="histo-x">' + el.dataset.x.split("|").map(s => "<span>" + s + "</span>").join("") + '</div>' : "";
      el.innerHTML = '<div class="histo">' + bars + '</div>' + xl;
    });
    // stacked status charts: data-stack="g2;g3;g4;g5" each group is comma list per bar
    document.querySelectorAll(".js-stack").forEach(el => {
      const groups = (el.dataset.stack || "").split(";").map(g => g.split(",").map(Number));
      const n = groups[0] ? groups[0].length : 0;
      let totals = [];
      for (let i = 0; i < n; i++) totals[i] = groups.reduce((s, g) => s + (g[i] || 0), 0);
      const max = Math.max.apply(null, totals.concat([1]));
      const labels = (el.dataset.x || "").split("|");
      let bars = "";
      for (let i = 0; i < n; i++) {
        const h = Math.max(3, totals[i] / max * 100);
        bars += '<div class="bar" style="height:' + h + '%" data-tip="' + (labels[i] || "") + " · " + totals[i] + '">' +
          '<i class="s2" style="height:' + (groups[0][i] / totals[i] * 100 || 0) + '%"></i>' +
          '<i class="s3" style="height:' + (groups[1][i] / totals[i] * 100 || 0) + '%"></i>' +
          '<i class="s4" style="height:' + (groups[2][i] / totals[i] * 100 || 0) + '%"></i>' +
          '<i class="s5" style="height:' + (groups[3][i] / totals[i] * 100 || 0) + '%"></i></div>';
      }
      const xl = el.dataset.xlabels ? '<div class="chart-x">' + el.dataset.xlabels.split("|").map(s => "<span>" + s + "</span>").join("") + '</div>' : "";
      el.innerHTML = '<div class="chart chart-stack">' + bars + '</div>' + xl;
    });
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mount);
  else mount();
})();
