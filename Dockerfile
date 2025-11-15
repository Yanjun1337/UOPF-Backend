FROM composer:2 AS builder

WORKDIR /usr/src/uopf
COPY composer.json composer.lock .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
COPY . .

FROM php:8.4-fpm-alpine

RUN mv "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini" && \
    echo 'variables_order = "EGPCS"' >> "${PHP_INI_DIR}/php.ini"

RUN apk add --no-cache pcre-dev $PHPIZE_DEPS && \
    pecl install redis && \
    docker-php-ext-enable redis.so

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS imagemagick-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps

COPY --from=builder --chown=www-data:www-data /usr/src/uopf /usr/src/uopf

VOLUME /var/lib/uopf/images
WORKDIR /usr/src/uopf
