# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql intl bcmath zip gd pcntl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY backend/ ./
COPY docker/php.ini /usr/local/etc/php/conf.d/99-mora.ini
COPY docker/entrypoint.sh /usr/local/bin/mora-entrypoint
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && chmod +x /usr/local/bin/mora-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["mora-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.28-alpine AS nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx-routes.conf /etc/nginx/snippets/mora-routes.conf
COPY --from=app /var/www/html/public /var/www/html/public
EXPOSE 80
