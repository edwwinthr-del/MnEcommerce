#!/usr/bin/env bash
# Runs only against a newly named, disposable Compose project. Requires Docker,
# Compose v2, curl, and Node 24. Never uses the repository's root .env file.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
docker compose version >/dev/null
node --version >/dev/null
curl --version >/dev/null

smoke_project="mora-smoke-$(date +%s)-$$"
smoke_port=${SMOKE_HTTP_PORT:-18080}
case "$smoke_port" in
  ''|*[!0-9]*) echo 'SMOKE_HTTP_PORT must be a port number.' >&2; exit 1 ;;
esac
if (( smoke_port < 1024 || smoke_port > 65535 )); then
  echo 'SMOKE_HTTP_PORT must be between 1024 and 65535.' >&2
  exit 1
fi
smoke_dir=$(mktemp -d)

# Process variables override interpolation values from all user configuration.
export APP_KEY="base64:$(node -e 'process.stdout.write(require("node:crypto").randomBytes(32).toString("base64"))')"
export DB_PASSWORD="$(node -e 'process.stdout.write(require("node:crypto").randomBytes(32).toString("hex"))')"
export DB_DATABASE=mora_container_test DB_USERNAME=mora
export HTTP_PORT="$smoke_port" APP_URL="http://localhost:$smoke_port"
export NEXT_PUBLIC_SITE_URL="$APP_URL" SANCTUM_STATEFUL_DOMAINS="localhost:$smoke_port"
export SESSION_SECURE_COOKIE=false MAIL_MAILER=array NEXT_PUBLIC_STORE_LIVE=false
export NEXT_PUBLIC_SUPPORT_EMAIL= NEXT_PUBLIC_MERCHANT_NAME= NEXT_PUBLIC_MERCHANT_ADDRESS=
export STORE_SHIPPING_CENTS=390 STORE_FREE_SHIPPING_THRESHOLD_CENTS=7500
touch "$smoke_dir/empty.env"

compose() {
  docker compose --env-file "$smoke_dir/empty.env" --project-name "$smoke_project" -f compose.yaml "$@"
}

cleanup() {
  smoke_result=$?
  trap - EXIT
  if (( smoke_result != 0 )); then
    compose ps --all || true
    compose logs --no-color --tail=100 || true
  fi
  # These volumes belong only to the unique project created by this invocation.
  if ! compose down --volumes --remove-orphans; then
    echo "Cleanup failed; inspect Compose project $smoke_project." >&2
    smoke_result=1
  fi
  rm -f -- "$smoke_dir/empty.env" "$smoke_dir/upload.png" "$smoke_dir/upload-after.png"
  rmdir -- "$smoke_dir"
  exit "$smoke_result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

compose config --quiet
compose build
compose up -d --wait --wait-timeout 120 postgres
compose run --rm backend php artisan migrate --force --seed
compose up -d --wait --wait-timeout 180
node scripts/verify-http.mjs "$APP_URL"

# Write through the unprivileged application user, read through Nginx, and
# ensure disallowed upload extensions cannot be served or executed.
compose exec -T backend php -r 'file_put_contents("storage/app/public/smoke.png", base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=")); file_put_contents("storage/app/public/smoke.php", "<?php echo 123;");'
curl --fail --silent --show-error "$APP_URL/storage/smoke.png" -o "$smoke_dir/upload.png"
test "$(curl --silent --show-error -o /dev/null -w '%{http_code}' "$APP_URL/storage/smoke.php")" = 404
compose up -d --force-recreate --wait --wait-timeout 120 backend
# Nginx resolves the FastCGI service at startup; refresh it after replacement.
compose up -d --force-recreate --wait --wait-timeout 180 nginx
curl --fail --silent --show-error "$APP_URL/storage/smoke.png" -o "$smoke_dir/upload-after.png"
cmp "$smoke_dir/upload.png" "$smoke_dir/upload-after.png"

# Verify the real HTTP failure path, not only a mocked database exception.
compose stop postgres
node scripts/verify-http.mjs "$APP_URL" --database-unavailable
compose up -d --wait --wait-timeout 120 postgres
node scripts/verify-http.mjs "$APP_URL"
echo 'Container smoke checks passed (routing, assets, CSRF, sessions, uploads, database recovery).'
