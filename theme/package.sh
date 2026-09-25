#!/bin/sh
# Build uploadable zips: dist/milad-bahrami.zip (theme) and dist/mb-migration.zip (temporary plugin).
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
python3 "$ROOT/design/build.py" >/dev/null
python3 "$ROOT/theme/build-theme.py" >/dev/null
mkdir -p "$ROOT/dist"
rm -f "$ROOT/dist/"*.zip
(cd "$ROOT/theme" && zip -qr "$ROOT/dist/milad-bahrami.zip" milad-bahrami -x '*.DS_Store')
(cd "$ROOT/migration" && zip -qr "$ROOT/dist/mb-migration.zip" mb-migration -x '*.DS_Store')
ls -la "$ROOT/dist"
