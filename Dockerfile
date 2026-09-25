# Development image: FrankenPHP serves the API, the same image runs the worker.
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_pgsql intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV SERVER_NAME=":8000" \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize && composer run-script auto-scripts

EXPOSE 8000
