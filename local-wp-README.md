# تست محلی قالب (WordPress Playground)

بدون نیاز به PHP/MySQL روی سیستم؛ وردپرس واقعی روی Node اجرا می‌شود.

```bash
npx -y @wp-playground/cli@latest server --port=4620 \
  --mount="$PWD/theme/milad-bahrami:/wordpress/wp-content/themes/milad-bahrami" \
  --mount="$PWD/migration/mb-migration:/wordpress/wp-content/plugins/mb-migration" \
  --mount="$PWD/local-wp/data:/data" --blueprint="$PWD/local-wp/blueprint.json"
```
داده‌ها (`local-wp/data`، gitignore) از REST API سایت فعلی گرفته شده‌اند؛ `data/import.php` همان شناسه‌ها و نامک‌ها را می‌سازد و `mb_mig_setup()` را اجرا می‌کند.
مقایسه با baseline: `SNAPSHOT_COOKIE=playground_auto_login_already_happened=1 python3 scripts/seo_snapshot.py http://127.0.0.1:4620 /tmp/local.csv --paths-from docs/baseline-live-2026-09-25.csv`
