/* SL Distribution admin — progressive enhancement. Every page works without JS;
   this adds dialogs, menus, confirmations, toasts, tooltips, table tools and theming. */
(function () {
  'use strict';
  var d = document, root = d.documentElement, body = d.body;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  /* ---------- Theme ---------- */
  function setTheme(t) {
    root.setAttribute('data-theme', t);
    try { localStorage.setItem('erp-theme', t); } catch (e) {}
    $$('[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', t === 'dark'); b.title = t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'; });
  }
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); });
  });
  setTheme(root.getAttribute('data-theme') || 'light');

  /* ---------- Mobile sidebar ---------- */
  $$('[data-nav-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var o = body.classList.toggle('nav-open'); b.setAttribute('aria-expanded', o); });
  });
  var scrim = $('.scrim');
  if (scrim) scrim.addEventListener('click', function () { body.classList.remove('nav-open'); });

  /* ---------- Menus (<details class="menu">) ---------- */
  function closeMenus(except) { $$('details.menu[open]').forEach(function (m) { if (m !== except) m.removeAttribute('open'); }); }
  $$('details.menu').forEach(function (m) {
    m.addEventListener('toggle', function () {
      if (!m.open) return;
      closeMenus(m);
      var pop = $('.menu-pop', m); if (!pop) return;
      pop.classList.remove('up');
      if (m.closest('.table-wrap')) {
        // Inside a scroll container: escape the overflow clip by positioning against the viewport.
        var t = m.querySelector('summary').getBoundingClientRect();
        pop.style.position = 'fixed'; pop.style.right = 'auto';
        pop.style.left = Math.max(8, Math.min(t.right - pop.offsetWidth, window.innerWidth - pop.offsetWidth - 8)) + 'px';
        var below = t.bottom + 6, h = pop.offsetHeight;
        pop.style.top = (below + h > window.innerHeight - 8 && t.top - h - 6 > 8 ? t.top - h - 6 : below) + 'px';
        return;
      }
      var r = pop.getBoundingClientRect();
      if (r.bottom > window.innerHeight - 8 && r.height < m.getBoundingClientRect().top) pop.classList.add('up');
    });
  });
  d.addEventListener('click', function (e) { if (!e.target.closest('details.menu')) closeMenus(); });

  /* ---------- Dialogs ---------- */
  function openDialog(dlg) {
    if (!dlg) return;
    closeMenus();
    if (typeof dlg.showModal === 'function') { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    var f = $('[autofocus], input:not([type=hidden]):not([disabled]), select, textarea', dlg); if (f) setTimeout(function () { f.focus(); }, 30);
  }
  function closeDialog(dlg) { if (dlg && dlg.open) { dlg.close ? dlg.close() : dlg.removeAttribute('open'); } }
  d.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); openDialog(d.getElementById(o.getAttribute('data-open'))); return; }
    var c = e.target.closest('[data-close]');
    if (c) { e.preventDefault(); closeDialog(c.closest('dialog')); }
  });
  $$('dialog.modal-x').forEach(function (dlg) {
    dlg.addEventListener('click', function (e) { if (e.target === dlg) closeDialog(dlg); }); // backdrop
    if (dlg.hasAttribute('data-autoshow')) openDialog(dlg);
  });
  if (location.hash.length > 1) {
    var hd = d.getElementById(location.hash.slice(1));
    if (hd && hd.matches('dialog.modal-x') && !$('dialog[open]')) openDialog(hd);
    if (hd && hd.matches('tr')) { // deep link to a row: highlight it and open its details
      hd.classList.add('row-flash'); var ex = $('[data-expand]', hd); if (ex && ex.getAttribute('aria-expanded') !== 'true') ex.click();
      setTimeout(function () { hd.scrollIntoView({ block: 'center' }); }, 50);
    }
  }
  // ?new=1 opens the page's primary "create" dialog (used by dashboard tasks and next-step links).
  if (/[?&]new=1(&|$)/.test(location.search) && !$('dialog[open]')) {
    var nb = $('.page-head .actions .btn-primary[data-open]') || $('.page-head .actions [data-open]');
    if (nb) openDialog(d.getElementById(nb.getAttribute('data-open')));
  }

  /* ---------- Conditional fields: data-show-if / data-require-if="#control:val1,val2" (or :checked / :unchecked) ---------- */
  function condMet(spec) {
    var i = spec.lastIndexOf(':'), ctl = d.querySelector(spec.slice(0, i)), want = spec.slice(i + 1);
    if (!ctl) return true;
    if (ctl.type === 'checkbox') return want === 'checked' ? ctl.checked : !ctl.checked;
    return want.split(',').indexOf(ctl.value) > -1;
  }
  function applyConds() {
    $$('[data-show-if]').forEach(function (el) {
      var on = condMet(el.getAttribute('data-show-if')); el.hidden = !on;
      $$('input, select, textarea', el).forEach(function (f) { f.disabled = !on; });
    });
    $$('[data-require-if]').forEach(function (el) {
      var on = condMet(el.getAttribute('data-require-if')); el.required = on;
      var lab = el.id && d.querySelector('label[for="' + el.id + '"] .req'); if (lab) lab.hidden = !on;
    });
  }
  d.addEventListener('change', applyConds); applyConds();

  /* ---------- Live order total (inputs with data-price / data-tax) ---------- */
  $$('[data-order-total]').forEach(function (out) {
    var scope = out.closest('form'), fmt = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
    var calc = function () {
      var sum = 0, items = 0;
      $$('input[data-price]', scope).forEach(function (i) { var q = parseInt(i.value, 10) || 0; if (q > 0) { items++; sum += q * parseFloat(i.dataset.price) * (1 + parseFloat(i.dataset.tax || 0) / 100); } });
      out.textContent = fmt.format(sum); var c = $('[data-order-count]', scope); if (c) c.textContent = items + (items === 1 ? ' product' : ' products');
    };
    scope.addEventListener('input', calc); calc();
  });

  /* ---------- Confirmation dialog (replaces window.confirm) ---------- */
  var cdlg = d.getElementById('confirm-dlg'), pending = null;
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.hasAttribute('data-confirm') || f.__confirmed) return;
    e.preventDefault();
    if (!cdlg) { if (confirm(f.getAttribute('data-confirm'))) { f.__confirmed = true; submitWith(f, e.submitter); } return; }
    pending = { form: f, submitter: e.submitter };
    var danger = f.getAttribute('data-tone') === 'danger';
    $('[data-c-title]', cdlg).textContent = f.getAttribute('data-confirm-title') || 'Please confirm';
    $('[data-c-text]', cdlg).textContent = f.getAttribute('data-confirm');
    $('.confirm-ic', cdlg).classList.toggle('danger', danger);
    var ok = $('[data-c-ok]', cdlg);
    ok.textContent = f.getAttribute('data-confirm-ok') || 'Confirm';
    ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
    openDialog(cdlg);
    setTimeout(function () { ok.focus(); }, 40);
  }, true);
  if (cdlg) {
    $('[data-c-ok]', cdlg).addEventListener('click', function () {
      closeDialog(cdlg);
      if (pending) { pending.form.__confirmed = true; submitWith(pending.form, pending.submitter); pending = null; }
    });
    cdlg.addEventListener('close', function () { pending = null; });
  }
  function submitWith(f, s) { if (f.requestSubmit) { s && s.form === f ? f.requestSubmit(s) : f.requestSubmit(); } else { f.submit(); } }

  /* ---------- Validation feedback + busy buttons ---------- */
  d.addEventListener('invalid', function (e) { var f = e.target.form; if (f) f.classList.add('was-validated'); }, true);
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented) return;
    var btn = e.submitter || $('button[type=submit], button:not([type])', f);
    if ((f.method || 'get').toLowerCase() === 'get') { startLoading(); return; }
    setTimeout(function () {
      $$('button[type=submit], button:not([type])', f).forEach(function (b) { b.disabled = true; });
      if (btn && !btn.querySelector('.spin')) { var s = d.createElement('span'); s.className = 'spin'; s.setAttribute('aria-hidden', 'true'); btn.insertBefore(s, btn.firstChild); }
    });
  });

  /* ---------- Toasts ---------- */
  var stack = $('.toasts');
  function toast(msg, kind) {
    if (!stack || !msg) return;
    var t = d.createElement('div'); t.className = 'toast-x' + (kind === 'error' ? ' err' : ''); t.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    t.innerHTML = '<svg class="ic t-ic"><use href="#i-' + (kind === 'error' ? 'alert' : 'check-circle') + '"/></svg><div class="t-msg"></div><button type="button" class="t-close" aria-label="Dismiss">&times;</button>';
    $('.t-msg', t).textContent = msg;
    stack.appendChild(t);
    var kill = function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 220); };
    $('.t-close', t).addEventListener('click', kill);
    setTimeout(kill, kind === 'error' ? 9000 : 5000);
  }
  $$('[data-toast]').forEach(function (n) { toast(n.getAttribute('data-toast'), n.getAttribute('data-kind')); });
  window.erpToast = toast;

  /* ---------- Tooltips ---------- */
  var tip = d.createElement('div'); tip.className = 'tip'; tip.setAttribute('role', 'tooltip'); body.appendChild(tip);
  function showTip(el, x, y) {
    var parts = (el.getAttribute('data-tip') || '').split('|');
    tip.textContent = '';
    var b = d.createElement('b'); b.textContent = parts[0]; tip.appendChild(b);
    for (var i = 1; i < parts.length; i++) { tip.appendChild(d.createElement('br')); tip.appendChild(d.createTextNode(parts[i])); }
    tip.classList.add('show');
    var r = tip.getBoundingClientRect();
    if (x === undefined) { var er = el.getBoundingClientRect(); x = er.left + er.width / 2; y = er.top; }
    var left = Math.min(Math.max(8, x - r.width / 2), window.innerWidth - r.width - 8);
    var top = y - r.height - 10; if (top < 8) top = y + 18;
    tip.style.left = left + 'px'; tip.style.top = top + 'px';
  }
  function hideTip() { tip.classList.remove('show'); }
  d.addEventListener('mousemove', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); el ? showTip(el, e.clientX, e.clientY) : hideTip(); });
  d.addEventListener('focusin', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); if (el) showTip(el); });
  d.addEventListener('focusout', hideTip);
  window.addEventListener('scroll', function (e) {
    hideTip();
    if (!(e.target.closest && e.target.closest('.menu-pop'))) $$('.table-wrap details.menu[open]').forEach(function (m) { m.removeAttribute('open'); });
  }, true);

  /* ---------- Table search + sort ---------- */
  $$('[data-table-search]').forEach(function (inp) {
    var table = d.querySelector(inp.getAttribute('data-table-search')); if (!table) return;
    var none = $('tr.no-results', table);
    inp.addEventListener('input', function () {
      var q = inp.value.trim().toLowerCase(), shown = 0;
      $$('tbody > tr:not(.no-results):not(.subrow)', table).forEach(function (tr) {
        var hit = !q || tr.textContent.toLowerCase().indexOf(q) > -1; tr.hidden = !hit; if (hit) shown++;
        var sub = tr.nextElementSibling; if (sub && sub.classList.contains('subrow') && !hit) sub.hidden = true;
      });
      if (none) none.classList.toggle('show', shown === 0);
    });
  });
  $$('th[data-sort]').forEach(function (th) {
    th.tabIndex = 0;
    var go = function () {
      var table = th.closest('table'), tb = table.tBodies[0], idx = Array.prototype.indexOf.call(th.parentNode.children, th);
      var dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
      $$('th[data-sort]', table).forEach(function (h) { h.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', dir);
      var num = th.getAttribute('data-sort') === 'num';
      var rows = $$(':scope > tr:not(.no-results):not(.subrow)', tb);
      var val = function (tr) { var c = tr.children[idx]; if (!c) return ''; var v = c.getAttribute('data-v') || c.textContent.trim(); return num ? parseFloat(v.replace(/[^0-9.\-]/g, '')) || 0 : v.toLowerCase(); };
      rows.sort(function (a, b) { var x = val(a), y = val(b); return (x > y ? 1 : x < y ? -1 : 0) * (dir === 'ascending' ? 1 : -1); });
      rows.forEach(function (r) { var sub = r.nextElementSibling && r.nextElementSibling.classList.contains('subrow') ? r.nextElementSibling : null; tb.appendChild(r); if (sub) tb.appendChild(sub); });
    };
    th.addEventListener('click', go);
    th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
  });

  /* ---------- Expandable rows ---------- */
  d.addEventListener('click', function (e) {
    var t = e.target.closest('[data-expand]'); if (!t) return;
    var row = d.getElementById(t.getAttribute('data-expand')); if (!row) return;
    row.hidden = !row.hidden; t.setAttribute('aria-expanded', !row.hidden);
    var ic = t.querySelector('.chev'); if (ic) ic.style.transform = row.hidden ? '' : 'rotate(90deg)';
  });

  /* ---------- Page loading skeleton ---------- */
  function startLoading() { body.classList.add('is-loading'); }
  d.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (a.target || a.hasAttribute('download') || a.hasAttribute('data-no-load')) return;
    var href = a.getAttribute('href'); if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
    if (a.origin !== location.origin || /\/receipt$|export=/.test(a.href)) return;
    if (a.pathname === location.pathname && a.search === location.search && a.hash) return;
    startLoading(); body.classList.remove('nav-open');
  });
  window.addEventListener('pageshow', function () { body.classList.remove('is-loading'); });

  /* ---------- Keyboard ---------- */
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeMenus(); body.classList.remove('nav-open'); }
    if (e.key === '/' && !/input|textarea|select/i.test(d.activeElement.tagName)) {
      var s = $('[data-table-search], .search-input input'); if (s) { e.preventDefault(); s.focus(); }
    }
  });
})();
