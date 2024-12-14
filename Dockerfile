# 使用PHP 8.2 FPM基础镜像
FROM php:8.2-fpm

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

# 安装PHP扩展
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    gd

# 配置PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/memory_limit = 128M/memory_limit = 256M/g' "$PHP_INI_DIR/php.ini"

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 复制项目文件
COPY . /var/www/html/

# 在复制项目文件后添加
RUN mkdir -p /var/www/html/runtime \
    && chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# 安装项目依赖
RUN composer install --no-dev --optimize-autoloader

# 安装Nginx
RUN apt-get install -y nginx

# 删除默认的nginx配置
RUN rm -f /etc/nginx/sites-enabled/default

# 复制Nginx配置文件
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# 暴露端口
EXPOSE 8018 2347 2348

# 启动脚本
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

CMD ["/start.sh"] 