# برنامه‌ی مهاجرت بدون آسیب سئو

## تصمیم‌ها (۱۴۰۵/۰۷/۰۳ — 2026-09-25)
- پلتفرم: وردپرس + Rank Math، با **قالب اختصاصی سبک بدون Elementor**.
- محل: همین هاست؛ نصب تمیز روی ساب‌دامین استیجینگ، بعد جایگزینی.
- نام Entity: **میلاد بهرامی** (alternateName: محمدامین بهرامی).
- تصمیم درباره‌ی مقالات نامرتبط فقط پس از خروجی Search Console.

## وضعیت فعلی (baseline)
- WordPress 7.1.2 · Phlox Pro (پوشه‌ی `PhloxPro-WPMonster` ⇒ احتمالاً نسخه‌ی نال — ریسک امنیتی) · Elementor Pro · WooCommerce · Ultimate Member · Rank Math · ArvanCloud.
- پیوند یکتا: `/%postname%/` · دسته‌ها بدون `/category/` · نمونه‌کار `/portfolio/…` · FAQ `/faq/…`.
- ۵۲ URL در sitemap، همه 200 → `docs/baseline-live-2026-09-25.csv`.
- ~۲۰ نوشته و همه‌ی برگه‌های اصلی Elementor هستند ⇒ محتوا باید به بلوک/HTML تمیز تبدیل شود.
- مشکلات فعلی که قالب جدید رفع می‌کند: ۹ نمونه‌کار بدون H1، صفحه‌ی سلطان قیف با ۹ H1، دو meta description در Home، دو دسته بدون description.

## قوانین غیرقابل‌مذاکره
1. هیچ URL از baseline حذف یا تغییر نمی‌کند مگر با 301 ثبت‌شده در `docs/redirects.csv`.
2. ساختار پیوند یکتا، slug دسته‌ها و CPTهای `portfolio` و `faq` عیناً حفظ می‌شوند.
3. داده‌های Rank Math (title/description/schema/redirections) با دیتابیس منتقل می‌شوند، نه دستی.
4. تصاویر با همان مسیر `wp-content/uploads/...` منتقل می‌شوند.
5. استیجینگ `noindex` + رمزدار؛ بعد از جایگزینی حتماً noindex برداشته شود.
6. قبل از جایگزینی: `seo_snapshot.py --compare` باید صفر خطا بدهد.

## فازها
- [x] ۰. baseline سئو + ریپو
- [ ] ۱. هویت بصری و طراحی Home (نمونه در `design/`) ← **در حال انجام**
- [ ] ۲. قالب وردپرس: header/footer، single، page، archive، portfolio، faq، landing خدمات، schema شخص/سازمان
- [ ] ۳. استیجینگ روی همین هاست + کپی دیتابیس/آپلودها
- [ ] ۴. تبدیل محتوای Elementor به بلوک (اسکریپت) + بازبینی دستی
- [ ] ۵. صفحات جدید (Money pages) و بازنویسی Home/About با Positioning جدید
- [ ] ۶. حذف افزونه‌های اضافه (Elementor، Woo، UM در صورت عدم نیاز) + کنترل سرعت
- [ ] ۷. مقایسه‌ی snapshot، جایگزینی، purge کش آروان، ارسال sitemap در Search Console
- [ ] ۸. پایش ۴ هفته‌ای: Coverage، 404ها، رتبه‌ی کوئری‌های اصلی

## ورودی‌های لازم از میلاد
- خروجی Search Console (صفحات + کوئری‌ها، ۱۶ ماه)
- دسترسی هاست (cPanel/DirectAdmin) و پنل آروان
- اعداد واقعی: تعداد پروژه‌ی سیستم‌سازی، صنایع، فرآیندهای طراحی‌شده
- جزئیات ۳ Case Study اول (مسئله/سیستم/نتیجه)
- آدرس و تلفن رسمی (NAP) برای Local SEO
