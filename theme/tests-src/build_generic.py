# -*- coding: utf-8 -*-
"""Builds meta (inc/tests/<slug>.json) + data (assets/tests/<slug>.json) for the dimension-scored tests."""
import json, os
from gen_a import DISC, STRENGTHS, MI
from gen_b import P16, ENT, MOT, COM
T = os.path.join(os.path.dirname(__file__), "..", "milad-bahrami")
SCALE = [{"v":0,"l":"کاملاً مخالفم","e":"😖"},{"v":1,"l":"مخالفم","e":"🙁"},{"v":2,"l":"نظری ندارم","e":"😐"},{"v":3,"l":"موافقم","e":"🙂"},{"v":4,"l":"کاملاً موافقم","e":"😍"}]

def build(t):
    n, cnt = t["name"], t["count"]
    meta = dict(
      slug=t["slug"], order=t["order"], name=n, short=t["short"], emoji=t["emoji"], minutes=t["minutes"], count=cnt,
      h1_tail=t["h1_tail"], tagline=t["tagline"],
      seo_title=f'{n} رایگان آنلاین؛ نتیجه و تفسیر کامل بدون پرداخت | میلاد بهرامی',
      seo_description=f'{n} کاملاً رایگان و بدون ثبت‌نام؛ با {cnt} سؤال در {t["minutes"]} دقیقه. تفسیر کامل نتیجه، نمودار و پیشنهادهای عملی را همین حالا رایگان دریافت کنید.',
      data=t["slug"], renderer="dims", renderer_script="dims", intro=[t["intro"]],
      steps=[["۱","به سؤال‌ها پاسخ دهید",f"{cnt} جمله‌ی کوتاه؛ فقط بگویید تا چه اندازه با هر کدام موافقید. جواب درست یا غلط وجود ندارد."],
             ["۲","نتیجه را فوری ببینید","نمودار و کد نتیجه بلافاصله و بدون ثبت‌نام نمایش داده می‌شود."],
             ["۳","تفسیر کامل را رایگان بخوانید","توضیح کامل هر بعد، نقاط قوت، نکات توجه و قدم‌های بعدی؛ همه رایگان."]],
      faq=[[f"{n} رایگان است؟","بله، شرکت در این تست و دریافت تفسیر کامل نتیجه کاملاً رایگان است. هیچ بخشی از نتیجه پشت پرداخت قفل نیست و نیازی به ثبت‌نام هم ندارید."],
           ["اطلاعات من ذخیره می‌شود؟","خیر. پاسخ‌ها فقط در مرورگر شما پردازش می‌شود و به سرور ارسال نمی‌شود."],
           [f"{n} چقدر زمان می‌برد؟",f"حدود {t['minutes']} دقیقه؛ {cnt} سؤال کوتاه."],
           ["نتیجه‌ی تست قطعی و علمی است؟","این تست یک ابزار خودشناسی مستقل است، نه تشخیص تخصصی یا آزمون رسمی. نتیجه را نقطه‌ی شروع تأمل و گفت‌وگو بدانید و برای تصمیم‌های مهم از متخصص کمک بگیرید."],
           ["می‌توانم تست را دوباره انجام دهم؟","بله، هر چند بار که بخواهید. با تغییر شرایط و تجربه، نتیجه هم می‌تواند تغییر کند."]],
      about=t["about"])
    qs = []
    for d, arr in t["qs"].items():
        for q in arr:
            qs.append({"d": d, "t": q})
    data = dict(id=t["slug"], renderer="dims", scale=SCALE, stem="تا چه اندازه با این جمله موافقید؟", gates=[], dims=t["dims"], questions=qs,
                steps=t["steps"], stepsTitle=t["stepsTitle"], topN=t.get("topN", 3), topLabel=t.get("topLabel"), summary=t.get("summary"))
    if t.get("pairs"):
        data["pairs"] = t["pairs"]; data["types"] = t["types"]
    assert len(qs) == cnt, (t["slug"], len(qs), cnt)
    json.dump(meta, open(f"{T}/inc/tests/{t['slug']}.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    json.dump(data, open(f"{T}/assets/tests/{t['slug']}.json", "w", encoding="utf-8"), ensure_ascii=False, separators=(",", ":"))
    print(t["slug"], len(qs))
for t in (DISC, STRENGTHS, MI, P16, ENT, MOT, COM):
    build(t)
