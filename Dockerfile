FROM composer:2 AS builder

WORKDIR /usr/src/uopf
COPY composer.json composer.lock .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
COPY . .

FROM php:8.4-fpm

RUN mv "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini" && \
    echo 'variables_order = "EGPCS"' >> "${PHP_INI_DIR}/php.ini"

RUN apt-get update && \
    apt-get install -y mariadb-client && \
    apt-get clean -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-install mysqli pdo pdo_mysql

RUN pecl install redis && \
    docker-php-ext-enable redis

RUN apt-get update && \
    apt-get install -y libmagickwand-dev && \
    apt-get clean -y && \
    rm -rf /var/lib/apt/lists/* && \
    pecl install imagick && \
    docker-php-ext-enable imagick

COPY --from=builder --chown=www-data:www-data /usr/src/uopf /usr/src/uopf

VOLUME /var/lib/uopf/images
WORKDIR /usr/src/uopf
