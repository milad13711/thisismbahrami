# thisismbahrami.ir

بازطراحی سایت شخصی میلاد بهرامی — مشاور سیستم‌سازی و تحول سازمانی.

| پوشه | محتوا |
|---|---|
| `design/` | نمونه‌های طراحی همه‌ی صفحات (HTML استاتیک). منبع در `design/src/`، خروجی با `python3 design/build.py`. نقشه‌ی صفحات: `design/pages.html` |
| `theme/` | قالب اختصاصی وردپرس (بعد از تأیید طراحی) |
| `scripts/seo_snapshot.py` | ثبت و مقایسه‌ی سیگنال‌های سئو (status، canonical، title، H1، schema) برای همه‌ی URLها |
| `docs/` | برنامه‌ی مهاجرت و baseline سئوی سایت فعلی |

پیش‌نمایش طراحی:

```bash
python3 design/build.py && python3 -m http.server 4610 --directory design
```

سپس `http://localhost:4610/pages.html`. ساختار `design/` عیناً به قالب وردپرس نگاشت می‌شود:
`assets/site.css` → `style.css` · `assets/site.js` → `assets/js/site.js` · `src/partials/header|footer.html` → `header.php`/`footer.php` · هر فایل `src/pages/*.html` → قالب ذکرشده در سربرگ `<!--meta -->` آن.
