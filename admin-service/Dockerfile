# syntax=docker/dockerfile:1

# FrankenPHP: a PHP application server that speaks HTTP itself, so Nginx simply
# proxies to it. It replaces `php artisan serve`, which is for development only.
FROM dunglas/frankenphp:1.12.7-php8.4-alpine AS base

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql redis intl zip gd bcmath pcntl opcache

# Product images may be up to 5 MB (validated in the app); PHP's default limit is 2 MB.
RUN printf "upload_max_filesize=6M\npost_max_size=8M\nmemory_limit=256M\n" > "$PHP_INI_DIR/conf.d/uploads.ini"

WORKDIR /var/www/html
EXPOSE 8000

# Development: docker compose mounts the source over /var/www/html. Without a
# worker script FrankenPHP runs each request fresh, so code changes apply at once.
FROM base AS dev
CMD ["frankenphp", "php-server", "--root", "public/", "--listen", ":8000"]

FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

# Tailwind also scans Laravel's pagination views in vendor/, so the build gets the PHP packages too.
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

# Production: code, production packages and built assets baked in, running as www-data.
FROM base AS prod
ENV APP_ENV=production \
    APP_DEBUG=false

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-production.ini "$PHP_INI_DIR/conf.d/zz-production.ini"
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy

USER www-data

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
  CMD wget -qO /dev/null http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "php-server", "--root", "public/", "--listen", ":8000"]
