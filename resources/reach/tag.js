/* AdsRanger Reach web tag (FR-REACH R-2). Async, no cookies, < 5 KB.
   Usage: <div data-reach="rw_…" data-placement="sidebar" style="min-height:160px"></div>
          <script async src="https://adsranger.com/reach/v1/tag.js"></script>
   0.00 CLS: the box keeps the height the page reserved for it; offers render inside and scroll if longer. */
(function (d, w) {
  var s = d.currentScript, base = s ? s.src.replace(/\/reach\/v1\/tag\.js.*$/, '') : '';
  if (!base || w.innerWidth < 2 || w.innerHeight < 2) return; // 0x0 iframe guard: never serve or count
  var ar = (d.documentElement.lang || '').slice(0, 2) === 'ar';
  var T = ar ? { go: 'احصل على العرض', copy: 'انسخ الكود', copied: 'تم النسخ', ad: 'عروض برعاية', join: 'انضم واربح 5%' }
             : { go: 'Get offer', copy: 'Copy code', copied: 'Copied', ad: 'Sponsored offers', join: 'Join, earn 5%' };
  function el(t, css, txt) { var n = d.createElement(t); if (css) n.style.cssText = css; if (txt) n.textContent = txt; return n; }
  function post(path, body) {
    return fetch(base + path, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
  }
  var skim = {};
  function render(box, key, place, j) {
    var h = box.offsetHeight;
    box.style.height = h + 'px'; box.style.overflow = 'auto'; box.style.boxSizing = 'border-box';
    var wrap = el('div', 'font:14px/1.45 system-ui,sans-serif;padding:8px');
    wrap.setAttribute('dir', ar ? 'rtl' : 'ltr');
    wrap.appendChild(el('div', 'font-size:11px;opacity:.65;margin-bottom:6px', T.ad));
    (j.offers || []).forEach(function (o) {
      var row = el('div', 'display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:space-between;padding:6px 0;border-top:1px solid rgba(127,127,127,.2)');
      row.appendChild(el('span', 'flex:1 1 140px', o.title));
      var a = el('a', 'padding:6px 10px;border-radius:6px;background:#0f766e;color:#fff;text-decoration:none;min-height:32px', T.go);
      a.href = o.click; a.target = '_blank'; a.rel = 'sponsored noopener';
      row.appendChild(a);
      if (o.has_code) {
        var b = el('button', 'padding:6px 10px;border-radius:6px;border:1px solid #0f766e;background:none;color:inherit;cursor:pointer;min-height:32px', T.copy);
        b.type = 'button';
        b.onclick = function () {
          post('/reach/v1/copy', { w: key, o: o.id, p: place, t: o.t }).then(function (r) {
            if (!r.ok || !r.code) return;
            b.textContent = r.code + ' · ' + T.copied;
            if (navigator.clipboard) navigator.clipboard.writeText(r.code).catch(function () {});
          });
        };
        row.appendChild(b);
      }
      wrap.appendChild(row);
    });
    if (j.badge) {
      var j5 = el('a', 'display:inline-block;margin-top:6px;font-size:11px;opacity:.7;color:inherit', T.join);
      j5.href = base + (ar ? '' : '/en') + '/reach?ref=' + encodeURIComponent(j.badge); j5.target = '_blank'; j5.rel = 'noopener';
      wrap.appendChild(j5);
    }
    box.textContent = ''; box.appendChild(wrap);
  }
  function load(box) {
    var key = box.getAttribute('data-reach'), place = box.getAttribute('data-placement') || '';
    if (!/^rw_[a-z0-9]{16}$/.test(key || '') || box.offsetWidth < 2 || box.offsetHeight < 2) return; // hidden box: no view
    fetch(base + '/reach/v1/serve?w=' + key + '&p=' + encodeURIComponent(place) + '&u=' + encodeURIComponent(location.href), { credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) return;
        for (var hst in (j.skim || {})) skim[hst] = j.skim[hst];
        if ((j.offers || []).length) render(box, key, place, j);
      }).catch(function () {});
  }
  // Auto-skim: an outbound link to a merchant we carry goes through its tracked link, only when clicked.
  d.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || a.host === location.host || a.rel.indexOf('sponsored') > -1) return;
    var hst = a.hostname.replace(/^www\./, '');
    if (skim[hst]) { a.href = skim[hst]; a.rel = (a.rel ? a.rel + ' ' : '') + 'sponsored'; }
  }, true);
  function start() { [].forEach.call(d.querySelectorAll('[data-reach]'), load); }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})(document, window);
