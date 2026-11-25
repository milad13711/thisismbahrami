#!/usr/bin/env python3
"""Insert the comments section placeholder into every article page of site/ (idempotent)."""
import os, re, sys, urllib.parse
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = os.path.join(ROOT, 'site')
SECTION = ('<section id="mb-comments" data-post="%s" aria-labelledby="mb-comments-title"><h2 id="mb-comments-title">دیدگاه‌ها</h2><div data-list></div>'
           '<form novalidate><label>نام <input name="name" required maxlength="60" autocomplete="name"></label>'
           '<label>ایمیل (اختیاری، نمایش داده نمی‌شود) <input name="email" type="email" maxlength="120" autocomplete="email" dir="ltr"></label>'
           '<label>دیدگاه شما <textarea name="body" rows="4" required maxlength="2000"></textarea></label>'
           '<label class="hp" aria-hidden="true">وب‌سایت <input name="website" tabindex="-1" autocomplete="off"></label>'
           '<div class="c-msg" data-msg hidden></div><button type="submit" class="btn btn-ink">ارسال دیدگاه</button>'
           '<small>دیدگاه بعد از بررسی مدیر نمایش داده می‌شود.</small></form></section>')
def main():
    changed = []
    xml = open(os.path.join(SITE, 'post-sitemap.xml'), encoding='utf-8').read()
    for u in re.findall(r'<loc>([^<]+)</loc>', xml):
        slug = urllib.parse.unquote(urllib.parse.urlparse(u).path).strip('/')
        f = os.path.join(SITE, slug, 'index.html')
        if not slug or not os.path.exists(f): continue
        s = open(f, encoding='utf-8').read()
        if 'og:type" content="article"' not in s or 'id="mb-comments"' in s or '</article>' not in s: continue
        s = s.replace('</article>', SECTION % slug.replace('"', '') + '\n</article>', 1)
        open(f, 'w', encoding='utf-8').write(s); changed.append(os.path.join(slug, 'index.html'))
    print('\n'.join(changed)); print('pages updated:', len(changed), file=sys.stderr)
if __name__ == '__main__': main()
