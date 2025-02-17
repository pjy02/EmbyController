#!/bin/sh
set -e

echo "[$(date)] Starting initialization..."

# 确保目录权限正确
echo "Setting up permissions..."
mkdir -p /app/runtime
chown -R www-data:www-data /app
chmod -R 755 /app/runtime

# 运行数据库迁移
# 检查是否存在迁移文件
if [ -d "/app/database/migrations" ] && [ "$(ls -A /app/database/migrations)" ]; then
    echo "Running database migrations..."
    php think migrate:run
else
    echo "No migrations found, skipping migration step"
fi

# 启动PHP-FPM
echo "Starting PHP-FPM..."
php-fpm -D

# 启动GatewayWorker
echo "Starting GatewayWorker..."
php /app/server.php start -d

# 启动Nginx
echo "Starting Nginx..."
nginx -g "daemon off;"