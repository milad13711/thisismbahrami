"""HTML clean-up applied to each crawled page."""
import os, re

ERP_API = 'https://exirerp.ir/api'
ERP_TENANT = 't7debc5f8d8e'
ERP_FORM = 'site-assessment'
ERP_FIELD = '7a68504e-8f19-42c1-bfe6-a8d72596b02f'

_here = os.path.dirname(os.path.abspath(__file__))
def _lines(name):
    p = os.path.join(_here, name)
    return [l.strip() for l in open(p, encoding='utf-8') if l.strip() and not l.startswith('#')] if os.path.exists(p) else []
GONE = set(_lines('gone.txt'))
SPAM_FRAGMENTS = [x for x in _lines('exclude.txt') if not x.startswith('/')]
REL_LINK = re.compile(r'(<link\b[^>]*\brel=["\'](?:stylesheet|preload|icon|apple-touch-icon|manifest)["\'][^>]*?\bhref=["\'])([^"\']+)(["\'])', re.I)

MEDIA = {}
OVER = re.compile(r'https?://thisismbahrami\.ir/wp-admin/admin-ajax\.php\?action=rank_math_overlay_thumb(?:&#038;|&amp;|&)id=(\d+)[^"\'<> ]*')

def page(html, base):
    html = OVER.sub(lambda m: MEDIA.get(m.group(1), base + '/wp-content/themes/milad-bahrami/assets/img/portrait-900.jpg'), html)
    # date-based permalinks (set by the intruders) -> original post URLs
    html = re.sub(r'(https?://thisismbahrami\.ir)?/\d{4}/\d{2}/\d{2}/', lambda m: (m.group(1) or '') + '/', html)
    # WordPress head noise
    html = re.sub(r'<link[^>]+(?:oembed|api\.w\.org|rel=["\']shortlink|rel=["\']EditURI|wlwmanifest|xmlrpc)[^>]*>\s*', '', html, flags=re.I)
    html = re.sub(r'<link[^>]+type=["\']application/(?:json|rss\+xml)[^>]*>\s*', '', html, flags=re.I)
    html = re.sub(r'<link[^>]+rel=["\']alternate["\'][^>]+/wp-json/[^>]*>\s*', '', html, flags=re.I)
    html = re.sub(r'<link\b[^>]*\brel=["\'](?:next|prev)["\'][^>]*>\s*', '', html, flags=re.I)
    # spam leftovers: related-post cards and category chips
    for frag in SPAM_FRAGMENTS:
        html = re.sub(r'<a\b[^>]*href=["\'][^"\']*' + re.escape(frag) + r'[^"\']*["\'][^>]*>.*?</a>', '', html, flags=re.S)
    for slug in GONE:
        html = re.sub(r'<a\b[^>]*href=["\'](?:' + re.escape(base) + r')?/' + re.escape(slug) + r'/["\'][^>]*>.*?</a>\s*', '', html, flags=re.S)
    # asset/link URLs become root-relative (canonical / og / JSON-LD / alternate stay absolute)
    html = REL_LINK.sub(lambda m: m.group(1) + m.group(2).replace(base, '') + m.group(3), html)
    html = re.sub(r'(<(?:script|img|source|a|iframe)\b[^>]*?\b(?:href|src)=["\'])(' + re.escape(base) + r'[^"\']*)(["\'])',
                  lambda m: m.group(1) + (m.group(2)[len(base):] or '/') + m.group(3), html)
    html = re.sub(r'(\bsrcset=["\'])([^"\']+)(["\'])', lambda m: m.group(1) + m.group(2).replace(base, '') + m.group(3), html)
    # lead form: the browser talks straight to the ERP public form endpoint (no WordPress, no nonce)
    cfg = 'var MB={"erp":{"api":"%s","tenant":"%s","form":"%s","field":"%s"}};' % (ERP_API, ERP_TENANT, ERP_FORM, ERP_FIELD)
    html = re.sub(r'var MB\s*=\s*\{[^}]*\};?', cfg, html)
    html = re.sub(r'<script[^>]+id=["\']mb-site-js-extra["\'][^>]*>.*?</script>', '<script>' + cfg + '</script>', html, flags=re.S)
    return html
