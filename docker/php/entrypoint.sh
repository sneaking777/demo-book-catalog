#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "[entrypoint] ожидаем MySQL по адресу ${DB_HOST}:${DB_PORT}..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 1
done

echo "[entrypoint] MySQL доступен"

mkdir -p runtime web/assets web/uploads/covers
chown -R www-data:www-data runtime web/assets web/uploads
chmod -R ug+rwX runtime web/assets web/uploads

if [ ! -d vendor ]; then
    echo "[entrypoint] устанавливаем зависимости composer..."
    runuser -u www-data -- composer install --no-interaction --prefer-dist --no-progress
fi

echo "[entrypoint] применяем миграции..."
runuser -u www-data -- php yii migrate --interactive=0

echo "[entrypoint] запускаем php-fpm"
exec "$@"
