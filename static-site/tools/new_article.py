#!/usr/bin/env python3
"""Publish an article from a Markdown file into the static site.

  python3 tools/new_article.py ARTICLE.md --cover IMG [--mid IMG --mid-alt "..." --mid-before "heading text"]
         [--link "phrase=/path/"]... [--date 2026-12-01] [--category "توسعه کسب و کار"] [--deploy] [--dry-run]

Front matter (between --- lines) understood: title, metaTitle, metaDescription, excerpt, focusKeyword,
tags: [a, b], slug, coverAlt, category (ignored if --category given).
Everything after a "meta" heading (links to add, prompts, checklists, sources...) is dropped.

Pipeline: markdown -> HTML (arrows flipped for RTL, ids, TOC) -> images to WebP -> page cloned from the golden
article template (tools/templates/golden-article.html) -> post-sitemap entry -> hubs/categories rebuilt
(tools/build_hub.py) -> optional deploy of only the changed files.
"""
import argparse, datetime, html as H, json, os, re, subprocess, sys, urllib.parse
import markdown
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, 'tools'))
SITE = os.path.join(ROOT, 'site')
BASE = 'https://thisismbahrami.ir'
FA = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')
MONTHS = ['ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر']
META_HEADINGS = ('لینک‌سازی داخلی', 'موجودیت‌ها', 'منابع', 'پرامپت', 'مشخصات تصاویر', 'چک‌لیست', 'پیشنهاد تصویر', 'منابع و مبانی', 'یادداشت')

def enc(path):  # WordPress-style lowercase percent-encoded path with trailing slash
    return '/' + urllib.parse.quote(path.strip('/'), safe='/-_.~').lower() + '/'

def parse_md(path):
    s = open(path, encoding='utf-8').read()
    fm = {}
    if s.startswith('---\n'):
        rest = s[4:]
        idx = rest.index('\n---\n')
        head, s = rest[:idx], rest[idx + 5:]
        for line in head.strip().split('\n'):
            if ':' not in line: continue
            k, v = line.split(':', 1); v = v.strip()
            if v.startswith('[') and v.endswith(']'): v = [x.strip() for x in v[1:-1].split(',') if x.strip()]
            fm[k.strip()] = v
    return fm, s

def clean_body(md):
    md = re.sub(r'^\s*# .+\n', '', md, count=1)  # H1 is the page title
    cut = len(md)
    for m in re.finditer(r'^(#{1,3}) (.+)$', md, re.M):
        if any(m.group(2).strip().startswith(h) for h in META_HEADINGS): cut = min(cut, m.start())
    md = md[:cut].rstrip()
    md = re.sub(r'\n---\s*$', '', md).rstrip()
    return md

def to_html(md):
    lines = md.split('\n')
    has_h1 = any(re.match(r'# \S', l) for l in lines)
    out = []
    for l in lines:
        m = re.match(r'(#{1,4}) (.+)', l)
        if m:
            lvl = len(m.group(1)) + (1 if has_h1 else 0)
            l = '#' * min(lvl, 4) + ' ' + m.group(2)
        out.append(l)
    md = '\n'.join(out).replace('→', '←')
    md = re.sub(r'^---\s*$', '', md, flags=re.M)
    h = markdown.markdown(md, extensions=['tables'])
    h = re.sub(r'<a href="([^"]*)"', lambda m: '<a href="%s"' % m.group(1), h)
    return h

def add_ids(h):
    toc = []
    def rep(m):
        toc.append((len(toc) + 1, re.sub(r'<[^>]+>', '', m.group(1))))
        return '<h2 id="s%d">%s</h2>' % (len(toc), m.group(1))
    return re.sub(r'<h2>(.*?)</h2>', rep, h, flags=re.S), toc

def autolink(h, links):
    done = set()
    parts = re.split(r'(<[^>]+>)', h)
    depth_a = depth_h = 0
    for i, p in enumerate(parts):
        if p.startswith('<'):
            t = p.lower()
            if t.startswith('<a ') or t == '<a>': depth_a += 1
            elif t == '</a>': depth_a = max(0, depth_a - 1)
            elif re.match(r'<h[1-6]\b', t): depth_h += 1
            elif re.match(r'</h[1-6]>', t): depth_h = max(0, depth_h - 1)
            continue
        if depth_a or depth_h: continue
        for phrase, url in links:
            if phrase in done or phrase not in p: continue
            parts[i] = p = p.replace(phrase, '<a href="%s">%s</a>' % (url, phrase), 1); done.add(phrase)
    missing = [ph for ph, _ in links if ph not in done]
    return ''.join(parts), missing

def image_to_webp(src, alt, when):
    name = re.sub(r'[\s‌،,؟?:؛;"\'«»()]+', '-', alt).strip('-')[:110] or 'image'
    rel = 'wp-content/uploads/%04d/%02d/%s.webp' % (when.year, when.month, name)
    dst = os.path.join(SITE, rel); os.makedirs(os.path.dirname(dst), exist_ok=True)
    im = Image.open(src).convert('RGB')
    if im.width > 1600: im = im.resize((1600, round(im.height * 1600 / im.width)), Image.LANCZOS)
    im.save(dst, 'WEBP', quality=82, method=6)
    return '/' + rel, im.size

