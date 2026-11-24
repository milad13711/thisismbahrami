#!/usr/bin/env python3
"""Regenerate the article hub (/blog/, /blog/page/N/) and category listings from the article pages in site/.

Run after adding/changing articles:  python3 tools/build_hub.py [site]
Prints the list of files it wrote (relative paths) so a deploy can send only those.
"""
import html as H, json, os, re, sys, urllib.parse
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = os.path.join(ROOT, sys.argv[1] if len(sys.argv) > 1 else 'site')
BASE = 'https://thisismbahrami.ir'
PER_PAGE = 9
CATS = {  # display name -> slug (the only real categories)
    'توسعه کسب و کار': 'توسعه-کسب-و-کار',
    'همکاران ما': 'our-colleagues',
    'داستان مدیریتی لازم برای تغییر فرهنگ سازمانی': 'داستان-های-مدیریتی-2024-ارتقا-سازمان',
}
FA = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')
written = []

def q(path):  # URL path as WordPress emitted it: lowercase percent-encoding, trailing slash
    p = urllib.parse.quote(path.strip('/'), safe='/-_.~').lower() if path.strip('/') else ''
    return '/' + (p + '/' if p else '')

def meta(s, prop, attr='property'):
    m = re.search(r'<meta %s="%s" content="([^"]*)"' % (attr, re.escape(prop)), s)
    return H.unescape(m.group(1)) if m else ''

def post_paths():
    xml = open(os.path.join(SITE, 'post-sitemap.xml'), encoding='utf-8').read()
    return set(urllib.parse.unquote(urllib.parse.urlparse(u).path) for u in re.findall(r'<loc>([^<]+)</loc>', xml))

def scan():
    posts = []
    allowed = post_paths()
    for dp, dn, fn in os.walk(SITE):
        if 'index.html' not in fn or dp == SITE: continue
        s = open(os.path.join(dp, 'index.html'), encoding='utf-8').read()
        if meta(s, 'og:type') != 'article': continue
        rel = '/' + os.path.relpath(dp, SITE).replace(os.sep, '/') + '/'
        if rel not in allowed: continue
        h1 = re.search(r'<h1[^>]*>(.*?)</h1>', s, re.S)
        lead = re.search(r'<p class="lead"[^>]*>(.*?)</p>', s, re.S)
        excerpt = H.unescape(re.sub(r'<[^>]+>', '', lead.group(1))).strip() if lead else meta(s, 'og:description')
        words = excerpt.split()
        excerpt = ' '.join(words[:26]) + ('…' if len(words) > 26 else '')
        rt = re.search(r'(\d+)', meta(s, 'twitter:data2', 'name'))
        posts.append(dict(
            path=rel, title=H.unescape(re.sub(r'<[^>]+>', '', h1.group(1))).strip() if h1 else meta(s, 'og:title'),
            excerpt=excerpt, date=meta(s, 'article:published_time'), cat=meta(s, 'article:section'),
            img=urllib.parse.unquote(urllib.parse.urlparse(meta(s, 'og:image')).path), alt=meta(s, 'og:image:alt'),
            minutes=int(rt.group(1)) if rt else 5))
    posts.sort(key=lambda p: p['date'], reverse=True)
    return posts

