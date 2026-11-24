#!/usr/bin/env python3
"""Render the live WordPress site into plain static files (dist/).

Keeps every URL (pages AND asset paths) identical so SEO and image links survive.
Usage: python3 tools/crawl.py [--base https://thisismbahrami.ir] [--out dist]
"""
import ssl, argparse, concurrent.futures as cf, csv, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request

ap = argparse.ArgumentParser()
ap.add_argument('--base', default='https://thisismbahrami.ir')
ap.add_argument('--out', default='dist')
ap.add_argument('--exclude', default='tools/exclude.txt')
a = ap.parse_args()
BASE = a.base.rstrip('/'); HOST = urllib.parse.urlparse(BASE).netloc; OUT = a.out
EXCL = set(l.strip() for l in open(a.exclude, encoding='utf-8') if l.strip() and not l.startswith('#')) if os.path.exists(a.exclude) else set()
UA = {'User-Agent': 'Mozilla/5.0 (static-export)'}

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kw): return None
_ctx = ssl.create_default_context(cafile='/etc/ssl/cert.pem')
opener = urllib.request.build_opener(NoRedirect, urllib.request.HTTPSHandler(context=_ctx))

def req(url, tries=5):
    last = None
    for i in range(tries):
        try:
            r = opener.open(urllib.request.Request(url, headers=UA), timeout=60)
            return r.status, dict(r.headers), r.read()
        except urllib.error.HTTPError as e:
            if e.code in (301, 302, 307, 308): return e.code, dict(e.headers), b''
            if e.code in (404, 410): return e.code, dict(e.headers), e.read()
            last = e
        except Exception as e:
            last = e
        time.sleep(2 * (i + 1))
    return 0, {}, str(last).encode()

def norm_path(url):
    p = urllib.parse.urlparse(url)
    if p.netloc and p.netloc != HOST: return None
    path = urllib.parse.unquote(p.path or '/')
    return path

def q(path): return urllib.parse.quote(path, safe="/%:@!$&'()*+,;=-._~")

DATED = re.compile(r'^/\d{4}/\d{2}/\d{2}/')
def canon(path):
    return DATED.sub('/', path)
LEGIT_CATS = {'توسعه-کسب-و-کار', 'our-colleagues', 'داستان-های-مدیریتی-2024-ارتقا-سازمان'}
JUNK = ['/cart/', '/checkout/', '/my-account/', '/login/', '/logout/', '/password-reset/', '/user/', '/رویدادها', '/مکان-ها', '/ثبت-نام-های-من', '/تگ-ها', '/دسته-بندی-ها', '/single-service/', '/tag/', '/category/', '/author/', '/feed/', '/wp-']

# ---- seeds
seeds = ['/']
for sm in ['sitemap_index.xml', 'post-sitemap.xml', 'page-sitemap.xml', 'portfolio-sitemap.xml', 'faq-sitemap.xml', 'category-sitemap.xml', 'tests-urls.xml']:
    st, _, body = req(BASE + '/' + sm)
    if st == 200:
        for u in re.findall(r'<loc>([^<]+)</loc>', body.decode('utf-8', 'ignore')):
            if u.endswith('.xml'): continue
            pth = norm_path(u)
            if sm == 'category-sitemap.xml' and pth.strip('/') not in LEGIT_CATS: continue
            seeds.append(pth)
if os.path.exists('../docs/baseline-live-2026-09-25.csv'):
    for r in csv.DictReader(open('../docs/baseline-live-2026-09-25.csv', encoding='utf-8')): seeds.append(r['path'])
seeds = [canon(s) for s in dict.fromkeys(seeds) if s and not any(e in s for e in EXCL)]
seeds = [s for s in dict.fromkeys(seeds) if not any(j in s for j in JUNK) and (s.strip('/') in LEGIT_CATS or s.strip('/').count('/') > 0 or True)]
EXTRA_SKIP = {'/1/', '/post/', '/public/', '/articles/', '/new/', '/news/', '/updates/', '/uncategorized/', '/games/', '/gambling/', '/blog-category/'}
seeds = [s for s in seeds if s not in EXTRA_SKIP and (not s.count('/') == 2 or s in ('/',) or True)]
print('seeds', len(seeds), flush=True)

pages, redirects, assets, seen, queue = {}, {}, set(), set(), list(seeds)
SKIP = re.compile(r'^/(wp-(json|admin|login|content|includes|cron)|xmlrpc|feed|comments/feed)|/feed/?$|\?')
def links_in(html):
    for m in re.finditer(r'<a\b[^>]*?href=["\']([^"\']+)["\']', html):
        h = m.group(1)
        if h.startswith(('#', 'mailto:', 'tel:', 'javascript:')): continue
        p = norm_path(urllib.parse.urljoin(BASE + '/', h))
        if p and not SKIP.search(p) and re.search(r'/page/\d+/?$', p) and p.strip('/').split('/page/')[0] in LEGIT_CATS | {'blog'}: yield p.split('#')[0]
