FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-interaction

FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev libxml2-dev \
    && docker-php-ext-install dom mbstring pdo_sqlite xml xmlwriter \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=frontend /app/public/build ./public/build
ENV APP_ENV=local APP_DEBUG=true APP_URL=http://localhost:8000 \
    LOG_CHANNEL=stderr DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/app/database.sqlite \
    SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync \
    FILESYSTEM_DISK=local TTS_DRIVER=null
EXPOSE 8000
CMD ["sh", "docker/entrypoint.sh"]