def thumb(img):
    """720x405 cover crop next to the original, generated once."""
    if not img: return ''
    full = os.path.join(SITE, img.lstrip('/'))
    if not os.path.exists(full): return ''
    stem, ext = os.path.splitext(img)
    t = stem + '-720x405.webp'
    tp = os.path.join(SITE, t.lstrip('/'))
    if not os.path.exists(tp):
        im = Image.open(full).convert('RGB'); w, h = im.size
        target = 16 / 9
        if w / h > target: nw = int(h * target); im = im.crop(((w - nw) // 2, 0, (w - nw) // 2 + nw, h))
        else: nh = int(w / target); im = im.crop((0, (h - nh) // 2, w, (h - nh) // 2 + nh))
        im.resize((720, 405), Image.LANCZOS).save(tp, 'WEBP', quality=78, method=6)
        written.append(t.lstrip('/'))
    return t

def card(p, feature=False):
    t = thumb(p['img'])
    slug = CATS.get(p['cat'], '')
    img = ('<img width="720" height="405" src="%s" alt="%s" loading="lazy" decoding="async">' % (t, H.escape(p['alt']))) if t else ''
    return ('<a class="post%s" href="%s" data-cat="%s" data-reveal><div class="th">%s%s</div><div class="in"><h3>%s</h3><p>%s</p>'
            '<div class="meta"><span>%s دقیقه مطالعه</span></div>%s</div></a>') % (
        ' feature' if feature else '', q(p['path']),
        urllib.parse.quote(slug, safe='-_.~').lower(), img, ('<span class="cat">%s</span>' % H.escape(p['cat'])) if p['cat'] else '',
        H.escape(p['title']), H.escape(p['excerpt']), str(p['minutes']).translate(FA),
        '<span class="btn btn-ink" style="align-self:start;margin-top:14px;min-height:46px">مطالعه‌ی راهنما</span>' if feature else '')

def href(slug, n):
    base = '/' + urllib.parse.quote(slug, safe='-_.~').lower() + '/' if slug else '/blog/'
    return base + ('page/%d/' % n if n > 1 else '')

def pager(slug, n, total):
    if total <= 1: return ''
    out = []
    for i in range(1, total + 1):
        out.append('<a aria-current="page">%d</a>' % i if i == n else '<a class="page-numbers" href="%s">%d</a>' % (href(slug, i), i))
    if n < total: out.append('<a class="next page-numbers" href="%s">‹</a>' % href(slug, n + 1))
    return '<nav class="pager" aria-label="صفحه‌بندی">%s</nav>' % ''.join(out)

def shell():
    s = open(os.path.join(SITE, 'blog', 'index.html'), encoding='utf-8').read()
    i = s.index('<main id="main">'); j = s.index('</main>') + len('</main>')
    return s[:i], s[j:]

def set_meta(head, title, desc, url):
    head = re.sub(r'<title>.*?</title>', '<title>%s</title>' % H.escape(title), head, flags=re.S)
    for pat, val in ((r'(<meta name="description" content=")[^"]*', desc), (r'(<meta property="og:title" content=")[^"]*', title), (r'(<meta property="og:description" content=")[^"]*', desc),
                     (r'(<meta property="og:url" content=")[^"]*', url), (r'(<meta name="twitter:title" content=")[^"]*', title), (r'(<meta name="twitter:description" content=")[^"]*', desc),
                     (r'(<link rel="canonical" href=")[^"]*', url)):
        head = re.sub(pat, lambda m: m.group(1) + H.escape(val), head)
    head = re.sub(r'<meta property="og:type" content="[^"]*"', '<meta property="og:type" content="website"', head)
    head = re.sub(r'<meta property="article:[^>]*>\s*', '', head)
    ld = {'@context': 'https://schema.org', '@graph': [{'@type': 'CollectionPage', 'name': title, 'url': url, 'inLanguage': 'fa-IR', 'isPartOf': {'@type': 'WebSite', 'url': BASE + '/', 'name': 'میلاد بهرامی'}}]}
    head = re.sub(r'<script type="application/ld\+json" class="rank-math-schema">.*?</script>', lambda m: '<script type="application/ld+json" class="rank-math-schema">%s</script>' % json.dumps(ld, ensure_ascii=False), head, flags=re.S)
    return head

def main_html(posts, cats, slug, name, n, total):
    is_blog = not slug
    page = posts[(n - 1) * PER_PAGE:n * PER_PAGE]
    title = 'دانشنامه‌ی <span class="grad">سیستم‌سازی</span>' if is_blog else H.escape(name)
    lead = 'راهنماهای عملی برای مدیرانی که می‌خواهند کسب‌وکارشان بدون وابستگی به افراد رشد کند؛ از فرآیند و ERP تا فروش.' if is_blog else ''
    crumbs = '<ol class="crumbs" aria-label="مسیر صفحه"><li><a href="/">خانه</a></li>%s</ol>' % (
        '<li aria-current="page">دانشنامه</li>' if is_blog else '<li><a href="/blog/">دانشنامه</a></li><li aria-current="page">%s</li>' % H.escape(name))
    pillars = ''
    if is_blog and n == 1:
        pillars = '<div class="pillars rise" style="--d:150ms;margin-top:40px">%s</div>' % ''.join(
            '<a class="pillar" href="%s"><b>%s</b><span>%s مقاله</span><span class="n">مشاهده‌ی خوشه ←</span></a>' % (href(sl, 1), H.escape(nm), str(c).translate(FA)) for nm, sl, c in cats[:4])
    chips = '<a href="/blog/"%s>همه</a>' % (' aria-current="page"' if is_blog else '') + ''.join(
        '<a href="%s"%s>%s <small style="opacity:.6">%s</small></a>' % (href(sl, 1), ' aria-current="page"' if sl == slug else '', H.escape(nm), str(c).translate(FA)) for nm, sl, c in cats)
    cards = []
    for i, p in enumerate(page):
        cards.append(card(p, is_blog and n == 1 and i == 0))
        if i + 1 == 6 and len(page) > 6:
            cards.append('<div class="magnet" style="grid-column:1/-1" data-reveal><div class="doc">فصل<br>اول<br>رایگان</div><div><h3>فصل اول کتاب سلطان قیف را رایگان بخوانید</h3><p>DNA تفکر استراتژیک، همراه با بوم Strategic DNA برای کسب‌وکار خودتان.</p></div><a class="btn btn-ink" href="/%D8%B3%D9%84%D8%B7%D8%A7%D9%86-%D9%82%DB%8C%D9%81-%D9%81%D8%B5%D9%84-%D8%A7%D9%88%D9%84/">خواندن رایگان</a></div>')
    return ('<main id="main">\n<section class="phero on-dark"><div class="wrap"><div class="rise" style="max-width:760px">%s<h1>%s</h1>%s</div>%s</div></section>\n'
            '<section class="sec"><div class="wrap"><div class="filters"><nav class="filter-chips" aria-label="موضوع‌ها">%s</nav></div>'
            '<div class="posts">%s</div>%s</div></section>\n'
            '<section><div class="wrap"><div class="cta-band on-dark" data-reveal><div><h2>سؤال شما در مقاله‌ها نبود؟</h2><p>مسئله‌ی سازمان‌تان را مستقیم مطرح کنید؛ در جلسه‌ی مشاوره پاسخ مشخص می‌گیرید.</p></div>'
            '<div class="ctas"><a class="btn btn-gold" href="https://exirerp.ir/book/coaching">درخواست مشاوره کسب‌وکار</a><a class="btn btn-line" href="/%%D8%%A7%%D8%%B1%%D8%%B2%%DB%%8C%%D8%%A7%%D8%%A8%%DB%%8C-%%D8%%B3%%D8%%A7%%D8%%B2%%D9%%85%%D8%%A7%%D9%%86/">ارزیابی سازمان</a></div></div></div></section>\n</main>') % (
        crumbs, title, ('<p class="lead">%s</p>' % lead) if lead else '', pillars, chips, ''.join(cards), pager(slug, n, total))

def write(rel, content):
    path = os.path.join(SITE, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    open(path, 'w', encoding='utf-8').write(content)
    written.append(rel)

def build():
    posts = scan()
    head, tail = shell()
    cats = []
    for nm, sl in CATS.items():
        c = sum(1 for p in posts if p['cat'] == nm)
        if c: cats.append((nm, sl, c))
    cats.sort(key=lambda x: -x[2])
    def emit(slug, name, plist, desc):
        total = max(1, -(-len(plist) // PER_PAGE))
        for n in range(1, total + 1):
            url = BASE + href(slug, n)
            ttl = ('%s | میلاد بهرامی' % name) + (' - صفحه %d' % n if n > 1 else '')
            h = set_meta(head, ttl, desc, url)
            rel = ((urllib.parse.unquote(href(slug, n)).strip('/') if slug else 'blog' + ('/page/%d' % n if n > 1 else '')) + '/index.html')
            if not slug and n > 1: rel = 'blog/page/%d/index.html' % n
            write(rel, h + main_html(plist, cats, slug, name, n, total) + tail)
    emit('', 'دانشنامه‌ی سیستم‌سازی', posts, 'راهنماهای عملی سیستم‌سازی، عارضه‌یابی، فرآیند و ERP برای مدیران؛ مقاله‌های میلاد بهرامی مشاور کسب‌وکار.')
    for nm, sl, c in cats:
        emit(sl, nm, [p for p in posts if p['cat'] == nm], 'مقاله‌های دسته‌ی %s؛ راهنماهای عملی میلاد بهرامی.' % nm)
    print('posts', len(posts), 'categories', [(c[0], c[2]) for c in cats])

if __name__ == '__main__':
    build()
    print('\n'.join(written))
