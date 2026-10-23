FROM php:8.4-cli-alpine

COPY --from=mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql redis intl zip gd bcmath pcntl

# Product images may be up to 5 MB (validated in the app); PHP's default limit is 2 MB.
RUN printf "upload_max_filesize=6M\npost_max_size=8M\nmemory_limit=256M\n" > "$PHP_INI_DIR/conf.d/uploads.ini"

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
