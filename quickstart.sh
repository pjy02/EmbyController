#!/bin/bash

# Function to check if a command exists
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

# Function to install Docker
install_docker() {
  echo "正在安装 Docker..."
  curl -fsSL https://get.docker.com -o get-docker.sh
  sh get-docker.sh
  rm get-docker.sh
  echo "Docker 安装完成。"
}

# Function to install Docker Compose
install_docker_compose() {
  echo "正在安装 Docker Compose..."
  sudo curl -L "https://github.com/docker/compose/releases/download/$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep -oP '"tag_name": "\K(.*)(?=")')/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
  sudo chmod +x /usr/local/bin/docker-compose
  echo "Docker Compose 安装完成。"
}

# 检查 Docker
if ! command_exists docker; then
  echo "Docker 未安装。"
  read -p "是否安装 Docker? (y/n): " install_docker_choice
  if [ "$install_docker_choice" = "y" ]; then
    install_docker
  else
    echo "Docker 是必须的。退出。"
    exit 1
  fi
fi

# 检查 Docker Compose
if ! command_exists docker-compose; then
  echo "Docker Compose 未安装。"
  read -p "是否安装 Docker Compose? (y/n): " install_docker_compose_choice
  if [ "$install_docker_compose_choice" = "y" ]; then
    install_docker_compose
  else
    echo "Docker Compose 是必须的。退出。"
    exit 1
  fi
fi

# 创建目录并进入
mkdir -p EmbyController
cd EmbyController

# 检查是否存在本地 .env 文件
if [ -f ".env" ]; then
  echo "检测到本地 .env 文件已存在。"
  read -p "是否使用现有的 .env 文件？(y/n): " use_existing_env
  if [ "$use_existing_env" != "y" ]; then
    echo "下载新的 .env 文件..."
    curl -o .env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
  else
    echo "使用现有的 .env 文件。"
  fi
else
  echo "下载 .env 文件..."
  curl -o .env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
fi

# 下载 docker-compose.yml
echo "下载 docker-compose.yml..."
curl -o docker-compose.yml https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/docker-compose.yml

# 询问是否自动配置
read -p "是否进入自动配置 .env 环境变量？(y/n): " auto_config_choice

if [ "$auto_config_choice" = "y" ]; then
  echo "开始配置 .env..."
  
  # 定义配置项的顺序数组
  VARS_ORDER=(
    "APP_DEBUG"
    "APP_HOST"
    "IS_DOCKER"
    "CRONTAB_KEY"
    "DB_TYPE"
    "DB_HOST"
    "DB_NAME"
    "DB_USER"
    "DB_PASS"
    "DB_PORT"
    "DB_CHARSET"
    "CACHE_TYPE"
    "REDIS_HOST"
    "REDIS_PORT"
    "REDIS_PASS"
    "REDIS_DB"
    "SOCKS5_ENABLE"
    "SOCKS5_HOST"
    "SOCKS5_PORT"
    "SOCKS5_USERNAME"
    "SOCKS5_PASSWORD"
    "MAIL_TYPE"
    "MAIL_HOST"
    "MAIL_PORT"
    "MAIL_USER"
    "MAIL_PASS"
    "MAIL_FROM_NAME"
    "MAIL_FROM_EMAIL"
    "MAIL_USE_SOCKS5"
    "EMBY_URLBASE"
    "EMBY_APIKEY"
    "EMBY_ADMINUSERID"
    "EMBY_TEMPLATEUSERID"
    "EMBY_LINE_LIST_0_NAME"
    "EMBY_LINE_LIST_0_URL"
    "EMBY_LINE_LIST_1_NAME"
    "EMBY_LINE_LIST_1_URL"
    "PAY_URL"
    "PAY_MCHID"
    "PAY_KEY"
    "AVAILABLE_PAYMENT_0"
    "TG_BOT_TOKEN"
    "TG_BOT_USERNAME"
    "TG_BOT_ADMIN_ID"
    "TG_BOT_GROUP_ID"
    "TG_BOT_GROUP_NOTIFY"
    "XFYUNLIST_28654731426_APPID"
    "XFYUNLIST_28654731426_APIKEY"
    "XFYUNLIST_28654731426_APISECRET"
    "CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SITEKEY"
    "CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SECRET"
    "CLOUDFLARE_TURNSTILE_INVISIBLE_SITEKEY"
    "CLOUDFLARE_TURNSTILE_INVISIBLE_SECRET"
    "TENCENT_MAP_KEY"
    "TENCENT_MAP_SK"
    "GEMINI_API_KEY"
    "DEFAULT_LANG"
  )

  # 定义配置项说明
  declare -A VARS_DESC=(
    ["APP_DEBUG"]="应用调试模式 (true/false)"
    ["APP_HOST"]="应用主机地址"
    ["IS_DOCKER"]="是否在Docker中运行 (true/false)"
    ["CRONTAB_KEY"]="定时任务密钥"
    ["DB_TYPE"]="数据库类型"
    ["DB_HOST"]="数据库主机"
    ["DB_NAME"]="数据库名称"
    ["DB_USER"]="数据库用户名"
    ["DB_PASS"]="数据库密码"
    ["DB_PORT"]="数据库端口"
    ["DB_CHARSET"]="数据库字符集"
    ["CACHE_TYPE"]="缓存类型 (file/redis)"
    ["REDIS_HOST"]="Redis主机"
    ["REDIS_PORT"]="Redis端口"
    ["REDIS_PASS"]="Redis密码"
    ["REDIS_DB"]="Redis数据库编号"
    ["SOCKS5_ENABLE"]="是否启用Socks5代理 (true/false)"
    ["SOCKS5_HOST"]="Socks5代理主机"
    ["SOCKS5_PORT"]="Socks5代理端口"
    ["SOCKS5_USERNAME"]="Socks5代理用户名"
    ["SOCKS5_PASSWORD"]="Socks5代理密码"
    ["MAIL_TYPE"]="邮件类型 (smtp/smtps)"
    ["MAIL_HOST"]="邮件服务器主机"
    ["MAIL_PORT"]="邮件服务器端口"
    ["MAIL_USER"]="邮件用户名"
    ["MAIL_PASS"]="邮件密码"
    ["MAIL_FROM_NAME"]="发件人名称"
    ["MAIL_FROM_EMAIL"]="发件人邮箱"
    ["MAIL_USE_SOCKS5"]="邮件是否使用Socks5代理 (true/false)"
    ["EMBY_URLBASE"]="Emby内网API地址"
    ["EMBY_APIKEY"]="Emby API密钥"
    ["EMBY_ADMINUSERID"]="Emby管理员用户ID"
    ["EMBY_TEMPLATEUSERID"]="Emby模板用户ID"
    ["EMBY_LINE_LIST_0_NAME"]="线路0名称"
    ["EMBY_LINE_LIST_0_URL"]="线路0地址"
    ["EMBY_LINE_LIST_1_NAME"]="线路1名称"
    ["EMBY_LINE_LIST_1_URL"]="线路1地址"
    ["PAY_URL"]="支付接口地址"
    ["PAY_MCHID"]="支付商户ID"
    ["PAY_KEY"]="支付密钥"
    ["AVAILABLE_PAYMENT_0"]="可用支付方式0"
    ["TG_BOT_TOKEN"]="Telegram机器人Token"
    ["TG_BOT_USERNAME"]="Telegram机器人用户名"
    ["TG_BOT_ADMIN_ID"]="Telegram管理员ID"
    ["TG_BOT_GROUP_ID"]="Telegram群组ID"
    ["TG_BOT_GROUP_NOTIFY"]="Telegram群组通知 (true/false)"
    ["XFYUNLIST_28654731426_APPID"]="讯飞AI应用ID"
    ["XFYUNLIST_28654731426_APIKEY"]="讯飞AI密钥"
    ["XFYUNLIST_28654731426_APISECRET"]="讯飞AI密钥"
    ["CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SITEKEY"]="CloudFlare人机验证站点密钥"
    ["CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SECRET"]="CloudFlare人机验证密钥"
    ["CLOUDFLARE_TURNSTILE_INVISIBLE_SITEKEY"]="CloudFlare隐形验证站点密钥"
    ["CLOUDFLARE_TURNSTILE_INVISIBLE_SECRET"]="CloudFlare隐形验证密钥"
    ["TENCENT_MAP_KEY"]="腾讯地图API密钥"
    ["TENCENT_MAP_SK"]="腾讯地图SK密钥"
    ["GEMINI_API_KEY"]="Gemini API密钥"
    ["DEFAULT_LANG"]="默认语言"
  )

  # 备份原有的 .env 文件
  cp .env .env.backup
  
  # 清空 .env 文件
  > .env
  
  echo "提示：直接回车将跳过该配置项"
  echo "=================="
  
  # 按顺序逐个提示用户输入变量值
  for var in "${VARS_ORDER[@]}"; do
    echo -e "\n配置项: $var"
    echo "说明: ${VARS_DESC[$var]}"
    read -p "请输入值 (直接回车跳过): " value
    
    if [ -n "$value" ]; then
      echo "$var = $value" >> .env
    else
      echo "# $var = " >> .env
    fi
  done
  
  echo -e "\n.env 配置完成 ✅"
  echo "配置文件已保存，原文件备份为 .env.backup"
  
else
  echo "跳过自动配置，使用原始 .env 文件。"
fi

# 让用户选择启动方式
echo -e "\n请选择部署方式:"
while true; do
  echo "1) Docker 命令启动"
  echo "2) Docker Compose 启动"
  read -p "请输入你的选择 (1 或 2): " choice
  
  if [ "$choice" -eq 1 ]; then
    echo "使用 Docker 命令启动容器..."
    docker run -d -p 8018:8018 --name emby-controller --env-file .env -v $(pwd)/.env:/app/.env ranjie/emby-controller:latest
    if [ $? -eq 0 ]; then
      echo "容器启动成功！"
      echo "访问地址: http://localhost:8018"
    else
      echo "容器启动失败，请检查配置。"
    fi
    break
  elif [ "$choice" -eq 2 ]; then
    echo "使用 Docker Compose 启动..."
    docker-compose up -d
    if [ $? -eq 0 ]; then
      echo "服务启动成功！"
      echo "访问地址: http://localhost:8018"
    else
      echo "服务启动失败，请检查配置。"
    fi
    break
  else
    echo "无效的选择，请重新输入。"
  fi
done

echo -e "\n部署完成！"
echo "注意事项："
echo "1. 如需修改配置，请编辑 .env 文件"
echo "2. 修改配置后需要重启容器才能生效"
echo "3. 重启命令: docker restart emby-controller 或 docker-compose restart"
echo "4. 查看日志: docker logs emby-controller 或 docker-compose logs"
