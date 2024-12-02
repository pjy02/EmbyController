# 使用官方 PHP 8 FPM 镜像作为基础镜像
FROM php:8.0-fpm

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件到工作目录
COPY . /var/www/html

# 安装项目依赖
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo pdo_mysql

# 复制 Nginx 配置文件
COPY ./nginx.conf /etc/nginx/nginx.conf

# 启动 Nginx 和 PHP-FPM
CMD service nginx start && php-fpm