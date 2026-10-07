#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Generate it with php artisan key:generate --show.' >&2
    exit 1
fi

# Migrations are an explicit release step, never a side effect of a replica starting.
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec "$@"

