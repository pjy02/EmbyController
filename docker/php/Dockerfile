FROM composer as composer

RUN composer config -g repo.packagist composer https://hk.hongfs.dev/composer/

FROM php:8.2-fpm as php

WORKDIR /web

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY $PROJECT_DIR/ .

RUN apt-get update && \
    apt-get install -y git zip libpng-dev libzip-dev && \
    rm -rf /var/lib/apt/lists/* && \
    ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    pecl install redis-5.3.7 && \
    docker-php-ext-install pdo pdo_mysql mysqli gd zip bcmath opcache && \
    docker-php-ext-enable mysqli gd redis

RUN echo "[opcache]\n\
opcache.enable=1\n\
opcache.enable_cli=1\n\
opcache.memory_consumption=256\n\
opcache.interned_strings_buffer=128\n\
opcache.max_accelerated_files=1000000\n\
opcache.max_wasted_percentage=10\n\
opcache.validate_timestamps=0\n\
opcache.enable_file_override=1" > /usr/local/etc/php/conf.d/opcache.ini

RUN composer install -vvv && \
    composer clear-cache

EXPOSE 9000

CMD ["php-fpm"]