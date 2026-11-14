FROM php:8.5-cli AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && printf 'upload_max_filesize=50M\npost_max_size=60M\n' > /usr/local/etc/php/conf.d/uploads.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM php-base AS php-app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=php-app /var/www/html/vendor ./vendor
RUN npm run build

FROM php-app AS app

COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM php-app AS test-runner

RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts \
   && composer dump-autoload --no-interaction

COPY --from=frontend /app/public/build ./public/build
