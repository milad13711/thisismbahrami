#!/usr/bin/env python3
"""Fail if any internal link / asset in the built site points to a file that doesn't exist."""
import os, re, sys, urllib.parse

ROOT = sys.argv[1] if len(sys.argv) > 1 else 'site'
BASE = 'https://thisismbahrami.ir'
rules = open(os.path.join(ROOT, '.htaccess'), encoding='utf-8').read() if os.path.exists(os.path.join(ROOT, '.htaccess')) else ''
redirected = set(urllib.parse.unquote(m) for m in re.findall(r'RedirectMatch 301 \^([^$]+)/\?\$', rules))
redirected = set(r.replace('\\-', '-').rstrip('/') for r in redirected)

def exists(path):
    p = urllib.parse.unquote(path.split('#')[0].split('?')[0])
    if not p.startswith('/'): return True
    full = os.path.join(ROOT, p.lstrip('/'))
    if os.path.isfile(full): return True
    if os.path.isdir(full) and os.path.isfile(os.path.join(full, 'index.html')): return True
    return p.rstrip('/') in redirected or p == '/'

bad, total = {}, 0
for dp, dn, fn in os.walk(ROOT):
    for f in fn:
        if not f.endswith('.html'): continue
        src = os.path.join(dp, f)
        html = open(src, encoding='utf-8').read()
        urls = re.findall(r'(?:href|src)=["\']([^"\']+)["\']', html)
        for srcset in re.findall(r'srcset=["\']([^"\']+)["\']', html):
            urls += [x.strip().split(' ')[0] for x in srcset.split(',') if x.strip()]
        for u in urls:
            if u.startswith(('#', 'mailto:', 'tel:', 'javascript:', 'data:', 'sms:', 'whatsapp:')): continue
            if u.startswith(BASE): u = u[len(BASE):] or '/'
            if u.startswith('//') or re.match(r'^[a-z]+://', u): continue
            total += 1
            if not exists(u): bad.setdefault(u, set()).add(os.path.relpath(src, ROOT))
print('checked', total, 'internal references;', len(bad), 'broken')
for u, where in sorted(bad.items()):
    print(' ', urllib.parse.unquote(u)[:90], '<-', sorted(where)[0][:50], '(+%d)' % (len(where) - 1))
sys.exit(1 if bad else 0)
