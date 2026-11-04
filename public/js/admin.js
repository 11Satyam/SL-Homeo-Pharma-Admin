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
    var val = ctl.value;
    if (ctl.type === 'radio') { var on = d.querySelector('input[type=radio][name="' + ctl.name + '"]:checked'); val = on ? on.value : ''; }
    return want.split(',').indexOf(val) > -1;
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
    var set = function (sel, v) { var el = $(sel, scope); if (el) el.textContent = v; };
    var calc = function () {
      var sub = 0, gst = 0, items = 0, units = 0;
      $$('input[data-price]', scope).forEach(function (i) {
        var q = parseInt(i.value, 10) || 0, net = q * parseFloat(i.dataset.price), tax = net * parseFloat(i.dataset.tax || 0) / 100;
        var lt = i.closest('.qty-item') && $('[data-line-total]', i.closest('.qty-item'));
        if (lt) lt.textContent = q > 0 ? '= ' + fmt.format(net + tax) : '';
        if (q > 0) { items++; units += q; sub += net; gst += tax; }
      });
      out.textContent = fmt.format(sub + gst); set('[data-order-sub]', fmt.format(sub)); set('[data-order-gst]', fmt.format(gst));
      set('[data-order-count]', items + (items === 1 ? ' product' : ' products')); set('[data-order-units]', units ? units + (units === 1 ? ' unit' : ' units') : '');
    };
    scope.addEventListener('input', calc); calc();
  });

  /* ---------- New order: live free stock at the chosen shop's distributor + product filters ---------- */
  $$('[data-order-stock]').forEach(function (el) {
    var data = JSON.parse(el.textContent), url = el.getAttribute('data-url'), form = el.closest('form'), dlg = form.closest('dialog');
    var shop = $('select[name="shop_id"]', form), submit = $('button[type=submit]', form);
    var help = $('[data-stock-help]', form), list = $('[data-stock-list]', form), pick = $('[data-stock-pick]', form), link = $('[data-stock-transfer]', form);
    var live = $('[data-stock-live]', form), liveAt = $('[data-stock-at]', form), block = $('[data-order-block]', form);
    var grid = $('.qty-grid', form), none = $('[data-qty-none]', form);
    var inputs = $$('input[data-product]', form), base = link && link.getAttribute('href');
    var fq = $('[data-f-q]', form), fcat = $('[data-f-cat]', form), fgst = $('[data-f-gst]', form), fsort = $('[data-f-sort]', form);
    var chips = $$('[data-chip]', form), chip = '', fetchedAt = 0, busy = null;
    var dist = function () { var o = shop && shop.selectedOptions[0]; return o && o.dataset.dist; };
    var free = function () { var k = dist(); return k ? (data.stock[k] || {}) : null; };
    var qty = function (i) { return parseInt(i.value, 10) || 0; };
    var stamp = function (t, err) {
      if (!live) return;
      live.hidden = !dist(); live.classList.toggle('busy', !!busy); live.classList.toggle('err', !!err);
      var hm = t ? new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
      liveAt.textContent = busy ? 'Checking stock…' : err ? 'Could not refresh stock, showing figures from ' + hm : 'Live stock · updated ' + hm;
    };

    // Status per product: free qty, ordered qty, and which stock bucket it falls in.
    var status = function (i, map) {
      var a = map ? (map[i.dataset.product] || 0) : null, q = qty(i);
      return { a: a, q: q, short: map ? q > a : false, kind: !map ? '' : a <= 0 ? 'out' : a <= (parseInt(i.dataset.reorder, 10) || 0) ? 'low' : 'in' };
    };
    var update = function () {
      var map = free(), short = [];
      if (pick) pick.hidden = !!map;
      inputs.forEach(function (i) {
        var item = i.closest('.qty-item'), tag = $('[data-avail]', item), meter = $('[data-meter]', item), s = status(i, map);
        item.classList.toggle('picked', s.q > 0);
        if (!map) { tag.textContent = ''; tag.className = 'avail'; item.classList.remove('short', 'none'); meter.hidden = true; i.removeAttribute('max'); i.setCustomValidity(''); return; }
        tag.textContent = s.a > 0 ? s.a + ' in stock' + (s.q > 0 && !s.short ? ' · ' + (s.a - s.q) + ' left after this order' : '') : 'Out of stock';
        tag.className = 'avail ' + (s.kind === 'out' ? 'bad' : s.kind === 'low' ? 'warn' : 'ok');
        meter.hidden = !(s.q > 0 && s.a > 0); meter.className = 'meter ' + (s.short ? 'bad' : s.q / s.a > .8 ? 'warn' : '');
        $('i', meter).style.width = Math.min(100, s.a ? s.q / s.a * 100 : 0) + '%';
        item.classList.toggle('short', s.short); item.classList.toggle('none', s.a <= 0);
        i.max = s.a;
        i.setCustomValidity(s.short ? (s.a > 0 ? 'Only ' + s.a + ' in stock. Lower the quantity.' : 'Out of stock. Clear this quantity or get stock transferred first.') : '');
        if (s.short) short.push({ name: $('b', item).textContent, q: s.q, a: s.a });
      });
      list.textContent = '';
      short.forEach(function (s) { var li = d.createElement('li'); li.textContent = s.name + ': ordered ' + s.q + ', ' + (s.a > 0 ? 'only ' + s.a + ' in stock' : 'none in stock'); list.appendChild(li); });
      help.hidden = !short.length;
      if (block) { block.hidden = !short.length; $('span', block).textContent = short.length + (short.length === 1 ? ' product is' : ' products are') + ' over the stock available. Fix ' + (short.length === 1 ? 'it' : 'them') + ' to confirm the order.'; }
      if (submit) submit.disabled = short.length > 0;
      if (link) { var loc = data.locations[dist()]; link.href = base + (loc ? '&to=' + loc : ''); }
      counts(map);
    };

    // Filters: text, category and GST narrow the list; chips pick a stock bucket; sort reorders the grid.
    var matches = function (i) {
      var item = i.closest('.qty-item'), q = fq ? fq.value.trim().toLowerCase() : '';
      return (!q || item.dataset.search.indexOf(q) > -1) && (!fcat || !fcat.value || item.dataset.cat === fcat.value) && (!fgst || !fgst.value || item.dataset.gst === fgst.value);
    };
    var inChip = function (s, k) { return !k || (k === 'picked' ? s.q > 0 : k === 'short' ? s.short : s.kind === k); };
    var counts = function (map) {
      chips.forEach(function (c) {
        var k = c.dataset.chip, n = 0;
        inputs.forEach(function (i) { if (matches(i) && inChip(status(i, map), k)) n++; });
        $('[data-chip-n]', c).textContent = (k === 'short' && !n) ? '' : n;
        c.disabled = c.hasAttribute('data-needs-shop') && !map;
      });
    };
    var filter = function () {
      var map = free(), shown = 0, sort = fsort ? fsort.value : 'name';
      if (!map && (chip === 'in' || chip === 'low' || chip === 'out')) setChip('');
      var rows = inputs.map(function (i) { return { i: i, item: i.closest('.qty-item'), s: status(i, map) }; });
      rows.forEach(function (r) { var on = matches(r.i) && inChip(r.s, chip); r.item.hidden = !on; if (on) shown++; });
      rows.sort(function (x, y) {
        var p = parseFloat(x.i.dataset.price) - parseFloat(y.i.dataset.price), a = (x.s.a || 0) - (y.s.a || 0);
        var v = sort === 'price' ? p : sort === '-price' ? -p : sort === 'stock' ? -a : sort === 'low' ? a : 0;
        return v || (x.item.dataset.name < y.item.dataset.name ? -1 : 1);
      }).forEach(function (r) { grid.appendChild(r.item); });
      grid.appendChild(none); none.hidden = shown > 0;
      var dirty = chip || (fq && fq.value) || (fcat && fcat.value) || (fgst && fgst.value) || (fsort && fsort.value !== 'name');
      $$('[data-f-reset]', form).forEach(function (b) { if (!b.closest('[data-qty-none]')) b.hidden = !dirty; });
      counts(map);
    };
    var setChip = function (k) { chip = k; chips.forEach(function (c) { var on = c.dataset.chip === k; c.classList.toggle('on', on); c.setAttribute('aria-pressed', on); }); };
    chips.forEach(function (c) { c.addEventListener('click', function () { setChip(chip === c.dataset.chip ? '' : c.dataset.chip); filter(); }); });
    [fq, fcat, fgst, fsort].forEach(function (f) { if (f) f.addEventListener(f.tagName === 'SELECT' ? 'change' : 'input', function (e) { e.stopPropagation(); filter(); }); });
    $$('[data-f-reset]', form).forEach(function (b) { b.addEventListener('click', function () { if (fq) fq.value = ''; if (fcat) fcat.value = ''; if (fgst) fgst.value = ''; if (fsort) fsort.value = 'name'; setChip(''); filter(); }); });
    if (fq) fq.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

    // Live refresh from the server: on shop change, when the dialog opens, every 15s while open, and right before submit.
    var load = function () {
      var o = shop && shop.selectedOptions[0], k = dist();
      if (!url || !k || !window.fetch) return Promise.resolve(false);
      if (busy) return busy;
      stamp(fetchedAt);
      busy = fetch(url + '?shop=' + encodeURIComponent(o.value), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (res) {
          var old = data.stock[res.distributor_id] || {};
          data.stock[res.distributor_id] = res.stock || {};
          if (String(res.distributor_id) === dist()) inputs.forEach(function (i) {
            var p = i.dataset.product, was = old[p] || 0, now = res.stock[p] || 0, item = i.closest('.qty-item');
            if (fetchedAt && was !== now) { item.classList.remove('changed'); void item.offsetWidth; item.classList.add('changed'); }
          });
          fetchedAt = Date.parse(res.at) || Date.now(); busy = null; update(); filter(); stamp(fetchedAt); return true;
        })
        .catch(function () { busy = null; stamp(fetchedAt || Date.parse(data.at), true); return false; });
      return busy;
    };
    $('[data-stock-fit]', form).addEventListener('click', function () {
      var map = free(); if (!map) return;
      inputs.forEach(function (i) { var a = map[i.dataset.product] || 0; if (qty(i) > a) i.value = a > 0 ? a : ''; });
      form.dispatchEvent(new Event('input', { bubbles: true }));
    });
    var refresh = $('[data-stock-refresh]', form); if (refresh) refresh.addEventListener('click', load);
    form.addEventListener('input', update);
    if (shop) shop.addEventListener('change', function () { fetchedAt = 0; update(); filter(); stamp(Date.parse(data.at)); load(); });
    // Re-check against the latest stock before the order is sent; stale figures could let a short order through.
    form.addEventListener('submit', function (e) {
      if (form.__stockChecked || !dist() || Date.now() - fetchedAt < 5000) { form.__stockChecked = false; return; }
      e.preventDefault();
      load().then(function () { update(); if (submit && submit.disabled) { var s = $('.qty-item.short', form); if (s) s.scrollIntoView({ block: 'nearest' }); return; } form.__stockChecked = true; submitWith(form, e.submitter); });
    });
    if (dlg && window.MutationObserver) new MutationObserver(function () { if (dlg.open) load(); }).observe(dlg, { attributes: true, attributeFilter: ['open'] });
    setInterval(function () { if ((!dlg || dlg.open) && !d.hidden) load(); }, 15000);
    d.addEventListener('visibilitychange', function () { if (!d.hidden && (!dlg || dlg.open)) load(); });
    update(); filter(); stamp(Date.parse(data.at));
  });

  /* ---------- New transfer: live free stock at the source (and what the destination already has) ---------- */
  $$('[data-transfer-stock]').forEach(function (el) {
    var data = JSON.parse(el.textContent), url = el.getAttribute('data-url'), form = el.closest('form'), dlg = form.closest('dialog');
    var from = $('select[name="from_location_id"]', form), to = $('select[name="to_location_id"]', form), submit = $('button[type=submit]', form);
    var help = $('[data-stock-help]', form), list = $('[data-stock-list]', form), live = $('[data-stock-live]', form), liveAt = $('[data-stock-at]', form);
    var inputs = $$('input[data-product]', form), fetchedAt = 0, busy = null;
    var name = function (s) { var o = s && s.selectedOptions[0]; return o ? o.textContent.split(' · ')[0] : ''; };
    var stamp = function (t, err) {
      var hm = t ? new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
      live.classList.toggle('busy', !!busy); live.classList.toggle('err', !!err);
      liveAt.textContent = busy ? 'Checking stock…' : err ? 'Could not refresh stock, showing figures from ' + hm : 'Live stock at ' + name(from) + ' · updated ' + hm;
    };
    var update = function () {
      var src = data.stock[from.value] || {}, dst = data.stock[to.value] || {}, short = [];
      inputs.forEach(function (i) {
        var item = i.closest('.qty-item'), p = i.dataset.product, a = src[p] || 0, q = parseInt(i.value, 10) || 0, isShort = q > a;
        var tag = $('[data-avail]', item), dest = $('[data-dest]', item);
        tag.textContent = a > 0 ? a + ' available' + (q > 0 && !isShort ? ' · ' + (a - q) + ' left after' : '') : 'None at ' + name(from);
        tag.className = 'avail ' + (a <= 0 ? 'bad' : isShort ? 'warn' : 'ok');
        dest.textContent = to.value && to.value !== from.value ? name(to) + ' has ' + (dst[p] || 0) + (q > 0 && !isShort ? ' → ' + ((dst[p] || 0) + q) : '') : '';
        item.classList.toggle('picked', q > 0); item.classList.toggle('short', isShort); item.classList.toggle('none', a <= 0);
        i.max = a;
        i.setCustomValidity(isShort ? (a > 0 ? 'Only ' + a + ' available at ' + name(from) + '.' : 'None available at ' + name(from) + '.') : '');
        if (isShort) short.push($('b', item).textContent + ': sending ' + q + ', ' + (a > 0 ? 'only ' + a : 'none') + ' available');
      });
      list.textContent = '';
      short.forEach(function (t) { var li = d.createElement('li'); li.textContent = t; list.appendChild(li); });
      help.hidden = !short.length;
      if (submit) submit.disabled = short.length > 0;
    };
    var load = function (sel) {
      var id = sel.value;
      if (!url || !id || !window.fetch) return Promise.resolve(false);
      if (busy) return busy;
      stamp(fetchedAt);
      busy = fetch(url + '?location=' + encodeURIComponent(id), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (res) { data.stock[res.location_id] = res.stock || {}; if (sel === from) fetchedAt = Date.parse(res.at) || Date.now(); busy = null; update(); stamp(fetchedAt); return true; })
        .catch(function () { busy = null; stamp(fetchedAt || Date.parse(data.at), true); return false; });
      return busy;
    };
    var both = function () { return load(from).then(function () { return load(to); }); };
    form.addEventListener('input', update);
    from.addEventListener('change', function () { fetchedAt = 0; update(); load(from); });
    to.addEventListener('change', function () { update(); load(to); });
    if (dlg && window.MutationObserver) new MutationObserver(function () { if (dlg.open) both(); }).observe(dlg, { attributes: true, attributeFilter: ['open'] });
    setInterval(function () { if ((!dlg || dlg.open) && !d.hidden) load(from); }, 15000);
    update(); stamp(Date.parse(data.at));
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
