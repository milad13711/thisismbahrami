/* RIASEC (Holland) result renderer. Used by tests whose config has renderer:"riasec". */
(function () {
  'use strict';
  var T = window.MBTests;
  var ORDER = ['R', 'I', 'A', 'S', 'E', 'C'];

  function ranking(pct) {
    return ORDER.slice().sort(function (a, b) { return pct[b] - pct[a] || ORDER.indexOf(a) - ORDER.indexOf(b); });
  }
  // How well an item's 3-letter code fits the user's profile (0..100), normalised so the user's own top-3 order scores 100.
  function fit(code, u, rank) {
    var w = [3, 2, 1], m = 0, ideal = 0, i;
    for (i = 0; i < 3; i++) { m += w[i] * (u[code.charAt(i)] || 0); ideal += w[i] * u[rank[i]]; }
    return ideal ? Math.max(0, Math.min(100, Math.round(m / ideal * 100))) : 0;
  }
  function level(p) { return p >= 88 ? ['عالی', 'hi'] : p >= 72 ? ['خوب', 'mid'] : ['متوسط', 'lo']; }

  function radar(cfg, pct) {
    var cx = 180, cy = 165, R = 120, pts = [], rings = '', axes = '', labels = '';
    function pt(i, f) { var a = -Math.PI / 2 + i * Math.PI / 3; return [cx + Math.cos(a) * R * f, cy + Math.sin(a) * R * f]; }
    [0.25, 0.5, 0.75, 1].forEach(function (f) {
      rings += '<polygon points="' + ORDER.map(function (_, i) { return pt(i, f).map(function (n) { return n.toFixed(1); }).join(','); }).join(' ') + '" fill="none" stroke="currentColor" stroke-opacity=".14"/>';
    });
    ORDER.forEach(function (d, i) {
      var e = pt(i, 1), p = pt(i, Math.max(.04, pct[d] / 100)), l = pt(i, 1.2);
      pts.push(p.map(function (n) { return n.toFixed(1); }).join(','));
      axes += '<line x1="' + cx + '" y1="' + cy + '" x2="' + e[0].toFixed(1) + '" y2="' + e[1].toFixed(1) + '" stroke="currentColor" stroke-opacity=".14"/>';
      labels += '<g text-anchor="middle"><text x="' + l[0].toFixed(1) + '" y="' + (l[1] - 2).toFixed(1) + '" font-size="15" font-weight="800" fill="' + cfg.dims[d].color + '">' + d + '</text><text x="' + l[0].toFixed(1) + '" y="' + (l[1] + 14).toFixed(1) + '" font-size="11" fill="currentColor" fill-opacity=".75">' + cfg.dims[d].name + '</text></g>';
    });
    var dots = ORDER.map(function (d, i) { var p = pt(i, Math.max(.04, pct[d] / 100)); return '<circle cx="' + p[0].toFixed(1) + '" cy="' + p[1].toFixed(1) + '" r="4.5" fill="' + cfg.dims[d].color + '" stroke="#fff" stroke-width="1.5"/>'; }).join('');
    return '<svg class="radar" viewBox="0 0 360 330" role="img" aria-label="نمودار شش‌ضلعی علایق شما">' + rings + axes +
      '<polygon points="' + pts.join(' ') + '" fill="#C9A45C" fill-opacity=".28" stroke="#85632A" stroke-width="2.2" stroke-linejoin="round"/>' + dots + labels + '</svg>';
  }

  function typeCard(cfg, d, rankNo) {
    var t = cfg.dims[d];
    return '<article class="rtype" style="--c:' + t.color + '"><header><span class="rl" aria-hidden="true">' + d + '</span><div><small>اولویت ' + T.fa(rankNo) + '</small><h4>' + t.emoji + ' ' + t.name + ' <span>· ' + t.sub + '</span></h4></div></header>' +
      '<p class="lead-s">' + t.lead + '</p><p>' + t.about + '</p>' +
      '<div class="rcols"><div><b>نقاط قوت</b><ul>' + t.strengths.map(function (s) { return '<li>' + s + '</li>'; }).join('') + '</ul></div><div><b>محیط‌های مناسب</b><p>' + t.envs + '</p></div></div>' +
      '<p class="rnote"><b>حواس‌تان باشد:</b> ' + t.watch + '</p><p class="rnote ok"><b>برای رشد:</b> ' + t.grow + '</p></article>';
  }

  function majorCard(m, p, tag) {
    var l = level(p);
    return '<li class="rm ' + l[1] + '"><div class="rm-h"><b>' + m.n + '</b><span class="code" dir="ltr">' + m.c + '</span></div>' +
      '<div class="meter" aria-hidden="true"><i style="width:' + p + '%"></i></div><div class="rm-s"><span>تناسب ' + l[0] + ' · ' + T.fa(p) + '٪</span>' + (tag ? '<em>' + tag + '</em>' : '') + '</div>' +
      (m.j ? '<small>مسیرهای شغلی: ' + m.j + '</small>' : '') + '</li>';
  }
  function jobCard(j, p) {
    var l = level(p);
    return '<li class="rm ' + l[1] + '"><div class="rm-h"><b>' + j.n + '</b><span class="code" dir="ltr">' + j.c + '</span></div>' +
      '<div class="meter" aria-hidden="true"><i style="width:' + p + '%"></i></div><div class="rm-s"><span>تناسب ' + l[0] + ' · ' + T.fa(p) + '٪</span></div><small>' + j.d + '</small></li>';
  }
  function steps(plan) { return '<ol class="rplan">' + plan.steps.map(function (s) { return '<li>' + s + '</li>'; }).join('') + '</ol>'; }

  T.register('riasec', {
    render: function (ctx) {
      var cfg = ctx.cfg, pct = ctx.pct, st = ctx.profile.status, tr = ctx.profile.track, goal = ctx.profile.goal;
      var rank = ranking(pct), code = rank.slice(0, 3).join('');
      var max = Math.max.apply(null, ORDER.map(function (d) { return pct[d]; })), min = Math.min.apply(null, ORDER.map(function (d) { return pct[d]; }));
      var spread = max - min;
      var u = {}; ORDER.forEach(function (d) { u[d] = spread ? (pct[d] - min) / spread : .5; });
      var h = '';

      // 1 · code
      h += '<section class="rsec rcode"><div class="rcode-l"><small>کد هالند شما</small><div class="bigcode" dir="ltr" aria-label="کد ' + code.split('').join(' ') + '">' +
        code.split('').map(function (d) { return '<span style="--c:' + cfg.dims[d].color + '">' + d + '</span>'; }).join('') + '</div>' +
        '<p>' + cfg.dims[rank[0]].emoji + ' ' + cfg.dims[rank[0]].name + ' ← ' + cfg.dims[rank[1]].name + ' ← ' + cfg.dims[rank[2]].name + '</p></div>' +
        '<p class="rcode-t">بیشترین انرژی‌تان از فعالیت‌های «<b>' + cfg.dims[rank[0]].sub + '</b>» می‌آید، با رگه‌ی «' + cfg.dims[rank[1]].sub + '» و «' + cfg.dims[rank[2]].sub + '». شغل یا رشته‌ای که این سه را با هم داشته باشد، احتمالاً بیشترین رضایت و ماندگاری را برایتان می‌سازد.</p></section>';

      if (spread < 15) h += '<p class="callout">پاسخ‌های شما تقریباً به همه‌ی گروه‌ها یک‌اندازه نمره داده و تفاوت‌ها کم است. نتیجه ممکن است دقیق نباشد؛ اگر دوست دارید، تست را یک بار دیگر و با انتخاب‌های قاطع‌تر انجام دهید.</p>';

      // 2 · chart
      h += '<section class="rsec"><h3>نمودار علایق شما</h3><div class="rchart">' + radar(cfg, pct) + '<ul class="rbars">' +
        rank.map(function (d) { return '<li style="--c:' + cfg.dims[d].color + '"><span><b>' + d + '</b> ' + cfg.dims[d].name + '</span><div class="meter" aria-hidden="true"><i style="width:' + pct[d] + '%"></i></div><em>' + T.fa(pct[d]) + '٪</em></li>'; }).join('') + '</ul></div></section>';

      // 3 · interpretation
      h += '<section class="rsec"><h3>تفسیر کامل: شخصیت شغلی شما</h3><p class="rintro">سه گروه برتر شما، «دلیل» پیشنهادهای پایین هستند. هر کدام را با دقت بخوانید و ببینید چقدر شبیه شماست.</p>' +
        rank.slice(0, 3).map(function (d, i) { return typeCard(cfg, d, i + 1); }).join('') +
        '<details class="rmore"><summary>گروه‌های کم‌اولویت‌تر شما (برای شناخت کامل)</summary>' + rank.slice(3).map(function (d, i) { return typeCard(cfg, d, i + 4); }).join('') + '</details></section>';

      // 4 · recommendations
      var majorsAll = cfg.majors.map(function (m) { return { m: m, p: fit(m.c, u, rank) }; }).sort(function (a, b) { return b.p - a.p; });
      var jobsAll = cfg.jobs.map(function (j) { return { j: j, p: fit(j.c, u, rank) }; }).sort(function (a, b) { return b.p - a.p; });
      if (st === 'student') {
        var mine = majorsAll.filter(function (x) { return x.m.t.indexOf(tr) >= 0; }), other = majorsAll.filter(function (x) { return x.m.t.indexOf(tr) < 0; });
        h += '<section class="rsec"><h3>رشته‌های دانشگاهی مناسب شما</h3><p class="rintro">این فهرست برای دانش‌آموز <b>' + cfg.labels.track[tr] + '</b> است و بر اساس کد <b dir="ltr">' + code + '</b> شما از بیشترین تناسب مرتب شده. تناسب یعنی هم‌خوانی علاقه‌ی شما با فضای کاری رشته، نه رتبه‌ی لازم برای قبولی.</p>' +
          '<ul class="rlist">' + mine.slice(0, 8).map(function (x) { return majorCard(x.m, x.p); }).join('') + '</ul>' +
          '<details class="rmore"><summary>رشته‌های بیشتر در رشته‌ی ' + cfg.labels.track[tr] + '</summary><ul class="rlist">' + mine.slice(8, 16).map(function (x) { return majorCard(x.m, x.p); }).join('') + '</ul></details>' +
          '<h4 class="rsub">خارج از رشته‌ی شما، ولی شبیه شما</h4><p class="rintro">این رشته‌ها با روحیات شما هم‌خوانی بالایی دارند اما معمولاً از مسیر رشته‌ی دیگری وارد می‌شوند؛ برای دانستن و تصمیم‌های آینده (مثلاً ارشد یا تغییر مسیر).</p>' +
          '<ul class="rlist">' + other.slice(0, 4).map(function (x) { return majorCard(x.m, x.p, 'از مسیر دیگر'); }).join('') + '</ul></section>';
        h += '<section class="rsec"><h3>شغل‌هایی که با کد شما می‌خواند</h3><ul class="rlist">' + jobsAll.slice(0, 6).map(function (x) { return jobCard(x.j, x.p); }).join('') + '</ul></section>';
      } else {
        h += '<section class="rsec"><h3>شغل‌های مناسب شما</h3><p class="rintro">بر اساس کد <b dir="ltr">' + code + '</b> این شغل‌ها بیشترین هم‌خوانی را با روحیات شما دارند. به‌جای انتخاب یک شغل، ۲ تا ۳ مورد را برای تحقیق بردارید.</p><ul class="rlist">' +
          jobsAll.slice(0, 12).map(function (x) { return jobCard(x.j, x.p); }).join('') + '</ul>' +
          '<details class="rmore"><summary>شغل‌های بیشتر</summary><ul class="rlist">' + jobsAll.slice(12, 24).map(function (x) { return jobCard(x.j, x.p); }).join('') + '</ul></details></section>';
        if (st === 'uni' && goal === 'grad') {
          h += '<section class="rsec"><h3>گرایش‌های مناسب برای ادامه‌ی تحصیل</h3><p class="rintro">رشته‌های زیر با علایق شما بیشترین هم‌خوانی را دارند؛ دنبال گرایش‌های مرتبط با آن‌ها در مقطع بالاتر بگردید.</p><ul class="rlist">' +
            majorsAll.slice(0, 6).map(function (x) { return majorCard(x.m, x.p); }).join('') + '</ul></section>';
        }
      }

      // 5 · plan
      var plan = st === 'student' ? cfg.studentPlan : (cfg.plans[st] && cfg.plans[st][goal]);
      if (plan) h += '<section class="rsec rplan-s"><h3>' + plan.title + '</h3>' + steps(plan) + '</section>';

      // 6 · disclaimer + CTA
      h += '<section class="rsec"><p class="callout">این نتیجه فقط علاقه‌ی شما را می‌سنجد، نه توانایی، رتبه یا شرایط بازار کار. آن را نقطه‌ی شروع گفت‌وگو و تحقیق بدانید و برای تصمیم‌های بزرگ با افراد باتجربه‌ی آن مسیر هم مشورت کنید.</p></section>';
      h += '<section class="rsec rcta"><div><h3>می‌خواهید مسیرتان را با یک متخصص روشن‌تر کنید؟</h3><p>اگر درباره‌ی مسیر شغلی یا شروع و رشد کسب‌وکار سؤال دارید، یک گفت‌وگوی کوتاه می‌تواند راهگشا باشد.</p></div><a class="btn btn-gold" href="' + ctx.cta + '">درخواست مشاوره</a></section>';
      return h;
    }
  });
})();
