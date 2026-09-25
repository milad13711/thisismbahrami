#!/bin/sh
# Copy the design system (single source of truth: design/assets) into the theme.
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
T="$ROOT/theme/milad-bahrami"
cp "$ROOT/design/assets/site.css" "$T/assets/css/site.css"
cp "$ROOT/design/assets/site.js"  "$T/assets/js/site.js"
cp "$ROOT/design/assets/img/"*   "$T/assets/img/"
mkdir -p "$T/assets/fonts" && cp "$ROOT/design/assets/fonts/"* "$T/assets/fonts/"
# site.css lives in assets/css/, fonts in assets/fonts/
sed -i '' 's#url("fonts/#url("../fonts/#g' "$T/assets/css/site.css"
echo "synced design/assets -> theme/milad-bahrami/assets"
