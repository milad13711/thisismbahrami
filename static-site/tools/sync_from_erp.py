#!/usr/bin/env python3
"""Pull published/archived posts from the Exir ERP «وب‌سایت» module and update the live static site.

  python3 tools/sync_from_erp.py [--dry-run] [--no-deploy]

Needs an ERP API key in ~/.thisismbahrami-erp-key (create it in ERP: تنظیمات ← API) and ~/.netrc for FTP.
State (which version of each post is already on the site) lives in tools/.erp-sync.json.
- PUBLISHED, new or newer version  -> page built with tools/new_article.py, hub/sitemap refreshed
- ARCHIVED (taken off the site)    -> page removed, 410 rule added, hub/sitemap refreshed
Everything changed is uploaded in ONE FTP session at the end.
"""
import argparse, base64, ftplib, json, netrc, os, re, ssl, subprocess, sys, tempfile, urllib.parse, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = os.path.join(ROOT, 'site')
STATE = os.path.join(ROOT, 'tools', '.erp-sync.json')
API = os.environ.get('ERP_API', 'https://exirerp.ir/api').rstrip('/')
KEY_FILE = os.path.expanduser('~/.thisismbahrami-erp-key')
CTX = ssl.create_default_context(cafile='/etc/ssl/cert.pem')
REMOTE = '/thisismbahrami.ir/public_html'

def call(path):
    key = open(KEY_FILE, encoding='utf-8').read().strip()
    req = urllib.request.Request(API + path, headers={'Authorization': 'Bearer ' + key, 'User-Agent': 'site-sync'})
    return json.loads(urllib.request.urlopen(req, context=CTX, timeout=60).read().decode('utf-8'))

def media_to_file(mid, tmp):
    m = call('/website/media/' + mid)
    ext = {'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'image/gif': 'gif'}.get(m['mimeType'], 'bin')
    p = os.path.join(tmp, mid + '.' + ext)
    open(p, 'wb').write(base64.b64decode(m['base64']))
    return p

def front(v):
    return v.replace('\n', ' ').strip() if isinstance(v, str) else v

def build(item, tmp, changed):
    md = os.path.join(tmp, item['slug'] + '.md')
    fm = ['---', 'title: ' + front(item['title']), 'slug: ' + item['slug']]
    for k, f in (('metaTitle', 'metaTitle'), ('metaDescription', 'metaDescription'), ('excerpt', 'excerpt'), ('focusKeyword', 'focusKeyword')):
        if item.get(f): fm.append('%s: %s' % (k, front(item[f])))
    if item.get('tags'): fm.append('tags: [%s]' % ', '.join(item['tags']))
    fm.append('---\n')
    open(md, 'w', encoding='utf-8').write('\n'.join(fm) + '\n' + item['bodyMd'])
    if not item.get('coverMediaId'):
        print('  skip (no cover image):', item['slug']); return False
    cmd = [sys.executable, os.path.join(ROOT, 'tools', 'new_article.py'), md, '--cover', media_to_file(item['coverMediaId'], tmp), '--cover-alt', item.get('coverAlt') or item['title']]
    if item.get('midMediaId'):
        cmd += ['--mid', media_to_file(item['midMediaId'], tmp), '--mid-alt', item.get('midAlt') or item['title']]
        if item.get('midBefore'): cmd += ['--mid-before', item['midBefore']]
    for l in item.get('links') or []:
        cmd += ['--link', '%s=%s' % (l['phrase'], l['url'])]
    if item.get('category'): cmd += ['--category', item['category']]
    if item.get('publishedAt'): cmd += ['--date', item['publishedAt'][:19]]
    subprocess.run(cmd, check=True)
    changed.update(open(os.path.join(ROOT, 'tools', 'last-publish.txt'), encoding='utf-8').read().split('\n'))
    return True

def take_down(item, changed):
    slug = item['slug']
    d = os.path.join(SITE, slug)
    page = os.path.join(d, 'index.html')
    removed = os.path.exists(page)
    if removed:
        os.remove(page)
        try: os.rmdir(d)
        except OSError: pass
    sm = os.path.join(SITE, 'post-sitemap.xml')
    s = open(sm, encoding='utf-8').read()
    s2 = re.sub(r'<url><loc>[^<]*/%s/</loc>.*?</url>\s*' % re.escape(urllib.parse.quote(slug, safe='').lower()), '', s, flags=re.S)
    s2 = re.sub(r'<url><loc>[^<]*%s[^<]*</loc>.*?</url>\s*' % re.escape(urllib.parse.quote(slug, safe='-').lower()), '', s2, flags=re.S)
    if s2 != s: open(sm, 'w', encoding='utf-8').write(s2); changed.add('post-sitemap.xml')
    ht = os.path.join(SITE, '.htaccess')
    rule = 'RedirectMatch 410 ^/%s/?$' % re.escape(slug).replace('\\-', '-')
    h = open(ht, encoding='utf-8').read()
    if rule not in h:
        open(ht, 'w', encoding='utf-8').write(h.rstrip('\n') + '\n' + rule + '\n'); changed.add('.htaccess')
    return removed

def delete_remote(slugs):
    if not slugs: return
    n = netrc.netrc(); host = list(n.hosts)[0]; u, _, p = n.authenticators(host)
    f = ftplib.FTP(host, timeout=60); f.login(u, p); f.set_pasv(True)
    for s in slugs:
        try: f.delete('%s/%s/index.html' % (REMOTE, s)); f.rmd('%s/%s' % (REMOTE, s)); print('  removed on server:', s)
        except ftplib.all_errors as e: print('  (server)', s, e)
    f.quit()

def main():
    ap = argparse.ArgumentParser(); ap.add_argument('--dry-run', action='store_true'); ap.add_argument('--no-deploy', action='store_true')
    a = ap.parse_args()
    state = json.load(open(STATE)) if os.path.exists(STATE) else {}
    items = call('/website/export?seen=' + urllib.parse.quote(json.dumps(state)))
    print('changes from ERP:', len(items))
    if a.dry_run:
        for it in items: print(' ', it['status'], 'v%s' % it['version'], it['slug'])
        return
    changed, down, ok_state = set(), [], dict(state)
    with tempfile.TemporaryDirectory() as tmp:
        for it in items:
            print('->', it['status'], it['slug'])
            if it['status'] == 'PUBLISHED':
                if build(it, tmp, changed): ok_state[it['id']] = it['version']
            else:
                if take_down(it, changed): down.append(it['slug'])
                ok_state[it['id']] = it['version']
    if down:
        sys.path.insert(0, os.path.join(ROOT, 'tools'))
        import build_hub
        build_hub.SITE = SITE; build_hub.written.clear(); build_hub.build(); changed.update(build_hub.written)
    changed.discard('')
    if not changed:
        print('nothing to upload'); json.dump(ok_state, open(STATE, 'w')); return
    lst = os.path.join(ROOT, 'tools', 'last-publish.txt')
    open(lst, 'w', encoding='utf-8').write('\n'.join(sorted(changed)))
    if a.no_deploy:
        print('built; not deployed. files:', len(changed)); json.dump(ok_state, open(STATE, 'w')); return
    delete_remote(down)
    subprocess.run([sys.executable, '-u', os.path.join(ROOT, 'tools', 'deploy.py'), REMOTE, '--only', lst], check=True)
    json.dump(ok_state, open(STATE, 'w'))
    print('done; state saved')

if __name__ == '__main__':
    main()
