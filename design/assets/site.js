/* Shared behaviour for every page. Each module is a no-op when its markup is absent. */
(() => {
  const $ = (q, r = document) => r.querySelector(q);
  const $$ = (q, r = document) => [...r.querySelectorAll(q)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fa = n => Number(n).toLocaleString('fa-IR');

  /* ---------- header state + reading progress ---------- */
  const hdr = $('#hdr'), bar = $('.progress');
  const onScroll = () => {
    const y = scrollY, h = document.documentElement.scrollHeight - innerHeight;
    hdr && hdr.classList.toggle('scrolled', y > 24);
    if (bar) bar.style.transform = `scaleX(${h > 0 ? y / h : 0})`;
  };
  addEventListener('scroll', onScroll, {passive:true}); onScroll();

  /* ---------- active nav item (set by <body data-page>) ---------- */
  const page = document.body.dataset.page;
  $$('[data-nav]').forEach(a => { if (a.dataset.nav === page) a.setAttribute('aria-current', 'page'); });

  /* ---------- services mega menu ---------- */
  const megaBtn = $('.mega-btn'), mega = $('#mega');
  if (megaBtn && mega) {
    const wrap = megaBtn.parentElement;
    const set = open => { wrap.classList.toggle('open', open); megaBtn.setAttribute('aria-expanded', String(open)); };
    megaBtn.addEventListener('click', () => set(!wrap.classList.contains('open')));
    wrap.addEventListener('mouseenter', () => matchMedia('(hover:hover)').matches && set(true));
    wrap.addEventListener('mouseleave', () => matchMedia('(hover:hover)').matches && set(false));
    wrap.addEventListener('focusout', e => { if (!wrap.contains(e.relatedTarget)) set(false); });
    addEventListener('keydown', e => { if (e.key === 'Escape' && wrap.classList.contains('open')) { set(false); megaBtn.focus(); } });
  }

  /* ---------- mobile drawer ---------- */
  const drawer = $('#drawer'), burger = $('#burger'), closeBtn = $('#drawerClose');
  if (drawer && burger && closeBtn) {
    const setDrawer = open => {
      drawer.classList.toggle('open', open);
      drawer.setAttribute('aria-hidden', String(!open));
      burger.setAttribute('aria-expanded', String(open));
      (open ? closeBtn : burger).focus();
    };
    burger.onclick = () => setDrawer(true);
    closeBtn.onclick = () => setDrawer(false);
    drawer.addEventListener('click', e => { if (e.target === drawer || e.target.closest('a')) setDrawer(false); });
    addEventListener('keydown', e => { if (e.key === 'Escape' && drawer.classList.contains('open')) setDrawer(false); });
  }

  /* ---------- reveal on scroll ---------- */
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), {threshold:.12, rootMargin:'0px 0px -40px 0px'});
  $$('[data-reveal], .report').forEach(el => io.observe(el));

  /* ---------- counters ---------- */
  const cio = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    cio.unobserve(e.target);
    const el = e.target, to = +el.dataset.count, suf = el.dataset.suffix || '';
    if (reduce) { el.textContent = fa(to) + suf; return; }
    const t0 = performance.now(), dur = 1400;
    const tick = t => {
      const p = Math.min(1, (t - t0) / dur), v = Math.round(to * (1 - Math.pow(1 - p, 4)));
      el.textContent = fa(v) + (p === 1 ? suf : '');
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }), {threshold:.6});
  $$('[data-count]').forEach(el => cio.observe(el));

  /* ---------- hero: before/after system map ---------- */
  const map = $('#map');
  if (map) {
    const thumb = $('.thumb', map), legend = $('#legend');
    const btns = $$('.seg button', map);
    const legends = {
      before:'<span><i style="background:#E0874B"></i>همه‌ی مسیرها از مدیر می‌گذرد</span><span><i style="background:#6B7A94"></i>ارتباط‌های غیررسمی</span>',
      after:'<span><i style="background:#E6C98E"></i>فرآیندهای تعریف‌شده</span><span><i style="background:#6FD3BD"></i>جریان داده‌ی خودکار</span>'
    };
    let touched = false;
    const setState = s => {
      map.dataset.state = s;
      btns.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.set === s)));
      const on = btns.find(b => b.dataset.set === s);
      thumb.style.width = on.offsetWidth + 'px';
      thumb.style.left = on.offsetLeft + 'px';
      legend.innerHTML = legends[s];
    };
    btns.forEach(b => b.onclick = () => { touched = true; setState(b.dataset.set); });
    if (reduce) setState('after');
    else {
      setState('before');
      const mio = new IntersectionObserver(es => { if (es[0].isIntersecting) { mio.disconnect(); setTimeout(() => { if (!touched) setState('after'); }, 2400); } }, {threshold:.5});
      mio.observe(map);
    }
    addEventListener('resize', () => setState(map.dataset.state));
    document.fonts && document.fonts.ready.then(() => setState(map.dataset.state));
  }

  /* ---------- self-diagnosis ---------- */
  const syms = $$('.sym');
  if (syms.length) {
    const arc = $('#arc'), score = $('#score'), vT = $('#vTitle'), vX = $('#vText');
    const levels = [
      ['هنوز انتخابی نکرده‌اید','روی کارت‌ها بزنید؛ با هر انتخاب، نتیجه اینجا به‌روز می‌شود.','#6FD3BD'],
      ['زمان مناسب برای پیشگیری','چند نشانه‌ی اولیه دیده می‌شود؛ حالا ساده‌ترین زمان برای مستندسازی فرآیندهاست.','#6FD3BD'],
      ['نیاز جدی به سیستم‌سازی','گلوگاه‌ها در حال تبدیل شدن به سقف رشد هستند. عارضه‌یابی ساختاریافته توصیه می‌شود.','#E6C98E'],
      ['رشد شما قفل شده است','سازمان به افراد وابسته است و داده‌ها پراکنده‌اند. معماری فرآیند اولویت اول است.','#E0874B']
    ];
    const update = () => {
      const n = syms.filter(s => s.getAttribute('aria-pressed') === 'true').length;
      const lv = n === 0 ? 0 : n <= 2 ? 1 : n <= 4 ? 2 : 3;
      score.textContent = fa(n);
      arc.style.strokeDashoffset = 100 - (n / syms.length) * 100;
      arc.style.stroke = levels[lv][2];
      vT.textContent = levels[lv][0]; vX.textContent = levels[lv][1];
      $('#cnt').textContent = fa(n);
      $('#tally').classList.toggle('has', n > 0);
      $('#verdict').classList.toggle('has', n > 0);
    };
    syms.forEach(s => s.onclick = () => {
      syms.forEach(x => x.classList.remove('hint'));
      s.setAttribute('aria-pressed', String(s.getAttribute('aria-pressed') !== 'true'));
      update();
    });
    $('#reset').onclick = () => { syms.forEach(s => s.setAttribute('aria-pressed', 'false')); update(); };
  }

  /* ---------- method: scroll-driven progress ---------- */
  const steps = $$('.step'), list = $('#steps');
  if (steps.length && list) {
    const rail = $('.steps .rail'), phases = $$('#phases span');
    let ticking = false;
    const onMethod = () => {
      ticking = false;
      const mid = innerHeight * .55;
      let active = -1;
      steps.forEach((s, i) => { if (s.getBoundingClientRect().top < mid) active = i; });
      steps.forEach((s, i) => { s.classList.toggle('on', i === active); s.classList.toggle('done', i < active); });
      const r = list.getBoundingClientRect();
      rail.style.setProperty('--p', Math.max(0, Math.min(1, (mid - r.top) / r.height)).toFixed(3));
      const ph = active >= 0 ? steps[active].dataset.ph : '1';
      phases.forEach(p => p.classList.toggle('on', p.dataset.ph === ph));
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onMethod); } }, {passive:true});
    onMethod();
  }

  /* ---------- marquee pause control (WCAG 2.2.2) ---------- */
  const mq = $('#marquee'), mqBtn = $('#mqBtn');
  if (mq && mqBtn) {
    const iconPlay = '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M4 2.5v9l7-4.5z" fill="currentColor"/></svg>';
    const iconPause = mqBtn.innerHTML;
    mqBtn.onclick = () => {
      const p = mq.classList.toggle('paused');
      mqBtn.setAttribute('aria-pressed', String(p));
      mqBtn.setAttribute('aria-label', p ? 'ادامه‌ی حرکت فهرست' : 'توقف حرکت فهرست');
      mqBtn.innerHTML = p ? iconPlay : iconPause;
    };
    mq.addEventListener('mouseenter', () => mq.classList.add('paused'));
    mq.addEventListener('mouseleave', () => { if (mqBtn.getAttribute('aria-pressed') !== 'true') mq.classList.remove('paused'); });
  }

  /* ---------- book: 3D tilt ---------- */
  $$('.stage').forEach(stage => {
    const cover = $('.cover3d', stage);
    if (!cover || reduce || !matchMedia('(hover:hover)').matches) return;
    stage.addEventListener('pointermove', e => {
      const r = stage.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      cover.style.transform = `rotateY(${-18 + x * 22}deg) rotateX(${4 - y * 12}deg)`;
    });
    stage.addEventListener('pointerleave', () => cover.style.transform = '');
  });

  /* ---------- article: table of contents highlight ---------- */
  const toc = $$('.toc a');
  if (toc.length) {
    const heads = toc.map(a => document.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);
    const onToc = () => {
      let cur = heads[0];
      heads.forEach(h => { if (h.getBoundingClientRect().top < 140) cur = h; });
      toc.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + cur.id));
    };
    addEventListener('scroll', onToc, {passive:true}); onToc();
  }

  /* ---------- copy link ---------- */
  $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(location.href); b.dataset.done = '1'; setTimeout(() => delete b.dataset.done, 1800); } catch {}
  }));

  /* ---------- filter chips (blog / projects / faq) ---------- */
  $$('[data-filter-group]').forEach(group => {
    const target = $(group.dataset.filterGroup);
    const chips = $$('[data-filter]', group);
    const search = $('[data-filter-search]', group.parentElement);
    const empty = $('.filter-empty', target.parentElement);
    let active = 'all', q = '';
    const apply = () => {
      let shown = 0;
      $$('[data-cat]', target).forEach(it => {
        const ok = (active === 'all' || it.dataset.cat.split(' ').includes(active)) && (!q || it.textContent.includes(q));
        it.hidden = !ok; if (ok) shown++;
      });
      if (empty) empty.hidden = shown > 0;
    };
    chips.forEach(c => c.addEventListener('click', () => {
      active = c.dataset.filter;
      chips.forEach(x => x.setAttribute('aria-pressed', String(x === c)));
      apply();
    }));
    search && search.addEventListener('input', () => { q = search.value.trim(); apply(); });
  });

  /* ---------- Strategic DNA canvas: autosave in this browser + print ---------- */
  const canvas = $('#dnaCanvas');
  if (canvas) {
    const KEY = 'fk-dna-canvas', fields = $$('textarea', canvas), saved = $('#canvasSaved');
    const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch { return {}; } };
    const data = read();
    fields.forEach(f => { if (data[f.id]) f.value = data[f.id]; });
    let t;
    canvas.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const d = {}; fields.forEach(f => { if (f.value.trim()) d[f.id] = f.value; });
        try { localStorage.setItem(KEY, JSON.stringify(d)); saved.textContent = 'ذخیره شد'; } catch { saved.textContent = ''; }
        setTimeout(() => saved.textContent = '', 1600);
      }, 400);
    });
    $('#canvasClear').onclick = () => {
      fields.forEach(f => f.value = '');
      try { localStorage.removeItem(KEY); } catch {}
      fields[0].focus();
    };
    $('#canvasPrint').onclick = () => {
      document.documentElement.classList.add('print-canvas');
      window.print();
      setTimeout(() => document.documentElement.classList.remove('print-canvas'), 500);
    };
  }

  /* ---------- assessment wizard ---------- */
  const wiz = $('#wizard');
  if (wiz) {
    const panes = $$('.wz-pane', wiz), dots = $$('.wz-steps li', wiz);
    const back = $('#wzBack'), next = $('#wzNext'), bar = $('.wz-bar i', wiz);
    let i = 0;
    const show = n => {
      i = n;
      panes.forEach((p, k) => p.hidden = k !== i);
      dots.forEach((d, k) => { d.classList.toggle('on', k === i); d.classList.toggle('done', k < i); if (k === i) d.setAttribute('aria-current', 'step'); else d.removeAttribute('aria-current'); });
      bar.style.width = ((i + 1) / panes.length * 100) + '%';
      back.hidden = i === 0 || i === panes.length - 1;
      next.hidden = i === panes.length - 1;
      next.textContent = i === panes.length - 2 ? 'ارسال و دریافت نتیجه' : 'مرحله‌ی بعد';
      const f = $('input, textarea, select, button.opt', panes[i]); f && f.focus({preventScroll:true});
    };
    const valid = () => {
      const req = $$('[required]', panes[i]);
      const bad = req.find(f => !f.value.trim());
      $$('.err', panes[i]).forEach(e => e.hidden = true);
      if (bad) { const e = $('#' + bad.getAttribute('aria-describedby'), wiz); if (e) e.hidden = false; bad.focus(); return false; }
      return true;
    };
    $$('.opt', wiz).forEach(o => o.addEventListener('click', () => {
      if (o.closest('[data-single]')) $$('.opt', o.parentElement).forEach(x => x.setAttribute('aria-pressed', 'false'));
      o.setAttribute('aria-pressed', String(o.getAttribute('aria-pressed') !== 'true'));
    }));
    next.onclick = () => { if (valid()) show(Math.min(i + 1, panes.length - 1)); wiz.scrollIntoView({block:'start', behavior: reduce ? 'auto' : 'smooth'}); };
    back.onclick = () => show(Math.max(i - 1, 0));
    show(0);
  }
})();
