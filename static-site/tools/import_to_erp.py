#!/usr/bin/env python3
"""One-time import of the existing articles (site/) into the Exir ERP «وب‌سایت» module.

  python3 tools/import_to_erp.py [--dry-run]

Needs the ERP API key in ~/.thisismbahrami-erp-key. Each article is created, its cover image uploaded and the
post published. The sync state (tools/.erp-sync.json) is pre-filled with the published versions so
sync_from_erp.py does NOT re-render these legacy pages — they only get re-rendered after you edit them in the ERP.
The article body is stored as the page's current HTML (Markdown accepts raw HTML).
"""
import argparse, base64, html as H, json, mimetypes, os, re, ssl, sys, urllib.error, urllib.parse, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = os.path.join(ROOT, 'site')
STATE = os.path.join(ROOT, 'tools', '.erp-sync.json')
API = os.environ.get('ERP_API', 'https://exirerp.ir/api').rstrip('/')
KEY = open(os.path.expanduser('~/.thisismbahrami-erp-key'), encoding='utf-8').read().strip()
CTX = ssl.create_default_context(cafile='/etc/ssl/cert.pem')

def api(method, path, body=None):
    req = urllib.request.Request(API + path, method=method, data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Authorization': 'Bearer ' + KEY, 'Content-Type': 'application/json', 'User-Agent': 'site-import'})
    try:
        return json.loads(urllib.request.urlopen(req, context=CTX, timeout=120).read().decode('utf-8') or 'null')
    except urllib.error.HTTPError as e:
        raise RuntimeError('%s %s -> %s %s' % (method, path, e.code, e.read().decode('utf-8', 'ignore')[:200]))

def meta(s, prop, attr='property'):
    m = re.search(r'<meta %s="%s" content="([^"]*)"' % (attr, re.escape(prop)), s)
    return H.unescape(m.group(1)) if m else ''

def posts():
    xml = open(os.path.join(SITE, 'post-sitemap.xml'), encoding='utf-8').read()
    for u in re.findall(r'<loc>([^<]+)</loc>', xml):
        path = urllib.parse.unquote(urllib.parse.urlparse(u).path).strip('/')
        f = os.path.join(SITE, path, 'index.html')
        if path and os.path.exists(f): yield path, open(f, encoding='utf-8').read()

def main():
    ap = argparse.ArgumentParser(); ap.add_argument('--dry-run', action='store_true'); a = ap.parse_args()
    state = json.load(open(STATE)) if os.path.exists(STATE) else {}
    ok = skipped = 0
    for slug, s in posts():
        if meta(s, 'og:type') != 'article': continue
        title = H.unescape(re.sub(r'<[^>]+>', '', re.search(r'<h1[^>]*>(.*?)</h1>', s, re.S).group(1))).strip()
        prose = re.search(r'<div class="prose">(.*?)</div>\s*<p class="updated"', s, re.S)
        if not prose: print('  skip (no body):', slug); skipped += 1; continue
        body = prose.group(1).strip()
        img_path = urllib.parse.unquote(urllib.parse.urlparse(meta(s, 'og:image')).path)
        img_file = os.path.join(SITE, img_path.lstrip('/'))
        lead = re.search(r'<p class="lead"[^>]*>(.*?)</p>', s, re.S)
        tags = [meta_v for meta_v in re.findall(r'<meta property="article:tag" content="([^"]*)"', s)]
        pub = meta(s, 'article:published_time')
        print('->', slug[:60], '| body', len(body), '| cover', os.path.basename(img_file)[:30] if os.path.exists(img_file) else 'MISSING')
        if a.dry_run: continue
        cover_id = None
        if os.path.exists(img_file):
            mime = mimetypes.guess_type(img_file)[0] or 'image/webp'
            cover_id = api('POST', '/website/media', {'filename': os.path.basename(img_file), 'dataUri': 'data:%s;base64,%s' % (mime, base64.b64encode(open(img_file, 'rb').read()).decode())})['id']
        post = api('POST', '/website/posts', {
            'kind': 'POST', 'title': title, 'slug': slug, 'bodyMd': body, 'excerpt': H.unescape(re.sub(r'<[^>]+>', '', lead.group(1))).strip()[:590] if lead else None,
            'metaTitle': H.unescape(re.search(r'<title>(.*?)</title>', s, re.S).group(1))[:220], 'metaDescription': meta(s, 'description', 'name')[:400] or title,
            'tags': tags[:20], 'category': meta(s, 'article:section') or None, 'coverMediaId': cover_id, 'coverAlt': (meta(s, 'og:image:alt') or title)[:300]})
        post = api('POST', '/website/posts/%s/publish' % post['id'])
        state[post['id']] = post['publishedVersion']
        ok += 1
    if not a.dry_run:
        json.dump(state, open(STATE, 'w'))
    print('imported', ok, 'skipped', skipped)

if __name__ == '__main__':
    main()
