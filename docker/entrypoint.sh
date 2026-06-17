#!/bin/sh
set -e

# In local dev the project is bind-mounted over /var/www/html, which shadows the
# vendor/ that was baked into the image. When OE_AUTO_COMPOSER=1 (set only on the
# "app" service) install dependencies on first boot so `docker compose up` works
# with no extra steps. Other services wait for "app" to become healthy, which only
# happens once vendor/autoload.php exists — so this never races.
if [ "${OE_AUTO_COMPOSER:-0}" = "1" ] && [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ not found — running composer install (first boot)..."
    composer install --no-interaction --no-progress --prefer-dist
fi

# php-fpm runs as www-data; make sure it can write storage + cache.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
