#!/bin/bash

# 确保目录存在并设置正确权限
mkdir -p /var/www/html/runtime
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/runtime

# 启动PHP-FPM
php-fpm -D

# 启动GatewayWorker
php /var/www/html/server.php start -d

# 启动Nginx
nginx -g "daemon off;" 