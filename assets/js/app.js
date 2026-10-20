/* Storefront interactions — no dependencies. */
(function () {
  'use strict';
  var body = document.body;
  var BASE = body.dataset.base || '/';
  var CSRF = body.dataset.csrf || '';
  var POLL = Math.max(5, parseInt(body.dataset.poll || '15', 10)) * 1000;

  function $(s, el) { return (el || document).querySelector(s); }
  function $$(s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); }

  /* ---------- Toasts ---------- */
  var toastBox = $('.toasts');
  function toast(msg, type) {
    if (!toastBox) return;
    var t = document.createElement('div');
    t.className = 'toast toast-' + (type || 'success');
    t.textContent = msg;
    var b = document.createElement('button');
    b.type = 'button'; b.setAttribute('aria-label', 'Dismiss'); b.textContent = '×';
    b.onclick = function () { t.remove(); };
    t.appendChild(b);
    toastBox.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 3500);
  }
  $$('.toasts .toast').forEach(function (t) { setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 5000); });

  /* ---------- AJAX helper ---------- */
  function postForm(url, data) {
    return fetch(url, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': CSRF }
    }).then(function (r) {
      return r.json().then(function (j) { j._status = r.status; return j; });
    });
  }

  function setCartCount(n) {
    $$('[data-cart-count]').forEach(function (el) {
      el.textContent = n; el.hidden = !n;
      el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
    });
  }

  /* ---------- Drawer menu ---------- */
  var drawer = $('#drawer');
  function openDrawer() { drawer.hidden = false; requestAnimationFrame(function () { drawer.classList.add('open'); }); body.classList.add('no-scroll'); }
  function closeDrawer() { drawer.classList.remove('open'); body.classList.remove('no-scroll'); setTimeout(function () { drawer.hidden = true; }, 250); }
  $$('[data-open-drawer]').forEach(function (b) { b.addEventListener('click', openDrawer); });
  $$('[data-close-drawer]').forEach(function (b) { b.addEventListener('click', closeDrawer); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer && !drawer.hidden) closeDrawer(); });

  /* ---------- Filters auto-submit ---------- */
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  /* ---------- Quantity steppers ---------- */
  $$('[data-qty]').forEach(function (box) {
    var input = $('input', box);
    var timer;
    function clamp() {
      var max = parseInt(input.max || '99', 10), min = parseInt(input.min || '1', 10);
      var v = parseInt(input.value || '0', 10); if (isNaN(v)) v = min;
      input.value = Math.max(min, Math.min(max, v));
    }
    $$('[data-step]', box).forEach(function (b) {
      b.addEventListener('click', function () {
        input.value = (parseInt(input.value || '0', 10) || 0) + parseInt(b.dataset.step, 10);
        clamp();
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    input.addEventListener('change', function () {
      clamp();
      if (box.hasAttribute('data-autoupdate')) { clearTimeout(timer); timer = setTimeout(function () { updateCartLine(box.closest('form')); }, 350); }
    });
  });

  /* ---------- Cart page live updates ---------- */
  function applyCartSummary(j) {
    if (typeof j.count !== 'undefined') setCartCount(j.count);
    var map = { '[data-subtotal]': j.subtotal, '[data-shipping]': j.shipping, '[data-total]': j.total, '[data-cart-items]': j.count };
    Object.keys(map).forEach(function (s) { var el = $(s); if (el && map[s] != null) el.textContent = map[s]; });
    var prob = $('[data-cart-problem]');
    if (prob) prob.hidden = !j.problems;
    var btn = $('[data-checkout-btn]');
    if (btn) { btn.classList.toggle('is-disabled', !!j.problems); if (j.problems) btn.setAttribute('aria-disabled', 'true'); else btn.removeAttribute('aria-disabled'); }
  }
  function updateCartLine(form) {
    var line = form.closest('[data-line]');
    postForm(form.action, new FormData(form)).then(function (j) {
      if (!j.ok && j.msg) toast(j.msg, 'error');
      applyCartSummary(j);
      if (!j.line) { if (line) line.remove(); if (!j.count) location.reload(); return; }
      $('[data-line-total]', line).textContent = j.line.total;
      $('input[name=qty]', line).value = j.line.qty;
      $('[data-line-problem]', line).textContent = j.line.problem || '';
      line.classList.toggle('has-problem', !!j.line.problem);
    }).catch(function () { form.submit(); });
  }
  $$('.remove-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var line = f.closest('[data-line]');
      postForm(f.action, new FormData(f)).then(function (j) {
        line.classList.add('removing');
        setTimeout(function () { line.remove(); if (!j.count) location.reload(); }, 200);
        applyCartSummary(j);
        toast(j.msg || 'Removed');
      }).catch(function () { f.submit(); });
    });
  });
  $$('[data-checkout-btn]').forEach(function (a) {
    a.addEventListener('click', function (e) { if (a.classList.contains('is-disabled')) { e.preventDefault(); toast('Please fix the highlighted items first.', 'error'); } });
  });

  /* ---------- Add to cart (AJAX) ---------- */
  $$('.add-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (e.submitter && e.submitter.hasAttribute('data-buy-now')) return; // normal post -> checkout
      e.preventDefault();
      var btn = $('[data-add-btn]', f);
      var label = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }
      postForm(f.action, new FormData(f)).then(function (j) {
        toast(j.msg, j.ok ? 'success' : 'error');
        if (typeof j.count !== 'undefined') setCartCount(j.count);
        if (btn) { btn.textContent = j.ok ? 'Added ✓' : label; setTimeout(function () { btn.textContent = label; btn.disabled = btn.dataset.out === '1'; }, 1400); }
        poll();
      }).catch(function () { f.submit(); });
    });
  });

  /* ---------- Wishlist (AJAX) ---------- */
  $$('.wish-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      postForm(f.action, new FormData(f)).then(function (j) {
        if (j._status === 401) { location.href = BASE + 'login.php?next=' + encodeURIComponent(location.pathname + location.search); return; }
        var b = $('button', f);
        b.classList.toggle('on', !!j.on);
        b.setAttribute('aria-pressed', j.on ? 'true' : 'false');
        var span = $('span', b); if (span) span.textContent = j.on ? 'Saved to wishlist' : 'Add to wishlist';
        toast(j.msg);
      }).catch(function () { f.submit(); });
    });
  });

  /* ---------- Product gallery ---------- */
  var mainImg = $('#mainImg');
  $$('[data-thumb]').forEach(function (t) {
    t.addEventListener('click', function () {
      mainImg.src = t.dataset.thumb;
      $$('[data-thumb]').forEach(function (x) { x.classList.remove('active'); });
      t.classList.add('active');
    });
  });

  /* ---------- Countdowns ---------- */
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function tickCountdowns() {
    $$('[data-ends]').forEach(function (el) {
      var ms = new Date(el.dataset.ends).getTime() - Date.now();
      var span = $('span', el) || el;
      if (ms <= 0) {
        span.textContent = el.hasAttribute('data-expire-reload') ? 'time is up' : 'Deal ended';
        if (el.hasAttribute('data-expire-reload') && !el.dataset.reloaded) { el.dataset.reloaded = '1'; setTimeout(function () { location.reload(); }, 1500); }
        return;
      }
      var s = Math.floor(ms / 1000), d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
      var txt = (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(sec);
      span.textContent = el.closest('.product-card') ? 'Ends in ' + txt : txt;
    });
  }
  if ($('[data-ends]')) { tickCountdowns(); setInterval(tickCountdowns, 1000); }

  /* ---------- Banner slider ---------- */
  $$('[data-slider]').forEach(function (sl) {
    var track = $('.slides', sl), dots = $$('[data-dot]', sl), n = dots.length, idx = 0, timer;
    if (!n) return;
    function go(i) { idx = (i + n) % n; track.scrollTo({ left: track.clientWidth * idx, behavior: 'smooth' }); mark(); }
    function mark() { dots.forEach(function (d, i) { d.setAttribute('aria-selected', i === idx ? 'true' : 'false'); }); }
    dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); restart(); }); });
    track.addEventListener('scroll', function () { var i = Math.round(track.scrollLeft / track.clientWidth); if (i !== idx) { idx = i; mark(); } }, { passive: true });
    function restart() { clearInterval(timer); timer = setInterval(function () { go(idx + 1); }, 5500); }
    sl.addEventListener('pointerenter', function () { clearInterval(timer); });
    sl.addEventListener('pointerleave', restart);
    mark(); restart();
  });

  /* ---------- Live stock & price polling ---------- */
  function productIds() {
    var ids = {};
    $$('[data-product]').forEach(function (el) { ids[el.dataset.product] = 1; });
    return Object.keys(ids);
  }
  var polling = false;
  function poll() {
    var ids = productIds();
    if (!ids.length || polling || document.hidden) return;
    polling = true;
    fetch(BASE + 'api/stock.php?ids=' + ids.join(','), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.products) applyStock(j.products); })
      .catch(function () {})
      .then(function () { polling = false; });
  }
  function applyStock(map) {
    var cartProblem = false;
    Object.keys(map).forEach(function (id) {
      var d = map[id];
      $$('[data-stock-label="' + id + '"]').forEach(function (el) {
        if (el.textContent !== d.label) { el.textContent = d.label; el.className = 'stock stock-' + d.state + ' changed'; }
      });
      $$('[data-add-btn="' + id + '"]').forEach(function (b) {
        var out = d.state === 'out';
        b.dataset.out = out ? '1' : '0';
        if (b.textContent.indexOf('Add') === 0 || b.textContent === 'Sold out') b.textContent = out ? 'Sold out' : 'Add to cart';
        b.disabled = out;
      });
      $$('[data-buy-now]').forEach(function (b) { if (b.form && b.form.product_id && b.form.product_id.value === id) b.disabled = d.state === 'out'; });
      $$('[data-max-for="' + id + '"]').forEach(function (inp) {
        inp.max = Math.max(1, d.max);
        var line = inp.closest('[data-line]');
        if (line) {
          var over = d.max === 0 || parseInt(inp.value, 10) > d.max;
          var p = $('[data-line-problem]', line);
          if (over) { p.textContent = d.max === 0 ? 'This item just sold out.' : 'Only ' + d.max + ' available — please reduce the quantity.'; cartProblem = true; }
          else if (p.textContent.indexOf('available') > -1 || p.textContent.indexOf('sold out') > -1) p.textContent = '';
          line.classList.toggle('has-problem', over);
        }
      });
      $$('[data-price-for="' + id + '"]').forEach(function (el) {
        if (!d.price) return;
        var b = $('b', el);
        if (b && b.textContent !== d.price) {
          var html = '<b>' + d.price + '</b>' + (d.compare ? ' <s>' + d.compare + '</s>' : '') + (d.off > 0 ? ' <em>' + d.off + '% off</em>' : '');
          el.innerHTML = html; el.classList.add('changed');
        }
      });
      $$('.product-card[data-product="' + id + '"]').forEach(function (c) { c.classList.toggle('is-out', d.state === 'out'); });
    });
    if ($('[data-line]')) {
      var anyProblem = cartProblem || $$('[data-line].has-problem').length > 0;
      var prob = $('[data-cart-problem]'); if (prob) prob.hidden = !anyProblem;
      var btn = $('[data-checkout-btn]'); if (btn) btn.classList.toggle('is-disabled', anyProblem);
    }
  }
  if (productIds().length) {
    setInterval(poll, POLL);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  }

  /* ---------- Prevent double order submit ---------- */
  var co = $('[data-checkout-form]');
  if (co) co.addEventListener('submit', function () {
    var b = $('[data-place-order]', co);
    if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Placing order…'; }, 0); }
  });
})();
