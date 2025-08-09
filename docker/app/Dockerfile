FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
RUN npm run build

FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM dunglas/frankenphp:1.13-php8.4 AS app

RUN install-php-extensions pdo_pgsql redis intl zip gd bcmath pcntl opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/app/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/app/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --chmod=755 docker/app/entrypoint.sh /usr/local/bin/app-entrypoint

RUN php artisan package:discover --ansi \
    && useradd --create-home --uid 10001 app \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs /data/caddy /config/caddy \
    && chown -R app:app storage bootstrap/cache /data /config

USER app
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
