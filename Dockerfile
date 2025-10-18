FROM composer:2 AS builder

WORKDIR /usr/src/uopf
COPY composer.json composer.lock .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
COPY . .

FROM php:8.4-fpm-alpine

RUN mv "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"

COPY --from=builder --chown=www-data:www-data /usr/src/uopf /usr/src/uopf
WORKDIR /usr/src/uopf
