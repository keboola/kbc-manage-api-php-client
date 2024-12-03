ARG PHP_VERSION=8.1
# the default env bellow is used when build pipeline sends "PHP_VERSION=" - the above default value is ignored in that case
FROM php:${PHP_VERSION:-8.1} as dev

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction --classmap-authoritative --no-scripts"

MAINTAINER Martin Halamicek <martin@keboola.com>
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
  && apt-get install unzip git -y \
  && rm -r /var/lib/apt/lists/*

RUN echo "memory_limit = -1" >> /usr/local/etc/php/php.ini

RUN cd \
  && curl -sS https://getcomposer.org/installer | php \
  && ln -s /root/composer.phar /usr/local/bin/composer

WORKDIR /code

# Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* ./
# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

COPY . .

RUN composer install $COMPOSER_FLAGS
