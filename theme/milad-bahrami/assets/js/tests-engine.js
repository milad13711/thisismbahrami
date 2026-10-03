/*
 * Shared test engine. A test = JSON config (gates, scale, questions, data) + a renderer.
 *   gates      : profile questions asked first; a gate shows only if its `when` matches the profile so far
 *   questions  : {d: dimension, t: text, when?} — filtered by `when`, then interleaved across dimensions
 *   renderer   : MBTests.register(name, { render(ctx) -> html, after?(root, ctx) })
 * Result is stored in the URL hash (#r=…) so it can be saved/shared; nothing is sent to a server.
 */
(function () {
  'use strict';
  var renderers = {};
  var FA = '۰۱۲۳۴۵۶۷۸۹';
  function fa(n) { return String(n).replace(/\d/g, function (d) { return FA[d]; }); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function matches(when, profile) {
    if (!when) return true;
    for (var k in when) { if (when[k].indexOf(profile[k]) < 0) return false; }
    return true;
  }
  var store = {
    get: function (k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
    del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
  };

  window.MBTests = {
    register: function (name, r) { renderers[name] = r; boot(); },
    fa: fa, esc: esc
  };

  var app, cfg, S, KEY, booted = false;

  function boot() {
    if (booted) return;
    app = document.getElementById('tapp');
    if (!app) return;
    var name = app.getAttribute('data-renderer');
    if (!renderers[name]) return; // renderer script not loaded yet
    booted = true;
    try { cfg = JSON.parse(document.getElementById('tdata').textContent); } catch (e) { app.innerHTML = '<p class="callout">بارگذاری تست ناموفق بود. صفحه را دوباره باز کنید.</p>'; return; }
    KEY = 'mbt:' + cfg.id;
    S = { phase: 'gate', gi: 0, profile: {}, order: [], answers: [], qi: 0 };
    var hashed = parseHash();
    if (hashed) { S.profile = hashed.profile; showResult(hashed.pct); return; }
    var saved = store.get(KEY);
    if (saved && saved.phase === 'quiz' && saved.answers && saved.answers.length) { resumePrompt(saved); return; }
    renderGate();
  }
  document.addEventListener('DOMContentLoaded', boot);
  if (document.readyState !== 'loading') setTimeout(boot, 0);

  /* ---------- helpers ---------- */
  function scrollTop() {
    var y = app.getBoundingClientRect().top + window.pageYOffset - 84;
    window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
  }
  function mount(html, focusSel) {
    app.innerHTML = html;
    var f = app.querySelector(focusSel || '[data-focus]');
    if (f) { f.setAttribute('tabindex', '-1'); try { f.focus({ preventScroll: true }); } catch (e) {} }
  }
  function visibleGates() {
    return cfg.gates.filter(function (g, i, all) {
      // when several gates share an id, only the first one whose `when` matches counts
      return matches(g.when, S.profile) && all.findIndex(function (o) { return o.id === g.id && matches(o.when, S.profile); }) === i;
    });
  }
  function save() { store.set(KEY, { phase: S.phase, profile: S.profile, order: S.order, answers: S.answers, qi: S.qi }); }

  /* ---------- gates ---------- */
  function renderGate() {
    S.phase = 'gate';
    var gates = visibleGates(), g = gates[S.gi];
    if (!g) { startQuiz(); return; }
    var opts = g.options.map(function (o) {
      var sel = S.profile[g.id] === o.v;
      return '<button type="button" class="gopt' + (sel ? ' on' : '') + '" data-v="' + esc(o.v) + '"><span class="ge" aria-hidden="true">' + o.e + '</span><span class="gl"><b>' + esc(o.l) + '</b>' + (o.h ? '<small>' + esc(o.h) + '</small>' : '') + '</span></button>';
    }).join('');
    var dots = gates.map(function (x, i) { return '<i' + (i === S.gi ? ' class="on"' : (i < S.gi ? ' class="done"' : '')) + '></i>'; }).join('');
    mount('<div class="tcard-main"><div class="tdots" aria-hidden="true">' + dots + '</div>' +
      '<h2 class="tq-title" data-focus>' + esc(g.title) + '</h2><p class="tq-hint">' + esc(g.hint || '') + '</p>' +
      '<div class="gopts' + (g.options.length > 4 ? ' many' : '') + '" role="group" aria-label="' + esc(g.title) + '">' + opts + '</div>' +
      (S.gi > 0 ? '<div class="tnav"><button type="button" class="tback" data-back>بازگشت</button></div>' : '') + '</div>');
    app.querySelectorAll('.gopt').forEach(function (b) {
      b.addEventListener('click', function () {
        // keep answers of earlier gates only; later ones depend on this choice
        var keep = {}; gates.slice(0, S.gi).forEach(function (x) { keep[x.id] = S.profile[x.id]; });
        keep[g.id] = b.getAttribute('data-v'); S.profile = keep;
        b.classList.add('on');
        setTimeout(function () { S.gi++; renderGate(); scrollTop(); }, 160);
      });
    });
    var back = app.querySelector('[data-back]');
    if (back) back.addEventListener('click', function () { S.gi--; renderGate(); });
  }

  /* ---------- quiz ---------- */
  function startQuiz() {
    var pool = cfg.questions.map(function (q, i) { q.id = i; return q; }).filter(function (q) { return matches(q.when, S.profile); });
    var dims = Object.keys(cfg.dims), by = {};
    dims.forEach(function (d) { by[d] = pool.filter(function (q) { return q.d === d; }); });
    var order = [], more = true, r = 0;
    while (more) {
      more = false;
      dims.forEach(function (d) { if (by[d][r]) { order.push(by[d][r].id); more = true; } });
      r++;
    }
    S.order = order; S.answers = []; S.qi = 0; S.phase = 'quiz';
    save(); renderQuestion(); scrollTop();
  }
  function qById(id) { return cfg.questions[id]; }
  function renderQuestion() {
    var total = S.order.length, q = qById(S.order[S.qi]), cur = S.answers[S.qi];
    var pctDone = Math.round(S.qi / total * 100);
    var opts = cfg.scale.map(function (s, i) {
      return '<button type="button" role="radio" aria-checked="' + (cur === s.v) + '" class="lk lk' + s.v + (cur === s.v ? ' on' : '') + '" data-v="' + s.v + '"><span class="le" aria-hidden="true">' + s.e + '</span><span class="ll">' + esc(s.l) + '</span><kbd aria-hidden="true">' + fa(i + 1) + '</kbd></button>';
    }).join('');
    mount('<div class="tcard-main"><div class="tprog" role="progressbar" aria-valuemin="0" aria-valuemax="' + total + '" aria-valuenow="' + S.qi + '"><i style="width:' + pctDone + '%"></i></div>' +
      '<div class="tcount"><span>سؤال ' + fa(S.qi + 1) + ' از ' + fa(total) + '</span><span>' + fa(pctDone) + '٪</span></div>' +
      '<p class="tq-stem">' + esc(cfg.stem) + '</p><h2 class="tq-q" data-focus>' + esc(q.t) + '</h2>' +
      '<div class="likert" role="radiogroup" aria-label="میزان علاقه">' + opts + '</div>' +
      '<div class="tnav">' + (S.qi > 0 ? '<button type="button" class="tback" data-back>سؤال قبل</button>' : '<button type="button" class="tback" data-gates>تغییر وضعیت</button>') + '<span class="tsave">پیشرفت شما خودکار ذخیره می‌شود</span></div></div>');
    var locked = false;
    app.querySelectorAll('.lk').forEach(function (b) {
      b.addEventListener('click', function () { if (locked) return; locked = true; answer(+b.getAttribute('data-v'), b); });
    });
    var back = app.querySelector('[data-back]');
    if (back) back.addEventListener('click', function () { S.qi--; renderQuestion(); });
    var g = app.querySelector('[data-gates]');
    if (g) g.addEventListener('click', function () { S.gi = 0; S.profile = {}; store.del(KEY); renderGate(); });
  }
  function answer(v, btn) {
    S.answers[S.qi] = v;
    btn.classList.add('on');
    S.qi++; save();
    setTimeout(function () {
      if (S.qi >= S.order.length) { finish(); } else { renderQuestion(); }
    }, 230);
  }
  document.addEventListener('keydown', function (e) {
    if (!app || S.phase !== 'quiz' || e.ctrlKey || e.metaKey || e.altKey) return;
    var n = parseInt(e.key, 10);
    if (n >= 1 && n <= 5) { var b = app.querySelector('.lk' + (n - 1)); if (b) b.click(); }
  });
  function resumePrompt(saved) {
    mount('<div class="tcard-main center"><div class="gbig" aria-hidden="true">👋</div><h2 class="tq-title" data-focus>خوش برگشتید!</h2><p class="tq-hint">تست قبلی شما در سؤال ' + fa(saved.answers.length + 1) + ' از ' + fa(saved.order.length) + ' متوقف شده بود.</p>' +
      '<div class="tbtns"><button type="button" class="btn btn-ink" data-resume>ادامه از همان‌جا</button><button type="button" class="btn btn-line-d" data-fresh>شروع دوباره</button></div></div>');
    app.querySelector('[data-resume]').addEventListener('click', function () {
      S.profile = saved.profile; S.order = saved.order; S.answers = saved.answers; S.qi = saved.answers.length; S.phase = 'quiz'; renderQuestion(); scrollTop();
    });
    app.querySelector('[data-fresh]').addEventListener('click', function () { store.del(KEY); renderGate(); });
  }

  /* ---------- result ---------- */
  function finish() {
    var raw = {}, cnt = {};
    Object.keys(cfg.dims).forEach(function (d) { raw[d] = 0; cnt[d] = 0; });
    S.order.forEach(function (id, i) { var q = qById(id), d = q.d; raw[d] += q.r ? 4 - S.answers[i] : S.answers[i]; cnt[d]++; });
    var pct = {};
    Object.keys(raw).forEach(function (d) { pct[d] = cnt[d] ? Math.round(raw[d] / (cnt[d] * 4) * 100) : 0; });
    store.del(KEY);
    setHash(pct);
    showResult(pct);
  }
  function setHash(pct) {
    var dims = Object.keys(cfg.dims);
    var p = S.profile, parts = [p.status || '', p.track || '', p.goal || ''].concat(dims.map(function (d) { return pct[d]; }));
    try { history.replaceState(null, '', location.pathname + location.search + '#r=' + parts.join('.')); } catch (e) { location.hash = 'r=' + parts.join('.'); }
  }
  function parseHash() {
    var m = /^#r=([a-z]*)\.([a-z]*)\.([a-z]*)\.((?:\d{1,3}\.?){1,12})$/.exec(location.hash);
    if (!m) return null;
    var dims = Object.keys(cfg.dims), nums = m[4].split('.').filter(Boolean).map(Number);
    if (nums.length !== dims.length || nums.some(function (n) { return n < 0 || n > 100; })) return null;
    var L = cfg.labels || { status: {}, track: {} };
    var st = m[1] && L.status[m[1]] ? m[1] : '';
    if (!st && cfg.gates.length) return null;
    var tr = m[2] && L.track[m[2]] ? m[2] : '';
    if (st === 'student' && !tr) return null;
    var pct = {}; dims.forEach(function (d, i) { pct[d] = nums[i]; });
    return { profile: { status: st, track: tr || undefined, goal: m[3] || undefined }, pct: pct };
  }
  function showResult(pct) {
    S.phase = 'result';
    var r = renderers[app.getAttribute('data-renderer')];
    var ctx = { cfg: cfg, profile: S.profile, pct: pct, fa: fa, esc: esc, cta: app.getAttribute('data-cta') };
    var who = S.profile.status ? cfg.labels.status[S.profile.status] + (S.profile.track ? ' · ' + cfg.labels.track[S.profile.track] : '') : '';
    mount('<div class="tres"><div class="tres-top">' + (who ? '<span class="chip light">' + esc(who) + '</span>' : '') + '<h2 class="tres-h" data-focus>کارنامه‌ی شما آماده است</h2></div>' + r.render(ctx) +
      '<div class="tres-actions"><button type="button" class="btn btn-ink" data-print>ذخیره / چاپ نتیجه (PDF)</button><button type="button" class="btn btn-line-d" data-copy>کپی لینک نتیجه</button><button type="button" class="btn btn-line-d" data-retake>انجام دوباره‌ی تست</button></div></div>');
    app.querySelector('[data-print]').addEventListener('click', function () { window.print(); });
    app.querySelector('[data-retake]').addEventListener('click', function () {
      try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
      S = { phase: 'gate', gi: 0, profile: {}, order: [], answers: [], qi: 0 }; store.del(KEY); renderGate(); scrollTop();
    });
    var cp = app.querySelector('[data-copy]');
    cp.addEventListener('click', function () {
      var done = function () { cp.textContent = 'لینک کپی شد ✓'; setTimeout(function () { cp.textContent = 'کپی لینک نتیجه'; }, 2200); };
      if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, function () { window.prompt('لینک را کپی کنید:', location.href); });
      else window.prompt('لینک را کپی کنید:', location.href);
    });
    if (r.after) r.after(app, ctx);
    scrollTop();
  }
})();
