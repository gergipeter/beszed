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
COPY scripts ./scripts
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

FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev libxml2-dev libpq-dev lame \
    && docker-php-ext-install dom mbstring pdo_sqlite pdo_mysql xml xmlwriter \
    && rm -rf /var/lib/apt/lists/*
COPY --from=piper /opt/piper /opt/piper
WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=frontend /app/public/build ./public/build
ENV APP_ENV=local APP_DEBUG=true APP_URL=http://localhost:8000 \
    LOG_CHANNEL=stderr DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/app/database.sqlite \
    SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync \
    FILESYSTEM_DISK=local TTS_DRIVER=piper
EXPOSE 8000
CMD ["sh", "docker/entrypoint.sh"]
