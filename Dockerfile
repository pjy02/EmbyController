FROM php:8.2-fpm

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件到工作目录
COPY . /var/www/html

# 安装项目依赖
RUN apt-get update && apt-get install -y \
    git curl wget zip unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo pdo_mysql

# 安装composer \
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 安装项目依赖
RUN composer install

# 设置权限
RUN chown -R www-data:www-data /var/www/html

# 复制nginx配置文件
COPY ./nginx.conf /etc/nginx/sites-available/default

# 运行
CMD ["nginx", "-g", "daemon off;"]