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
COPY scripts ./scripts
# scripts/copy-emoji.mjs ships only the emoji these use, so it reads the content and the code that builds rounds
COPY app ./app
COPY config ./config
COPY database ./database
COPY vite.config.js ./
RUN npm run build

# Piper: free neural text-to-speech with Hungarian voices (TTS_DRIVER=piper).
FROM debian:bookworm-slim AS piper
ARG TARGETARCH
ARG PIPER_VERSION=2023.11.14-2
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && arch="$([ "$TARGETARCH" = "arm64" ] && echo aarch64 || echo x86_64)" \
    && curl -fsSL "https://github.com/rhasspy/piper/releases/download/${PIPER_VERSION}/piper_linux_${arch}.tar.gz" | tar -xz -C /opt \
    && mkdir -p /opt/piper/voices \
    && for v in anna imre; do for f in onnx onnx.json; do \
         curl -fsSL -o "/opt/piper/voices/hu_HU-$v-medium.$f" \
           "https://huggingface.co/rhasspy/piper-voices/resolve/main/hu/hu_HU/$v/medium/hu_HU-$v-medium.$f"; \
       done; done

FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions pdo_sqlite opcache intl zip \
    && apt-get update && apt-get install -y --no-install-recommends lame && rm -rf /var/lib/apt/lists/*
COPY --from=piper /opt/piper /opt/piper
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
