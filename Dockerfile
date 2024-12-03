FROM php:8.2.0-apache
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y libzip-dev libxml2-dev libgmp-dev libsodium-dev libpng-dev git curl wget zip unzip vim --allow-unauthenticated

RUN docker-php-ext-install gd pdo pdo_mysql
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
COPY . /app

RUN composer install

EXPOSE 8000
CMD php think run --host=0.0.0.0 --port=8000