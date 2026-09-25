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
        body = raw[m.end():]
        out = SHELL.format(header=HEADER, footer=FOOTER, body=body,
                           title=meta["title"], description=meta.get("description", ""),
                           page=meta.get("page", ""))
        (ROOT / src.name).write_text(out, encoding="utf-8")
        pages.append((src.name, meta))
    gallery(pages)
    print("\n".join(f"  {n:<18} {m.get('template','')}" for n, m in pages))


ORDER = ["index", "service", "about", "article", "blog", "book", "projects", "case", "assessment", "faq", "404"]


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
