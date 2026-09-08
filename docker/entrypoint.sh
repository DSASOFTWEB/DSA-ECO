#!/bin/sh
set -e
# Este script roda em TODOS os containers baseados nesta imagem (app, queue,
# scheduler) — todos precisam esperar o MySQL responder antes de fazer
# qualquer coisa. Só quem sobe o Apache (o container "app", identificado pelo
# comando "apache2-foreground") é responsável por migrar/semear o banco —
# senão os 3 containers tentariam migrar ao mesmo tempo na primeira subida.

# Garante que as pastas de runtime existem e têm dono certo. Roda em TODOS
# os containers (não só o app) porque queue/scheduler também escrevem log e
# usam storage/framework/cache. Precisa rodar aqui (não só no Dockerfile)
# porque storage/app é volume nomeado e pode mascarar o que foi criado no
# build — então garantimos de novo aqui, toda subida.
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "[entrypoint] Aguardando o MySQL (${DB_HOST}:${DB_PORT})..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] MySQL disponível."
if [ "$1" = "apache2-foreground" ]; then
    echo "[entrypoint] Rodando migrations..."
    php artisan migrate --force
    # Semeia dados de demonstração só na primeira vez. O flag fica em
    # storage/app, que é um volume nomeado (ver docker-compose.yml) — então
    # sobrevive a "docker compose down" (sem -v) e a rebuilds da imagem.
    SEED_FLAG="/var/www/html/storage/app/.seeded"
    # Também re-semeia se o flag existir mas o banco estiver vazio (ex.: volume
    # db_data apagado com -v, ou banco recriado, enquanto app_storage manteve
    # o .seeded — senão o login falha com "Credenciais inválidas").
    USER_COUNT=$(php -r "try { \$p=new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}','${DB_USERNAME}','${DB_PASSWORD}'); echo (int)\$p->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch (Throwable \$e) { echo 0; }" 2>/dev/null || echo 0)
    if [ ! -f "$SEED_FLAG" ] || [ "$USER_COUNT" = "0" ]; then
        echo "[entrypoint] Rodando seeders de demonstração (users=${USER_COUNT})..."
        php artisan db:seed --force
        touch "$SEED_FLAG"
    else
        echo "[entrypoint] Seed já aplicado anteriormente, pulando db:seed."
    fi
    php artisan storage:link --force
    echo "[entrypoint] Cacheando config/rotas/views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    # Tudo acima roda como root (é assim que o container inicia). O Dockerfile
    # já tinha dado chown em storage/ e bootstrap/cache/ pra www-data na hora
    # do build, mas migrate/seed/*:cache acabaram de criar arquivos NOVOS
    # (storage/logs/laravel.log, bootstrap/cache/*.php, o flag .seeded) que
    # nascem donos de root. Sem este chown de novo aqui, o Apache — que
    # atende requisição de verdade como www-data, não como root — não
    # consegue escrever no log nem em storage/app (upload, QR Code) depois
    # que o container sobe. Isso só apareceria em uso real, não em nenhuma
    # validação estática, por isso o comentário longo.
    chown -R www-data:www-data storage bootstrap/cache
fi
exec "$@"