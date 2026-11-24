#!/usr/bin/env python3
"""Make the lead-form script submit straight to the Exir ERP public form when MB.erp is defined."""
import sys

OLD = """      if (!window.MB) return true;
      const fd = new FormData(wiz);"""

NEW = """      if (!window.MB) return true;
      if (MB.erp) {
        // Static site: post straight to the Exir ERP public form (creates the CRM contact + notifies the owner).
        const data = new FormData(wiz);
        const f = k => (data.get(k) || '').toString().trim();
        const group = n => { const g = $('.opts[data-name="' + n + '"]', wiz); return g ? $$('.opt[aria-pressed="true"]', g).map(o => o.textContent.trim()).join('، ') : ''; };
        const tel = f('tel').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[^\\d+]/g, '');
        const err2 = $('#sendErr', wiz);
        if (f('website')) return true; // honeypot
        if (!f('org') || !f('name') || tel.length < 10) { err2.textContent = 'نام سازمان، نام و شماره موبایل لازم است.'; err2.hidden = false; return false; }
        const rows = [['سازمان', f('org')], ['صنعت', f('industry')], ['تعداد نیرو', group('size')], ['چالش‌ها', group('challenges')], ['هدف', group('goal')], ['توضیح', f('note')], ['سمت', f('role')], ['صفحه', location.href]];
        const details = rows.filter(r => r[1]).map(r => r[0] + ': ' + r[1]).join('\\n');
        next.disabled = true; next.textContent = 'در حال ارسال…';
        try {
          const r = await fetch(MB.erp.api + '/public/forms/' + MB.erp.tenant + '/' + MB.erp.form + '/submit', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({respondentName: f('name'), respondentPhone: tel, answers: [{fieldId: MB.erp.field, valueText: details}]})
          });
          if (!r.ok) throw new Error('erp');
          return true;
        } catch (e) {
          err2.textContent = 'ارسال انجام نشد؛ دوباره تلاش کنید یا تماس بگیرید.'; err2.hidden = false; return false;
        } finally { next.disabled = false; next.textContent = 'ارسال و دریافت نتیجه'; }
      }
      const fd = new FormData(wiz);"""

ROOT = '/Users/m/Documents/thisismbahrami.ir/'
for rel in ('design/assets/site.js', 'theme/milad-bahrami/assets/js/site.js'):
    s = open(ROOT + rel, encoding='utf-8').read()
    if 'MB.erp' in s:
        print('already patched', rel)
        continue
    if OLD not in s:
        sys.exit('anchor not found in ' + rel)
    open(ROOT + rel, 'w', encoding='utf-8').write(s.replace(OLD, NEW, 1))
    print('patched', rel)
