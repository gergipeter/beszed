# Build stage
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# PHP stage
FROM php:8.2-fpm-alpine

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
RUN chmod -R 755 storage bootstrap/cache

# Create nginx config
RUN mkdir -p /etc/nginx/conf.d
COPY <<'NGINXEOF' /etc/nginx/conf.d/default.conf
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
