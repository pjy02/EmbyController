#!/bin/bash

# Function to check if a command exists
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

# Function to install Docker
install_docker() {
  echo "\n\e[33m正在安装 Docker...\e[0m"
  curl -fsSL https://get.docker.com -o get-docker.sh
  sh get-docker.sh
  rm get-docker.sh
  echo -e "\e[32mDocker 安装完成。\e[0m"
}

# Function to install Docker Compose
install_docker_compose() {
  echo "\n\e[33m正在安装 Docker Compose...\e[0m"
  sudo curl -L "https://github.com/docker/compose/releases/download/$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep -oP '"tag_name": \"\K(.*)(?=\")')/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
  sudo chmod +x /usr/local/bin/docker-compose
  echo -e "\e[32mDocker Compose 安装完成。\e[0m"
}

# 检查 Docker
if ! command_exists docker; then
  echo -e "\n\e[31mDocker 未安装。\e[0m"
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
  echo -e "\n\e[31mDocker Compose 未安装。\e[0m"
  read -p "是否安装 Docker Compose? (y/n): " install_docker_compose_choice
  if [ "$install_docker_compose_choice" = "y" ]; then
    install_docker_compose
  else
    echo "Docker Compose 是必须的。退出。"
    exit 1
  fi
fi

# 创建目录
mkdir -p EmbyController
cd EmbyController || exit

# 下载 .env 和 docker-compose.yml
if [ -f .env ]; then
  echo ".env 文件已存在，跳过下载。"
else
  curl -o example.env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
  cp example.env .env
  echo "example.env 已下载并重命名为 .env"
fi

curl -o docker-compose.yml https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/docker-compose.yml

# 自动配置
read -p "是否进入自动配置 .env 环境变量？(y/n): " auto_config_choice
if [ "$auto_config_choice" = "y" ]; then
  echo "开始配置 .env..."

  VARS_TO_CONFIGURE=(
    APP_DEBUG APP_HOST IS_DOCKER CRONTAB_KEY
    DB_TYPE DB_HOST DB_NAME DB_USER DB_PASS DB_PORT DB_CHARSET
    CACHE_TYPE REDIS_HOST REDIS_PORT REDIS_PASS REDIS_DB
    SOCKS5_ENABLE SOCKS5_HOST SOCKS5_PORT SOCKS5_USERNAME SOCKS5_PASSWORD
    MAIL_TYPE MAIL_HOST MAIL_PORT MAIL_USER MAIL_PASS MAIL_FROM_NAME MAIL_FROM_EMAIL MAIL_USE_SOCKS5
    EMBY_URLBASE EMBY_APIKEY EMBY_ADMINUSERID EMBY_TEMPLATEUSERID
    EMBY_LINE_LIST_0_NAME EMBY_LINE_LIST_0_URL
    EMBY_LINE_LIST_1_NAME EMBY_LINE_LIST_1_URL
    PAY_URL PAY_MCHID PAY_KEY AVAILABLE_PAYMENT_0
    TG_BOT_TOKEN TG_BOT_USERNAME TG_BOT_ADMIN_ID TG_BOT_GROUP_ID TG_BOT_GROUP_NOTIFY
    XFYUNLIST_28654731426_APPID XFYUNLIST_28654731426_APIKEY XFYUNLIST_28654731426_APISECRET
    CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SITEKEY CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SECRET
    CLOUDFLARE_TURNSTILE_INVISIBLE_SITEKEY CLOUDFLARE_TURNSTILE_INVISIBLE_SECRET
    TENCENT_MAP_KEY TENCENT_MAP_SK GEMINI_API_KEY DEFAULT_LANG
  )

  > .env
  for var in "${VARS_TO_CONFIGURE[@]}"; do
    read -p "请输入 $var 的值: " value
    echo "$var=$value" >> .env
  done
  echo -e "\e[32m.env 配置完成。\e[0m"
fi

# 启动容器
while true; do
  echo "\n请选择创建容器的方法:"
  echo "1) Docker"
  echo "2) Docker Compose"
  read -p "请输入你的选择 (1 或 2): " choice

  if [ "$choice" = "1" ]; then
    docker run -d -p 8018:8018 --name emby-controller --env-file .env -v $(pwd)/.env:/app/.env ranjie/emby-controller:latest
    break
  elif [ "$choice" = "2" ]; then
    docker-compose up -d
    break
  else
    echo "无效的选择，请重新输入。"
  fi
done

echo -e "\n\e[32m部署完成，请根据需要编辑 .env 文件并重启容器。\e[0m"