def fig(path, size, alt, lazy=True):
    return '<figure class="wp-block-image"><img src="%s" alt="%s" width="%d" height="%d" loading="%s" decoding="async"></figure>' % (path, H.escape(alt), size[0], size[1], 'lazy' if lazy else 'eager')

def golden():
    g = open(os.path.join(ROOT, 'tools', 'templates', 'golden-article.html'), encoding='utf-8').read()
    meta = json.load(open(os.path.join(ROOT, 'tools', 'templates', 'golden-article.json'), encoding='utf-8'))
    return g, meta

def swap(text, old, new):
    """Replace a value everywhere, including JSON-escaped and HTML-escaped forms."""
    if not old or old == new: return text
    forms = [(old, new), (json.dumps(old, ensure_ascii=False)[1:-1], json.dumps(new, ensure_ascii=False)[1:-1]),
             (json.dumps(old)[1:-1], json.dumps(new)[1:-1]), (H.escape(old), H.escape(new))]
    for a, b in forms:
        text = text.replace(a, b)
    return text

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('md'); ap.add_argument('--cover', required=True); ap.add_argument('--cover-alt')
    ap.add_argument('--mid'); ap.add_argument('--mid-alt'); ap.add_argument('--mid-before')
    ap.add_argument('--link', action='append', default=[]); ap.add_argument('--date')
    ap.add_argument('--category', default='توسعه کسب و کار'); ap.add_argument('--slug')
    ap.add_argument('--deploy', action='store_true'); ap.add_argument('--dry-run', action='store_true')
    a = ap.parse_args()
    fm, body_md = parse_md(a.md)
    title = fm.get('title') or sys.exit('front matter needs title')
    slug = a.slug or fm.get('slug') or sys.exit('front matter needs slug')
    meta_title = fm.get('metaTitle', title); desc = fm.get('metaDescription', ''); excerpt = fm.get('excerpt', desc)
    keyword = fm.get('focusKeyword', ''); tags = fm.get('tags', []) if isinstance(fm.get('tags'), list) else []
    cover_alt = a.cover_alt or fm.get('coverAlt') or title
    when = datetime.datetime.fromisoformat(a.date) if a.date else datetime.datetime.now()
    iso = when.strftime('%Y-%m-%dT%H:%M:%S') + '+03:30'
    human = '%d %s %d' % (when.day, MONTHS[when.month - 1], when.year)

    html_body = to_html(clean_body(body_md))
    links = []
    for l in a.link:
        ph, _, u = l.partition('='); links.append((ph.strip(), u.strip()))
    html_body, missing = autolink(html_body, links)
    html_body, toc = add_ids(html_body)
    words = len(re.sub(r'<[^>]+>', ' ', html_body).split())
    minutes = max(1, round(words / 200))

    cover_path, cover_size = image_to_webp(a.cover, cover_alt, when)
    if a.mid:
        mid_alt = a.mid_alt or sys.exit('--mid-alt required with --mid')
        mid_path, mid_size = image_to_webp(a.mid, mid_alt, when)
        figure = fig(mid_path, mid_size, mid_alt)
        h2s = [m.start() for m in re.finditer(r'<h2 id=', html_body)]
        pos = None
        if a.mid_before:
            m = re.search(r'<h2 id="s\d+">[^<]*' + re.escape(a.mid_before), html_body)
            pos = m.start() if m else None
        if pos is None and h2s: pos = h2s[min(2, len(h2s) - 1)]
        html_body = html_body[:pos] + figure + '\n' + html_body[pos:] if pos is not None else html_body + figure

    g, gm = golden()
    # mid-article call-to-action (same box as on the other articles), placed before the 3rd section
    cta = '<div class="inline-cta"><div><b>سازمان شما کجا نشت می‌کند؟</b><span>در ارزیابی سازمان، گلوگاه‌ها را با داده‌ی واقعی پیدا می‌کنیم.</span></div><a class="btn btn-gold" href="/%D8%A7%D8%B1%D8%B2%DB%8C%D8%A7%D8%A8%DB%8C-%D8%B3%D8%A7%D8%B2%D9%85%D8%A7%D9%86/">ارزیابی رایگان اولیه</a></div>'
    if len(toc) >= 4 and '<h2 id="s3">' in html_body and 'inline-cta' not in html_body:
        html_body = html_body.replace('<h2 id="s3">', cta + '<h2 id="s3">', 1)
    page = g
    new_path = enc(slug); new_url = BASE + new_path
    # longest values first so that shorter ones inside them are not clobbered
    for old, new in sorted([
        (gm['meta_title'], meta_title), (gm['h1'], title), (gm['description'], desc),
        (gm['canonical'], new_url), (gm['path'], new_path),
        (gm['image_alt'], cover_alt), (gm['image'], cover_path), (gm['published'], iso), (gm['human_date'], human),
        (gm['keyword'], keyword or title), (gm['read_label'], '%d دقیقه' % minutes), (gm['hero_read'], '%s دقیقه مطالعه' % str(minutes).translate(FA))], key=lambda t: -len(t[0] or '')):
        page = swap(page, old, new)
    page = page.replace('width="%d" height="%d"' % tuple(gm['image_size']), 'width="%d" height="%d"' % cover_size)
    page = page.replace('content="%d"' % gm['image_size'][0], 'content="%d"' % cover_size[0]).replace('content="%d"' % gm['image_size'][1], 'content="%d"' % cover_size[1])
    page = re.sub(r'(?:<meta property="article:tag"[^>]*>\s*)+', ''.join('<meta property="article:tag" content="%s" />\n' % H.escape(t) for t in tags), page, count=1)
    page = re.sub(r'(<meta property="article:section" content=")[^"]*', lambda m: m.group(1) + H.escape(a.category), page)
    page = re.sub(r'<article class="[^"]*"', '<article class="post type-post status-publish format-standard has-post-thumbnail hentry"', page, count=1)
    toc_li = ''.join('<li><a href="#s%d">%s</a></li>' % (n, H.escape(t)) for n, t in toc)
    page = re.sub(r'(<details class="toc-m"><summary>[^<]*</summary><ol>).*?(</ol></details>)', lambda m: m.group(1) + toc_li + m.group(2), page, count=1, flags=re.S)
    page = re.sub(r'(<nav class="toc"[^>]*><h2>[^<]*</h2><ol>).*?(</ol>)', lambda m: m.group(1) + toc_li + m.group(2), page, count=1, flags=re.S)
    cover_fig = '<figure class="post-cover"><img width="%d" height="%d" src="%s" alt="%s" loading="eager" fetchpriority="high" sizes="(max-width: 900px) 100vw, 760px" decoding="async"></figure>' % (cover_size[0], cover_size[1], cover_path, H.escape(cover_alt))
    page = re.sub(r'<figure class="post-cover">.*?</figure>', lambda m: cover_fig, page, count=1, flags=re.S)
    page = re.sub(r'(<div class="prose">).*?(</div>\s*<p class="updated")', lambda m: m.group(1) + '\n' + html_body + '\n' + m.group(2), page, count=1, flags=re.S)
    page = re.sub(r'<section class="comments".*?</section>\s*', '', page, flags=re.S)
    page = re.sub(r'(<p class="lead"[^>]*>).*?(</p>)', lambda m: m.group(1) + H.escape(excerpt) + m.group(2), page, count=1, flags=re.S)

    rel_page = urllib.parse.unquote(new_path).strip('/') + '/index.html'
    changed = [rel_page, cover_path.lstrip('/')] + ([mid_path.lstrip('/')] if a.mid else [])
    if a.dry_run:
        print('DRY RUN', rel_page, 'words', words, 'minutes', minutes, 'h2', len(toc), 'missing links', missing); return
    os.makedirs(os.path.join(SITE, os.path.dirname(rel_page)), exist_ok=True)
    open(os.path.join(SITE, rel_page), 'w', encoding='utf-8').write(page)

    # sitemap entry
    sm_path = os.path.join(SITE, 'post-sitemap.xml')
    sm = open(sm_path, encoding='utf-8').read()
    if new_url not in sm:
        sm = sm.replace('</urlset>', '<url><loc>%s</loc><lastmod>%s</lastmod></url>\n</urlset>' % (new_url, iso))
        open(sm_path, 'w', encoding='utf-8').write(sm)
    changed.append('post-sitemap.xml')

    # hubs / categories / thumbnails, then related-articles block of the new page
    import build_hub
    build_hub.SITE = SITE
    build_hub.written.clear(); build_hub.build()
    changed += build_hub.written
    posts = [p for p in build_hub.scan() if p['path'] != urllib.parse.unquote(new_path)]
    related = ''.join(build_hub.card(p) for p in posts[:3])
    cur = open(os.path.join(SITE, rel_page), encoding='utf-8').read()
    cur = re.sub(r'(<div class="posts">).*?(</div>\s*</div>\s*</section>\s*<!-- =+ ۴)', lambda m: m.group(1) + related + m.group(2), cur, count=1, flags=re.S)
    if 'id="mb-comments"' not in cur:
        from add_comments import SECTION
        cur = cur.replace('</article>', SECTION % urllib.parse.unquote(new_path).strip('/') + '\n</article>', 1)
    open(os.path.join(SITE, rel_page), 'w', encoding='utf-8').write(cur)
    lst = os.path.join(ROOT, 'tools', 'last-publish.txt')
    open(lst, 'w', encoding='utf-8').write('\n'.join(dict.fromkeys(changed)))
    print('PAGE', new_url, '| words', words, '| read', minutes, 'min | h2', len(toc), '| files', len(set(changed)))
    if missing: print('WARNING: link phrases not found in text:', missing)
    if a.deploy:
        subprocess.run([sys.executable, '-u', os.path.join(ROOT, 'tools', 'deploy.py'), '/thisismbahrami.ir/public_html', '--only', lst], check=True)
        print('deployed; check', new_url)

if __name__ == '__main__':
    main()
