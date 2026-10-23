FROM php:8.4-cli-alpine

COPY --from=mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql redis intl zip gd bcmath pcntl

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
