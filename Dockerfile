FROM php:7.1
MAINTAINER Martin Halamicek <martin@keboola.com>
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
  && apt-get install unzip git -y

RUN git clone --recursive --depth=1 https://github.com/kjdev/php-ext-brotli.git \
    && cd php-ext-brotli \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && cd - \
    && echo "extension=brotli.so" > /usr/local/etc/php/conf.d/90-brotli.ini


RUN echo "memory_limit = -1" >> /usr/local/etc/php/php.ini

RUN cd \
  && curl -sS https://getcomposer.org/installer | php \
  && ln -s /root/composer.phar /usr/local/bin/composer

WORKDIR /code
ADD ./ /code

RUN composer install \
    --prefer-dist \
    --no-interaction
