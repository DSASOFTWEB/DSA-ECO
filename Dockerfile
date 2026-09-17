# Build multi-stage:
#   1) "assets"  — compila o Tailwind/Vite (precisa de Node, não de PHP)
#   2) "app"     — imagem final PHP + Apache que serve o Laravel, já com o
#                  resultado do build de assets copiado para dentro
#
# Por que "composer install" SEM --no-dev? Porque o seeder de demonstração
# (DemoDataSeeder / database/factories) usa o pacote fakerphp/faker, que no
# Laravel vem em require-dev. Este Dockerfile é para o fluxo "docker compose
# up -d sobe tudo, inclusive dado de exemplo" — ou seja, um ambiente de
# desenvolvimento/homologação local, não uma imagem de produção enxuta. Para
# uma imagem de produção de verdade, o certo é *não* rodar seeders de demo e
# usar --no-dev; ver o comentário equivalente no docker-compose.yml.
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build
FROM php:8.3-apache AS app
# Extensões de sistema necessárias pelas extensões PHP abaixo e pelos pacotes
# do projeto (endroid/qr-code usa gd, maatwebsite/excel usa zip/mbstring/xml,
# barryvdh/laravel-dompdf usa gd/mbstring).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath gd zip exif pcntl soap \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
# Copia o projeto inteiro (respeitando .dockerignore) e instala as
# dependências PHP — com dev, pelo motivo explicado no topo do arquivo.
COPY . .
RUN composer install --optimize-autoloader --no-interaction --no-progress
# Assets já compilados pelo estágio "assets" (Tailwind/Vite)
COPY --from=assets /app/public/build ./public/build
# Apache: DocumentRoot para /public (raiz do Laravel) + AllowOverride para o
# .htaccess de rotas do Laravel funcionar
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
# Garante que as pastas de runtime existem (Git não versiona pasta vazia) e
# ajusta permissão — evita "View path not found" no view:cache/config:cache.
RUN mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=6 \
    CMD curl -f http://localhost/login || exit 1
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]