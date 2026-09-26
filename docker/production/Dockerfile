# Production image: FrankenPHP (Caddy + PHP) serves Laravel with automatic HTTPS
# (Let's Encrypt) for $SERVER_NAME. Used by compose.prod.yaml; the dev image stays ./Dockerfile.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions pdo_sqlite opcache intl zip
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=frontend /app/public/build /app/public/build
COPY docker/production/Caddyfile /etc/caddy/Caddyfile
COPY docker/production/php.ini $PHP_INI_DIR/conf.d/zz-beszed.ini
COPY docker/production/entrypoint.sh /usr/local/bin/beszed-entrypoint
RUN chmod +x /usr/local/bin/beszed-entrypoint \
    && mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
ENTRYPOINT ["beszed-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
