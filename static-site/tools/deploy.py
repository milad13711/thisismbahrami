#!/usr/bin/env python3
"""Resumable single-connection FTP upload of site/ to a remote directory.

  python3 tools/deploy.py <remote_dir> [--site site]

Credentials come from ~/.netrc. A state file (tools/.deploy-<name>.json) records what was
uploaded (path -> size+mtime) so a re-run only sends new or changed files.
"""
import ftplib, json, netrc, os, posixpath, sys, time

remote = sys.argv[1].rstrip('/')
site = sys.argv[sys.argv.index('--site') + 1] if '--site' in sys.argv else 'site'
state_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '.deploy-%s.json' % remote.strip('/').replace('/', '_'))
state = json.load(open(state_path)) if os.path.exists(state_path) else {}
n = netrc.netrc(); host = list(n.hosts)[0]; user, _, pw = n.authenticators(host)
F = [None]

def conn():
    try:
        if F[0]: F[0].close()
    except Exception: pass
    for i in range(15):
        try:
            f = ftplib.FTP(host, timeout=60); f.login(user, pw); f.set_pasv(True); F[0] = f; return
        except Exception: time.sleep(6 * (i + 1))
    raise SystemExit('cannot connect')

files = []
for dp, dn, fn in os.walk(site):
    for x in fn:
        full = os.path.join(dp, x)
        rel = os.path.relpath(full, site)
        files.append((rel, full))
if '--only' in sys.argv:  # upload just the files listed (one relative path per line) in the given file
    only = set(l.strip() for l in open(sys.argv[sys.argv.index('--only') + 1], encoding='utf-8') if l.strip())
    files = [t for t in files if t[0].replace(os.sep, '/') in only]
# html last so assets are in place first; .htaccess very last (it may redirect/deny)
files.sort(key=lambda t: (t[0] == '.htaccess', t[0].endswith('.html'), t[0]))
conn(); made = set(); sent = 0; skipped = 0
for rel, full in files:
    sig = '%d-%d' % (os.path.getsize(full), int(os.path.getmtime(full)))
    if '--only' not in sys.argv and state.get(rel) == sig:
        skipped += 1; continue
    d = posixpath.dirname(remote + '/' + rel.replace(os.sep, '/'))
    for attempt in range(6):
        try:
            if d not in made:
                cur = ''
                for part in d.strip('/').split('/'):
                    cur += '/' + part
                    try: F[0].mkd(cur)
                    except ftplib.error_perm: pass
                made.add(d)
            with open(full, 'rb') as fh: F[0].storbinary('STOR ' + remote + '/' + rel.replace(os.sep, '/'), fh)
            state[rel] = sig; break
        except Exception:
            time.sleep(3); conn(); made.clear()
    else:
        print('FAILED', rel, flush=True); continue
    sent += 1
    if sent % 25 == 0:
        json.dump(state, open(state_path, 'w')); print('sent', sent, 'skipped', skipped, 'of', len(files), flush=True)
json.dump(state, open(state_path, 'w'))
print('DONE sent', sent, 'skipped', skipped, 'total', len(files), flush=True)
