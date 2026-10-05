# Build stage. node:22, not 20: scripts/copy-emoji.mjs's Intl.Segmenter call (grapheme-splitting
# every scanned file to find the emoji in use) OOMs inside Node 20's bundled ICU before finishing a
# single file, confirmed by the crash trace (icu_78 / JSSegmentIterator); the exact same code runs
# fine on Node 22+. Not a memory-size or Alpine/musl issue — a Node 20 ICU bug, worked around by
# moving past it.
FROM node:22-slim AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# PHP stage. 8.4, not 8.2: composer.lock already resolved symfony/clock, css-selector, event-dispatcher,
# string, translation and nesbot/carbon against PHP >=8.4.1 — composer install fails on 8.2 as a result.
FROM php:8.4-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    curl \
    git \
    zip \
    unzip \
    libpq-dev \
    sqlite-dev \
    oniguruma-dev \
    mysql-client \
    redis \
    supervisor \
    nginx

# Install PHP extensions (pdo, ctype, json are already built into this image)
RUN docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite bcmath mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy PHP code
COPY . .

# Copy built assets from node builder
COPY --from=node-builder /app/public/build ./public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
# www-data (php-fpm's worker user) needs to write here (views cache, logs, sessions) — root:root at
# 755 blocks that, which the entrypoint's own first page render then 500s on (tempnam() falling back
# to the system temp dir, then failing the same way rendering its own error page).
RUN chown -R www-data:www-data storage bootstrap/cache && chmod -R 755 storage bootstrap/cache

# Create nginx config. This image's own nginx.conf includes conf.d/*.conf at the ROOT context
# (before the http {} block) and http.d/*.conf inside it (Alpine's nginx package convention,
# unlike Debian's) — a server {} block belongs in http.d, or nginx refuses it on boot.
RUN mkdir -p /etc/nginx/http.d
COPY <<'NGINXEOF' /etc/nginx/http.d/default.conf
server {
    listen 8000;
    server_name _;
    root /app/public;
    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINXEOF

# Create supervisor config
RUN mkdir -p /etc/supervisor/conf.d
COPY <<'SUPERVISOREOF' /etc/supervisor/conf.d/app.conf
[supervisord]
nodaemon=true

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true

[program:queue-worker]
command=php /app/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=1
SUPERVISOREOF

# Render (and any other host without a DB/Redis service attached) needs the SQLite file
# created and migrated before supervisor starts; harmless no-op when a real DB is configured.
COPY docker/render/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

CMD ["/usr/local/bin/entrypoint.sh"]
