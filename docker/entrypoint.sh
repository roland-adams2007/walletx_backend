#!/bin/bash
set -e

cd /var/www/html

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if [ ! -L public/storage ]; then
    php artisan storage:link || true
fi

php artisan migrate --force

exec "$@"