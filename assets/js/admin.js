/* Admin dashboard interactions */
(function () {
  'use strict';
  var CSRF = document.body.dataset.csrf || '';
  function $(s, el) { return (el || document).querySelector(s); }
  function $$(s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); }

  /* Sidebar on mobile */
  var side = $('#sidebar');
  $$('[data-open-side]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.add('side-open'); }); });
  $$('[data-close-side]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.remove('side-open'); }); });

  /* Toast */
  function toast(msg, ok) {
    var area = $('.flash-area');
    var d = document.createElement('div');
    d.className = 'flash ' + (ok ? 'flash-success' : 'flash-error') + ' floating';
    d.textContent = msg;
    document.body.appendChild(d);
    setTimeout(function () { d.classList.add('out'); setTimeout(function () { d.remove(); }, 400); }, 2600);
  }
  setTimeout(function () { $$('.flash-area .flash-success').forEach(function (f) { f.classList.add('out'); setTimeout(function () { f.remove(); }, 400); }); }, 4000);

  /* AJAX save for quick-edit rows and stock forms (form[data-ajax]) */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches || !f.matches('form[data-ajax]')) return;
    e.preventDefault();
    var fd = new FormData(f);
    // include inputs associated via form="id"
    if (f.id) $$('[form="' + f.id + '"]').forEach(function (el) {
      if (!el.name) return;
      if (el.type === 'checkbox') { if (el.checked) fd.set(el.name, el.value); else fd.delete(el.name); }
      else fd.set(el.name, el.value);
    });
    var btn = e.submitter || $('button[type=submit]', f) || (f.id && $('button[form="' + f.id + '"]'));
    if (btn) { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Saving…'; }
    fetch(f.action || location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': CSRF } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        toast(j.msg || (j.ok ? 'Saved' : 'Error'), j.ok);
        var row = f.closest('tr, li') || (f.id && $('[form="' + f.id + '"]') && $('[form="' + f.id + '"]').closest('tr'));
        if (row && j.ok) { row.classList.remove('saved'); void row.offsetWidth; row.classList.add('saved'); }
      })
      .catch(function () { f.submit(); })
      .then(function () { if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label; } });
  });

  /* Mark quick-edit row dirty */
  $$('.product-table input').forEach(function (inp) {
    inp.addEventListener('input', function () { var tr = inp.closest('tr'); if (tr) tr.classList.add('dirty'); });
    inp.addEventListener('change', function () { var tr = inp.closest('tr'); if (tr) tr.classList.add('dirty'); });
  });
  document.addEventListener('submit', function (e) { var tr = e.target.closest && e.target.closest('tr'); if (tr) tr.classList.remove('dirty'); }, true);

  /* Image preview on product edit */
  $$('[data-preview-input]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var box = $('[data-preview="' + inp.dataset.previewInput + '"]');
      if (!inp.files || !inp.files[0] || !box) return;
      var url = URL.createObjectURL(inp.files[0]);
      box.innerHTML = '';
      var img = document.createElement('img'); img.src = url; img.alt = '';
      box.appendChild(img);
    });
  });

  /* Special end presets on product edit */
  function toLocalInput(d) {
    function p(n) { return (n < 10 ? '0' : '') + n; }
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  }
  var endsInput = $('[data-ends-input]');
  $$('[data-ends-preset]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.dataset.endsPreset, d = new Date();
      if (v === 'clear') { endsInput.value = ''; var sp = $('input[name=special_price]'); if (sp) sp.value = ''; return; }
      if (v === 'today') { d.setHours(23, 59, 0, 0); } else { d = new Date(Date.now() + parseInt(v, 10) * 3600 * 1000); }
      endsInput.value = toLocalInput(d);
    });
  });

  /* Specials page: live preview of the deal price */
  var sel = $('[data-price-select]'), mode = $('[data-mode]'), val = $('[data-value]'), prev = $('[data-preview-price]'), lab = $('[data-value-label]');
  function updatePreview() {
    if (!sel || !prev) return;
    var opt = sel.options[sel.selectedIndex];
    var price = opt ? parseFloat(opt.dataset.price || '0') : 0;
    lab.textContent = mode.value === 'percent' ? 'Percent off (%)' : 'Special price (₹)';
    var v = parseFloat(val.value || '0');
    if (!price || !v) { prev.textContent = ''; return; }
    var sp = mode.value === 'percent' ? price * (1 - v / 100) : v;
    var off = Math.round((1 - sp / price) * 100);
    prev.textContent = sp >= price || sp <= 0 ? '⚠ Must be lower than the regular price ₹' + price.toLocaleString('en-IN')
      : 'Customers pay ₹' + sp.toLocaleString('en-IN', { maximumFractionDigits: 2 }) + ' instead of ₹' + price.toLocaleString('en-IN') + ' (' + off + '% off)';
  }
  [sel, mode, val].forEach(function (el) { if (el) { el.addEventListener('input', updatePreview); el.addEventListener('change', updatePreview); } });
  var custom = $('[data-custom-ends]');
  if (custom) {
    custom.addEventListener('focus', function () { var r = $('input[name=preset][value=custom]'); if (r) r.checked = true; });
  }

  /* Copy-to-clipboard for webhook URL */
  $$('[data-copy]').forEach(function (el) {
    el.title = 'Click to copy';
    el.addEventListener('click', function () {
      if (navigator.clipboard) navigator.clipboard.writeText(el.textContent.trim()).then(function () { toast('Copied', true); });
    });
  });

  /* Warn before leaving with unsaved quick edits */
  window.addEventListener('beforeunload', function (e) { if ($('tr.dirty')) { e.preventDefault(); e.returnValue = ''; } });
})();
