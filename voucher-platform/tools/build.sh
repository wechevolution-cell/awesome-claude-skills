#!/usr/bin/env bash
# Builds dist/voucher-platform-site-php.zip from site/ without local config, database, logs or uploads.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
OUT="$PWD/dist/voucher-platform-site-php.zip"
[ -f "$OUT" ] && rm "$OUT"
find site -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
cd site
zip -qr "$OUT" . \
  -x 'app/config.php' \
  -x 'data/*' -x 'uploads/*' \
  -x '*.DS_Store'
zip -q "$OUT" data/.htaccess data/index.html uploads/.htaccess uploads/index.html
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
