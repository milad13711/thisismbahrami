#!/usr/bin/env python3
"""Convert Elementor-rendered post HTML into clean, theme-styled HTML.

    python3 migration/elementor_to_html.py export.json cleaned.json

export.json: [{"id": 123, "type": "post", "content": "<rendered html>"}, ...]
             (produced on the server by migration/export-rendered.php, or from the REST API)
cleaned.json: {"123": "<clean html>", ...} + a per-post integrity report on stdout.

Rules
- Unwrap every Elementor/Phlox layout wrapper (sections, columns, widget containers).
- Keep semantic content: headings, paragraphs, lists, tables, images, links, blockquotes.
- Button widgets become <p><a class="btn btn-gold">…</a></p>.
- Demote in-content <h1> to <h2>: the template prints the post title as the only H1.
- Drop inline styles, data-* attributes and builder classes; drop empty wrappers.
- Integrity check: visible words, images and links must be preserved (report mismatches).
"""
import json, re, sys
from bs4 import BeautifulSoup, Comment, NavigableString

KEEP_TAGS = {"h2", "h3", "h4", "h5", "h6", "p", "ul", "ol", "li", "table", "thead", "tbody", "tr", "th", "td",
             "img", "a", "strong", "b", "em", "i", "blockquote", "figure", "figcaption", "br", "hr", "span",
             "iframe", "video", "source", "sup", "sub", "code", "pre", "dl", "dt", "dd", "picture"}
KEEP_ATTRS = {"a": {"href", "target", "rel", "title"}, "img": {"src", "alt", "width", "height", "srcset", "sizes"},
              "iframe": {"src", "width", "height", "allowfullscreen", "title"}, "source": {"src", "srcset", "type"},
              "video": {"src", "controls", "poster"}, "td": {"colspan", "rowspan"}, "th": {"colspan", "rowspan"}}


def words(html):
    text = BeautifulSoup(html, "html.parser").get_text(" ")
    return len(re.findall(r"\w+", text))


def clean(html):
    soup = BeautifulSoup(html, "html.parser")
    for c in soup.find_all(string=lambda s: isinstance(s, Comment)):
        c.extract()
    for t in soup.find_all(["script", "style", "noscript", "svg"]):
        t.decompose()

    # Button widgets → a styled link paragraph.
    for w in soup.select(".elementor-widget-button"):
        a = w.find("a")
        if a:
            label = a.get_text(" ", strip=True)
            new = soup.new_tag("p")
            link = soup.new_tag("a", href=a.get("href", "#"))
            link["class"] = "btn btn-gold"
            link.string = label
            new.append(link)
            w.replace_with(new)
        else:
            w.decompose()

    # Slides widget → heading + text + button per slide, in order.
    for w in soup.select(".elementor-widget-slides"):
        frag = []
        for s in w.select(".swiper-slide, .elementor-repeater-item"):
            h = s.select_one(".elementor-slide-heading")
            d = s.select_one(".elementor-slide-description")
            b = s.select_one(".elementor-slide-button, a")
            if h:
                frag.append(f"<h3>{h.get_text(' ', strip=True)}</h3>")
            if d:
                frag.append(f"<p>{d.get_text(' ', strip=True)}</p>")
            if b and b.get("href"):
                frag.append(f'<p><a class="btn btn-gold" href="{b["href"]}">{b.get_text(" ", strip=True) or "بیشتر"}</a></p>')
        w.replace_with(BeautifulSoup("".join(dict.fromkeys(frag)), "html.parser"))

    for h1 in soup.find_all("h1"):
        h1.name = "h2"

    # Unwrap anything that is not a semantic tag (divs, sections, builder spans…).
    changed = True
    while changed:
        changed = False
        for t in soup.find_all(True):
            if t.name not in KEEP_TAGS:
                t.unwrap()
                changed = True

    for t in soup.find_all(True):
        allowed = KEEP_ATTRS.get(t.name, set())
        keep_class = t.name == "a" and "btn" in (t.get("class") or [])
        cls = t.get("class")
        t.attrs = {k: v for k, v in t.attrs.items() if k in allowed}
        if keep_class:
            t["class"] = cls
        if t.name == "img":
            t["loading"] = "lazy"
            t["decoding"] = "async"
        if t.name == "a" and t.get("target") == "_blank":
            t["rel"] = "noopener"
    for s in soup.find_all("span"):
        s.unwrap()

    # Bare text left at top level (from unwrapped text widgets) → paragraphs.
    out, buf = [], []
    for node in list(soup.contents):
        if isinstance(node, NavigableString):
            if node.strip():
                buf.append(str(node).strip())
            continue
        if node.name in ("strong", "b", "em", "i", "a", "br"):
            buf.append(str(node))
            continue
        if buf:
            out.append("<p>" + " ".join(buf) + "</p>")
            buf = []
        out.append(str(node))
    if buf:
        out.append("<p>" + " ".join(buf) + "</p>")
    html = "\n".join(out)

    # Remove empty paragraphs / headings and collapse whitespace.
    html = re.sub(r"<(p|h[2-6]|li)>\s*(?:&nbsp;| |<br/?>|\s)*</\1>", "", html)
    html = re.sub(r"[ \t\r\f\v]+", " ", html)
    html = re.sub(r"\n\s*\n+", "\n", html)
    return html.strip()


def main():
    src, dst = sys.argv[1], sys.argv[2]
    items = json.load(open(src, encoding="utf-8"))
    result, problems = {}, 0
    print(f"{'id':>6} {'type':<9} {'words':>11} {'img':>7} {'links':>7}  status")
    for it in items:
        before = it["content"]
        after = clean(before)
        result[str(it["id"])] = after
        wb, wa = words(before), words(after)
        ib, ia = before.count("<img"), after.count("<img")
        lb = len(re.findall(r"<a\s", before)); la = len(re.findall(r"<a\s", after))
        ok = wa >= wb * 0.99 and ia >= ib and la >= lb * 0.95
        problems += not ok
        print(f"{it['id']:>6} {it.get('type','post'):<9} {wb:>5}->{wa:<5} {ib:>3}->{ia:<3} {lb:>3}->{la:<3}  {'ok' if ok else 'CHECK'}")
    json.dump(result, open(dst, "w", encoding="utf-8"), ensure_ascii=False, indent=0)
    print(f"\n{len(items)} items, {problems} need manual check -> {dst}")
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    main()
