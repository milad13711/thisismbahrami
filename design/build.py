#!/usr/bin/env python3
"""Assemble static design prototypes from partials.

    python3 design/build.py

Each file in src/pages/ starts with a metadata comment:

    <!--meta {"title": "...", "description": "...", "page": "blog",
              "template": "archive.php", "url": "/blog/"} -->

`page` marks the active nav item; `template` and `url` document which
WordPress template / live URL the prototype maps to (shown in index gallery).
The output (design/<name>.html) mirrors header.php + template + footer.php.
"""
import json, pathlib, re

FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")


def inline(t):
    return re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", t)


def render_md(name):
    """Tiny line-based markup used for long-form content (book chapters).

    ## h2 (TOC entry) · ### h3 · #### h4 · %% subtitle · - bullet · 1. numbered
    > pull-quote · !! callout · == formula · | table row (first row = header)
    A mid-content CTA is injected before the 3rd h2 (the natural half-way point).
    """
    lines = (SRC / "content" / f"{name}.md").read_text(encoding="utf-8").splitlines()
    out, toc, block, rows = [], [], None, []
    cta = ('<div class="inline-cta"><div><b>این فصل از کتاب سلطان قیف است</b>'
           '<span>۱۱ بخش دیگر، از مهندسی قیف تا استراتژی ورود به بازار.</span></div>'
           '<a class="btn btn-gold" href="book.html">معرفی کتاب</a></div>')

    def close():
        nonlocal block, rows
        if block:
            out.append(f"</{block}>")
        if rows:
            head, *body = rows
            out.append('<table><thead><tr>' + "".join(f"<th>{c}</th>" for c in head) + "</tr></thead><tbody>"
                       + "".join("<tr>" + "".join(f"<td>{c}</td>" for c in r) + "</tr>" for r in body)
                       + "</tbody></table>")
        block, rows = None, []

    for raw in lines:
        ln = raw.strip()
        if not ln:
            continue
        kind = "ul" if ln.startswith("- ") else "ol" if re.match(r"1\. ", ln) else "table" if ln.startswith("|") else None
        if (kind != block and not (kind == "table" and rows)) or (rows and kind != "table"):
            close()
        if kind == "table":
            rows.append([c.strip() for c in ln.strip("|").split("|")])
            continue
        if kind in ("ul", "ol"):
            if block != kind:
                out.append(f"<{kind}>"); block = kind
            out.append(f"<li>{inline(ln[2:].strip() if kind == 'ul' else ln[3:].strip())}</li>")
            continue
        if ln.startswith("## "):
            if len(toc) == 2:
                out.append(cta)
            hid = f"p{len(toc) + 1}"
            toc.append((hid, ln[3:]))
            out.append(f'<h2 id="{hid}">{inline(ln[3:])}</h2>')
        elif ln.startswith("#### "):
            out.append(f"<h4>{inline(ln[5:])}</h4>")
        elif ln.startswith("### "):
            out.append(f"<h3>{inline(ln[4:])}</h3>")
        elif ln.startswith("%% "):
            out.append(f'<p class="sub">{inline(ln[3:])}</p>')
        elif ln.startswith("> "):
            out.append(f"<blockquote>{inline(ln[2:])}</blockquote>")
        elif ln.startswith("!! "):
            out.append(f'<div class="callout">{inline(ln[3:])}</div>')
        elif ln.startswith("== "):
            out.append(f'<div class="formula">{inline(ln[3:])}</div>')
        else:
            out.append(f"<p>{inline(ln)}</p>")
    close()
    words = len(re.sub(r"<[^>]+>", " ", " ".join(out)).split())
    toc_html = "".join(f'<li><a href="#{i}">{t}</a></li>' for i, t in toc)
    return "\n".join(out), toc_html, str(max(1, round(words / 200))).translate(FA_DIGITS)


def expand_includes(body):
    for name in set(re.findall(r"<!--(?:content|toc|minutes):([\w-]+)-->", body)):
        html, toc, minutes = render_md(name)
        body = (body.replace(f"<!--content:{name}-->", html)
                    .replace(f"<!--toc:{name}-->", toc)
                    .replace(f"<!--minutes:{name}-->", minutes))
    return body

ROOT = pathlib.Path(__file__).parent
SRC = ROOT / "src"
HEADER = (SRC / "partials/header.html").read_text(encoding="utf-8")
FOOTER = (SRC / "partials/footer.html").read_text(encoding="utf-8")

SHELL = """<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<meta name="description" content="{description}">
<script>document.documentElement.classList.add('js')</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
</head>
<body data-page="{page}">
<a class="skip" href="#main">پرش به محتوای اصلی</a>
<div class="progress" aria-hidden="true"></div>
<button type="button" class="proto-note" onclick="this.remove()">نمونه‌ی طراحی — کادرهای نارنجی‌خط‌چین = داده‌ای که باید با اطلاعات واقعی پر شود. (برای بستن کلیک کنید)</button>

{header}
<main id="main">
{body}
</main>

{footer}
<script src="assets/site.js" defer></script>
</body>
</html>
"""


def build():
    pages = []
    for src in sorted((SRC / "pages").glob("*.html")):
        raw = src.read_text(encoding="utf-8")
        m = re.match(r"\s*<!--meta\s+(\{.*?\})\s*-->\s*", raw, re.S)
        if not m:
            raise SystemExit(f"{src.name}: missing <!--meta {{...}} --> header")
        meta = json.loads(m.group(1))
        body = expand_includes(raw[m.end():])
        out = SHELL.format(header=HEADER, footer=FOOTER, body=body,
                           title=meta["title"], description=meta.get("description", ""),
                           page=meta.get("page", ""))
        (ROOT / src.name).write_text(out, encoding="utf-8")
        pages.append((src.name, meta))
    gallery(pages)
    print("\n".join(f"  {n:<18} {m.get('template','')}" for n, m in pages))


ORDER = ["index", "service", "about", "article", "blog", "book", "chapter", "projects", "case", "assessment", "faq", "404"]


def gallery(pages):
    """pages.html: one card per prototype with the WP template and live URL it maps to."""
    pages = sorted(pages, key=lambda p: ORDER.index(p[0][:-5]) if p[0][:-5] in ORDER else 99)
    cards = "\n".join(
        f'      <a href="{n}"><b>{m["title"].split(" — ")[0].split(" | ")[0]}</b>'
        f'<small>{m.get("template","")}</small><code>{m.get("url","")}</code></a>'
        for n, m in pages)
    body = f"""<section class="phero on-dark"><div class="wrap"><div style="max-width:760px">
  <h1>نقشه‌ی <span class="grad">طراحی</span></h1>
  <p class="lead">همه‌ی قالب‌های صفحه، به ترتیب قیف. هر کارت نشان می‌دهد این طراحی به کدام قالب وردپرس و کدام آدرس فعلی سایت نگاشت می‌شود.</p>
</div></div></section>
<section class="sec"><div class="wrap"><div class="gallery">
{cards}
</div></div></section>"""
    out = SHELL.format(header=HEADER, footer=FOOTER, body=body, title="نقشه‌ی طراحی — thisismbahrami.ir",
                       description="", page="")
    (ROOT / "pages.html").write_text(out, encoding="utf-8")


if __name__ == "__main__":
    build()
