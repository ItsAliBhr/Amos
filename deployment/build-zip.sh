#!/usr/bin/env bash
# Builds AMOS-cPanel-Deployment.zip:
#   amos_app/     full application with production vendor/ (no dev packages), no .env, empty storage
#   public_html/  front controller + .htaccess + prebuilt assets for the recommended cPanel layout
#   INSTALL-FA.md quick install guide
# Usage: deployment/build-zip.sh [output_dir]     (env COMPOSER_PREFER=--prefer-source to force git sources)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/dist}"
BUILD="$(mktemp -d)"
APP="$BUILD/amos_app"
PUB="$BUILD/public_html"
mkdir -p "$APP" "$PUB" "$OUT"

echo "==> Copying source"
rsync -a "$ROOT/" "$APP/" \
  --exclude '.git' --exclude '.github' --exclude 'node_modules' --exclude 'vendor' --exclude 'dist' \
  --exclude '.env' --exclude '.env.backup' --exclude '.phpunit.cache' --exclude '.phpunit.result.cache' \
  --exclude 'storage/logs/*.log' --exclude 'storage/app/installed.lock' \
  --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/app/private/*' --exclude 'storage/app/receipts' --exclude 'storage/app/investments' --exclude 'storage/app/ideas' \
  --exclude 'bootstrap/cache/*.php' --exclude 'deployment/public_html' --exclude '*.zip'

# keep the storage skeleton (.gitignore placeholders)
for d in storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
  mkdir -p "$APP/$d"
done
for d in storage/app/private storage/framework/sessions storage/framework/views storage/logs; do
  [ -f "$APP/$d/.gitignore" ] || printf '*\n!.gitignore\n' > "$APP/$d/.gitignore"
done

echo "==> Installing production dependencies (no dev)"
( cd "$APP" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress ${COMPOSER_PREFER:---prefer-dist} --no-scripts )
find "$APP/vendor" -name .git -type d -prune -exec rm -rf {} +
( cd "$APP" && APP_KEY=base64:$(head -c32 /dev/urandom | base64) php artisan package:discover --ansi >/dev/null )

# deny-all guards for private folders (defence in depth if ever exposed)
for d in app bootstrap config database lang resources routes storage vendor docs deployment scripts tests; do
  [ -d "$APP/$d" ] && printf 'Require all denied\n' > "$APP/$d/.htaccess"
done

echo "==> Preparing public_html"
cp -R "$APP/public/." "$PUB/"
cp "$ROOT/deployment/public_html/index.php" "$PUB/index.php"
cp "$ROOT/docs/INSTALL-cPanel.md" "$BUILD/INSTALL-FA.md"

echo "==> Sanity checks"
test ! -e "$APP/.env" || { echo ".env must not be shipped"; exit 1; }
test ! -d "$APP/node_modules" || { echo "node_modules must not be shipped"; exit 1; }
if grep -rIl --exclude-dir=vendor -E "DB_PASSWORD=.+[^=]$" "$APP" | grep -v '.env.example' | grep -q .; then echo "possible secret found"; exit 1; fi

echo "==> Zipping"
rm -f "$OUT/AMOS-cPanel-Deployment.zip"
( cd "$BUILD" && zip -qr -X "$OUT/AMOS-cPanel-Deployment.zip" amos_app public_html INSTALL-FA.md )
rm -rf "$BUILD"
ls -lh "$OUT/AMOS-cPanel-Deployment.zip"
