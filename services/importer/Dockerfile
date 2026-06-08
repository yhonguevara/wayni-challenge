# Stage 1: Composer dependencies
FROM composer:2 AS composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Stage 2: Application image
FROM php:8.5-cli-alpine

RUN apk add --no-cache \
        git \
        curl \
        libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath pcntl \
    && rm -rf /tmp/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 -S app \
    && adduser -u 1000 -S app -G app

WORKDIR /app

COPY --from=composer --chown=app:app /app/vendor ./vendor
COPY --chown=app:app . .

RUN composer dump-autoload --optimize \
    && chown -R app:app storage bootstrap/cache

USER app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
