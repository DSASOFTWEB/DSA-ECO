#!/bin/sh
set -e
# Entrypoint de PRODUÇÃO.
# - Espera o MySQL
# - Roda migrate --force
# - NÃO roda db:seed (sem dados de demonstração / senhas padrão)
# - Cacheia config/rotas/views
# Só o container "app" (apache2-foreground) migra; queue/scheduler só esperam o DB.

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "[entrypoint.prod] Aguardando o MySQL (${DB_HOST}:${DB_PORT})..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 2
done
echo "[entrypoint.prod] MySQL disponível."

if [ "$1" = "apache2-foreground" ]; then
    php artisan package:discover --ansi || true

    echo "[entrypoint.prod] Rodando migrations..."
    php artisan migrate --force

    php artisan storage:link --force

    echo "[entrypoint.prod] Cacheando config/rotas/views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache || true
    php artisan permission:cache-reset || true

    chown -R www-data:www-data storage bootstrap/cache
fi

exec "$@"
