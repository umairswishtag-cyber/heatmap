FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev libzip-dev oniguruma-dev curl-dev linux-headers \
    && docker-php-ext-install pdo_pgsql pcntl sockets mbstring
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist
COPY . .
RUN composer dump-autoload --optimize && chmod -R 775 storage bootstrap/cache
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
