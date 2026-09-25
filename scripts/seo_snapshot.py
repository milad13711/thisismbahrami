#!/usr/bin/env python3
"""Snapshot SEO-critical signals for every URL in a site's sitemap.

Usage:
  python3 scripts/seo_snapshot.py https://thisismbahrami.ir docs/baseline.csv
  python3 scripts/seo_snapshot.py https://staging.thisismbahrami.ir docs/staging.csv --paths-from docs/baseline.csv
  python3 scripts/seo_snapshot.py --compare docs/baseline.csv docs/staging.csv

The baseline taken before migration is the contract: every path in it must
still return 200 (or a deliberate 301) with an equivalent title/canonical after.
"""
import csv, html, os, re, ssl, sys, time, urllib.parse, urllib.request, concurrent.futures as cf

# python.org builds on macOS ship without a CA bundle; fall back to the system one.
_CTX = ssl.create_default_context(cafile="/etc/ssl/cert.pem" if os.path.exists("/etc/ssl/cert.pem") else None)

UA = "Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/128 Safari/537.36 seo-snapshot"
FIELDS = ["path", "status", "final_path", "title", "description", "canonical", "robots", "h1", "h1_count", "jsonld_types", "words"]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def fetch(url, follow=True, tries=4):
    # The CDN (ArvanCloud) drops bursts of connections; back off and retry.
    for i in range(tries):
        try:
            return _fetch(url, follow)
        except (urllib.error.URLError, ConnectionError, TimeoutError):
            if i == tries - 1:
                raise
            time.sleep(2 * (i + 1))


def _fetch(url, follow):
    https = urllib.request.HTTPSHandler(context=_CTX)
    opener = urllib.request.build_opener(https) if follow else urllib.request.build_opener(https, NoRedirect)
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        r = opener.open(req, timeout=60)
        return r.status, r.geturl(), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get("Location") or url, ""


def sitemap_paths(base):
    _, _, idx = fetch(base + "/sitemap_index.xml")
    maps = re.findall(r"<loc>([^<]+)</loc>", idx)
    paths = []
    for m in maps:
        _, _, xml = fetch(m)
        for loc in re.findall(r"<url>\s*<loc>([^<]+)</loc>", xml):
            paths.append(urllib.parse.urlsplit(loc).path)
    return paths


def text(s):
    return html.unescape(" ".join(re.sub(r"<[^>]+>", " ", s).split()))


def meta(doc, name):
    m = re.search(r'<meta[^>]+(?:name|property)="%s"[^>]+content="([^"]*)"' % name, doc)
    return html.unescape(m.group(1)) if m else ""


def snapshot(base, path):
    enc = urllib.parse.quote(urllib.parse.unquote(path), safe="/")
    status, _, _ = fetch(base + enc, follow=False)
    _, final, doc = fetch(base + enc)
    body = re.sub(r"<(script|style)[^>]*>.*?</\1>", " ", doc, flags=re.S)
    h1s = [text(h) for h in re.findall(r"<h1[^>]*>(.*?)</h1>", body, re.S)]
    t = re.search(r"<title[^>]*>(.*?)</title>", doc, re.S)
    c = re.search(r'<link[^>]+rel="canonical"[^>]+href="([^"]*)"', doc)
    types = sorted(set(re.findall(r'"@type"\s*:\s*"([^"]+)"', doc)))
    return {
        "path": urllib.parse.unquote(path),
        "status": status,
        "final_path": urllib.parse.unquote(urllib.parse.urlsplit(final).path),
        "title": text(t.group(1)) if t else "",
        "description": meta(doc, "description"),
        "canonical": urllib.parse.unquote(urllib.parse.urlsplit(c.group(1)).path) if c else "",
        "robots": meta(doc, "robots"),
        "h1": h1s[0] if h1s else "",
        "h1_count": len(h1s),
        "jsonld_types": " ".join(types),
        "words": len(text(body).split()),
    }


def compare(a_file, b_file):
    a = {r["path"]: r for r in csv.DictReader(open(a_file, encoding="utf-8"))}
    b = {r["path"]: r for r in csv.DictReader(open(b_file, encoding="utf-8"))}
    bad = 0
    for p, r in a.items():
        n = b.get(p)
        problems = []
        if not n:
            problems.append("MISSING from new site")
        else:
            if n["status"] not in ("200", "301"):
                problems.append(f"status {r['status']} -> {n['status']}")
            if n["status"] == "200" and n["canonical"] != r["canonical"]:
                problems.append(f"canonical {r['canonical']} -> {n['canonical']}")
            if n["robots"] and "noindex" in n["robots"] and "noindex" not in r["robots"]:
                problems.append("became noindex")
            if n["h1_count"] != "1":
                problems.append(f"h1 count {n['h1_count']}")
        if problems:
            bad += 1
            print(p, "|", "; ".join(problems))
    print(f"\n{len(a)} baseline URLs, {bad} with problems")
    return bad


def main():
    args = sys.argv[1:]
    if args and args[0] == "--compare":
        sys.exit(1 if compare(args[1], args[2]) else 0)
    base, out = args[0].rstrip("/"), args[1]
    if "--paths-from" in args:
        paths = [urllib.parse.quote(r["path"], safe="/") for r in csv.DictReader(open(args[args.index("--paths-from") + 1], encoding="utf-8"))]
    else:
        paths = sitemap_paths(base)
    with cf.ThreadPoolExecutor(2) as ex:
        rows = list(ex.map(lambda p: snapshot(base, p), paths))
    with open(out, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, FIELDS)
        w.writeheader()
        w.writerows(rows)
    print(f"{len(rows)} URLs -> {out}")


if __name__ == "__main__":
    main()