def asset_refs(text):
    for m in re.finditer(r'(?:https?:)?(?://' + re.escape(HOST) + r')?(/wp-content/[^\s"\'<>)\\,;]+)', text):
        yield urllib.parse.unquote(m.group(1).split('?')[0])

def fetch_page(path):
    st, hd, body = req(BASE + q(path))
    return path, st, hd, body
while queue:
    batch = [p for p in dict.fromkeys(queue) if p not in seen]; queue = []
    for p in batch: seen.add(p)
    if not batch: break
    with cf.ThreadPoolExecutor(4) as ex:
        for path, st, hd, body in ex.map(fetch_page, batch):
            if st in (301, 302, 307, 308):
                loc = norm_path(urllib.parse.urljoin(BASE + '/', hd.get('Location') or hd.get('location', '')))
                if loc and canon(loc) != canon(path):
                    redirects[canon(path)] = (st, canon(loc))
                if loc and loc not in seen: queue.append(loc)
            elif st == 200:
                html = body.decode('utf-8', 'ignore'); pages[canon(path)] = html
                for l in links_in(html):
                    if l not in seen and not any(e in l for e in EXCL): queue.append(l)
                assets.update(asset_refs(html))
            else:
                print('  status', st, path, flush=True)
    print('pages', len(pages), 'redirects', len(redirects), 'queue', len(queue), flush=True)

# ---- Rank Math dynamic og:image (admin-ajax overlay) -> the real uploaded file
OVER = re.compile(r'https?://' + re.escape(HOST) + r'/wp-admin/admin-ajax\.php\?action=rank_math_overlay_thumb(?:&#038;|&amp;|&)id=(\d+)[^"\'<> ]*')
media_map = {}
for html in pages.values():
    for mid in set(m.group(1) for m in OVER.finditer(html)):
        if mid in media_map: continue
        st_m, _, b = req(BASE + '/wp-json/wp/v2/media/' + mid + '?_fields=source_url')
        if st_m == 200:
            media_map[mid] = json.loads(b)['source_url']
            assets.add(urllib.parse.unquote(urllib.parse.urlparse(media_map[mid]).path))
print('og media', media_map, flush=True)

# ---- 404 page
st404, _, body404 = req(BASE + '/__not-found-probe__/')
print('404 probe status', st404)
json.dump({'pages': sorted(pages), 'redirects': redirects}, open('tools/crawl-manifest.json', 'w'), ensure_ascii=False, indent=1)
os.makedirs(OUT, exist_ok=True)
# theme css/js reference more assets
def get_asset(path):
    dst = os.path.join(OUT, path.lstrip('/'))
    if os.path.exists(dst) and os.path.getsize(dst) > 0: return path, 200, False
    st, _, body = req(BASE + q(path))
    if st == 200:
        os.makedirs(os.path.dirname(dst), exist_ok=True); open(dst, 'wb').write(body)
        return path, 200, body if path.endswith(('.css', '.js')) else False
    return path, st, False
pending = sorted(assets); done = set()
while pending:
    new = set()
    with cf.ThreadPoolExecutor(4) as ex:
        for path, st, extra in ex.map(get_asset, pending):
            done.add(path)
            if st != 200: print('  asset', st, path, flush=True)
            if extra:
                base_dir = os.path.dirname(path)
                txt = extra.decode('utf-8', 'ignore')
                for m in re.finditer(r'url\(["\']?([^)"\']+)["\']?\)', txt):
                    u = m.group(1)
                    if u.startswith(('data:', 'http', '#')): continue
                    new.add(os.path.normpath(os.path.join(base_dir, urllib.parse.unquote(u.split('?')[0]).split('#')[0])))
                for m in re.finditer(r'["\'](?:\.\./|\./)?(assets/[^"\'\s]+\.(?:svg|png|jpe?g|webp|woff2?|gif))["\']', txt): new.add('/wp-content/themes/milad-bahrami/' + m.group(1))
    pending = sorted(x for x in new if x not in done)
    print('assets done', len(done), flush=True)

# ---- write pages (transform in tools/transform.py)
sys.path.insert(0, 'tools')
import transform
transform.MEDIA = media_map
for path, html in pages.items():
    out = transform.page(html, BASE)
    d = os.path.join(OUT, path.strip('/'))
    os.makedirs(d, exist_ok=True)
    open(os.path.join(d, 'index.html'), 'w', encoding='utf-8').write(out)
if st404 == 404 and body404:
    open(os.path.join(OUT, '404.html'), 'w', encoding='utf-8').write(transform.page(body404.decode('utf-8', 'ignore'), BASE))
print('WROTE', len(pages), 'pages;', len(done), 'assets', flush=True)
