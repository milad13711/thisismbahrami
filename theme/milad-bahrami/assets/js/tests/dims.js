/* Generic "scored dimensions" result renderer (DISC, strengths, multiple intelligences, commitment, motivation, 16 types …). */
(function () {
  'use strict';
  var T = window.MBTests;

  function radar(cfg, keys, pct) {
    var n = keys.length, cx = 180, cy = 165, R = 118, i, rings = '', axes = '', labels = '', pts = [], dots = '';
    function pt(i, f) { var a = -Math.PI / 2 + i * 2 * Math.PI / n; return [cx + Math.cos(a) * R * f, cy + Math.sin(a) * R * f]; }
    function fmt(p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }
    [.25, .5, .75, 1].forEach(function (f) { var r = []; for (i = 0; i < n; i++) r.push(fmt(pt(i, f))); rings += '<polygon points="' + r.join(' ') + '" fill="none" stroke="currentColor" stroke-opacity=".14"/>'; });
    keys.forEach(function (k, i) {
      var e = pt(i, 1), p = pt(i, Math.max(.04, pct[k] / 100)), l = pt(i, 1.22), d = cfg.dims[k];
      pts.push(fmt(p));
      axes += '<line x1="' + cx + '" y1="' + cy + '" x2="' + e[0].toFixed(1) + '" y2="' + e[1].toFixed(1) + '" stroke="currentColor" stroke-opacity=".14"/>';
      dots += '<circle cx="' + p[0].toFixed(1) + '" cy="' + p[1].toFixed(1) + '" r="4.5" fill="' + d.color + '" stroke="#fff" stroke-width="1.5"/>';
      labels += '<text x="' + l[0].toFixed(1) + '" y="' + (l[1] + 4).toFixed(1) + '" text-anchor="middle" font-size="11.5" font-weight="700" fill="currentColor" fill-opacity=".85">' + (d.short || d.name) + '</text>';
    });
    return '<svg class="radar" viewBox="0 0 360 330" role="img" aria-label="نمودار نتیجه">' + rings + axes + '<polygon points="' + pts.join(' ') + '" fill="#C9A45C" fill-opacity=".28" stroke="#85632A" stroke-width="2.2" stroke-linejoin="round"/>' + dots + labels + '</svg>';
  }
  function lvl(p) { return p >= 67 ? ['بالا', 'hi'] : p >= 40 ? ['متوسط', 'mid'] : ['پایین', 'lo']; }
  function bars(cfg, keys, pct) {
    return '<ul class="rbars">' + keys.map(function (k) { var d = cfg.dims[k]; return '<li style="--c:' + d.color + '"><span><b>' + d.emoji + '</b> ' + d.name + '</span><div class="meter" aria-hidden="true"><i style="width:' + pct[k] + '%"></i></div><em>' + T.fa(pct[k]) + '٪</em></li>'; }).join('') + '</ul>';
  }
  function card(d, no, extra) {
    return '<article class="rtype" style="--c:' + d.color + '"><header><span class="rl" aria-hidden="true">' + d.emoji + '</span><div><small>' + extra + '</small><h4>' + d.name + (d.sub ? ' <span>· ' + d.sub + '</span>' : '') + '</h4></div></header>' +
      '<p class="lead-s">' + d.lead + '</p><p>' + d.about + '</p>' +
      (d.tips ? '<div class="rcols"><div><b>پیشنهاد عملی</b><ul>' + d.tips.map(function (t) { return '<li>' + t + '</li>'; }).join('') + '</ul></div><div><b>حواس‌تان باشد</b><p>' + (d.watch || '') + '</p></div></div>' : '') + '</article>';
  }

  T.register('dims', {
    render: function (ctx) {
      var cfg = ctx.cfg, pct = ctx.pct, keys = Object.keys(cfg.dims), h = '';
      if (cfg.pairs) {
        var code = cfg.pairs.map(function (p) { return pct[p[0]] >= pct[p[1]] ? p[0] : p[1]; }).join('');
        var ty = cfg.types[code] || { n: '', d: '' };
        h += '<section class="rsec rcode"><div class="rcode-l"><small>تیپ شما</small><div class="bigcode" dir="ltr">' + code.split('').map(function (c) { return '<span style="--c:' + cfg.dims[c].color + '">' + c + '</span>'; }).join('') + '</div><p>' + ty.n + '</p></div><p class="rcode-t">' + ty.d + '</p></section>';
        h += '<section class="rsec"><h3>شدت هر ویژگی</h3><div class="rpairs">' + cfg.pairs.map(function (p) {
          var a = cfg.dims[p[0]], b = cfg.dims[p[1]], pa = pct[p[0]], pb = pct[p[1]], tot = (pa + pb) || 1, wa = Math.round(pa / tot * 100);
          return '<div class="rpair"><div class="rp-l"><b>' + a.name + '</b><span>' + T.fa(wa) + '٪</span></div><div class="rp-m" style="--a:' + a.color + ';--b:' + b.color + '"><i style="width:' + wa + '%"></i></div><div class="rp-r"><span>' + T.fa(100 - wa) + '٪</span><b>' + b.name + '</b></div></div>';
        }).join('') + '</div></section>';
        h += '<section class="rsec"><h3>تفسیر کامل</h3>' + cfg.pairs.map(function (p) { var c = pct[p[0]] >= pct[p[1]] ? p[0] : p[1]; return card(cfg.dims[c], 0, 'ویژگی غالب شما'); }).join('') + '</section>';
      } else {
        var good = keys.filter(function (k) { return !cfg.dims[k].bad; }).sort(function (a, b) { return pct[b] - pct[a]; });
        var top = good.slice(0, cfg.topN || 3), first = cfg.dims[top[0]];
        h += '<section class="rsec rcode"><div class="rcode-l"><small>' + (cfg.topLabel || 'ویژگی غالب شما') + '</small><div class="bigcode small" dir="auto">' + top.map(function (k) { return '<span style="--c:' + cfg.dims[k].color + '" title="' + cfg.dims[k].name + '">' + (cfg.dims[k].letter || cfg.dims[k].emoji) + '</span>'; }).join('') + '</div><p>' + top.map(function (k) { return cfg.dims[k].name; }).join(' ← ') + '</p></div>' +
          '<p class="rcode-t">' + (cfg.summary || '').replace('{first}', first.name).replace('{second}', cfg.dims[top[1]] ? cfg.dims[top[1]].name : '') + ' ' + first.lead + '</p></section>';
        h += '<section class="rsec"><h3>نمودار نتیجه</h3><div class="rchart">' + (keys.length >= 3 ? radar(cfg, keys, pct) : '') + bars(cfg, keys, pct) + '</div></section>';
        h += '<section class="rsec"><h3>تفسیر کامل</h3><p class="rintro">مهم‌ترین ویژگی‌های شما با توضیح کامل:</p>' + top.map(function (k, i) { return card(cfg.dims[k], i, 'اولویت ' + T.fa(i + 1) + ' · ' + T.fa(pct[k]) + '٪'); }).join('') + '</section>';
        h += '<section class="rsec"><h3>وضعیت شما در همه‌ی ابعاد</h3><ul class="rlist">' + keys.map(function (k) {
          var d = cfg.dims[k], l = lvl(pct[k]), good = d.bad ? (l[1] === 'lo' ? 'hi' : l[1] === 'hi' ? 'lo' : 'mid') : l[1];
          var txt = l[1] === 'hi' ? d.hi : l[1] === 'lo' ? d.lo : 'در حد میانه است و بسته به موقعیت کم یا زیاد می‌شود؛ با تمرین آگاهانه می‌توانید آن را به سمتی که می‌خواهید ببرید.';
          return '<li class="rm ' + good + '"><div class="rm-h"><b>' + d.emoji + ' ' + d.name + '</b><span class="code">' + l[0] + '</span></div><div class="meter" aria-hidden="true"><i style="width:' + pct[k] + '%"></i></div><small>' + txt + '</small></li>';
        }).join('') + '</ul></section>';
      }
      if (cfg.steps) h += '<section class="rsec rplan-s"><h3>' + (cfg.stepsTitle || 'قدم‌های بعدی') + '</h3><ol class="rplan">' + cfg.steps.map(function (s) { return '<li>' + s + '</li>'; }).join('') + '</ol></section>';
      h += '<section class="rsec"><p class="callout">' + (cfg.disclaimer || 'این تست یک ابزار خودشناسی است، نه تشخیص تخصصی. نتیجه را نقطه‌ی شروع گفت‌وگو و تأمل بدانید.') + '</p></section>';
      h += '<section class="rsec rcta"><div><h3>می‌خواهید نتیجه را به یک برنامه‌ی عملی تبدیل کنید؟</h3><p>یک گفت‌وگوی کوتاه با متخصص می‌تواند مسیر را روشن‌تر کند.</p></div><a class="btn btn-gold" href="' + ctx.cta + '">درخواست مشاوره</a></section>';
      return h;
    }
  });
})();
