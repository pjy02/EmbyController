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

# 检测 .env 是否存在
if [ -f .env ]; then
  echo "检测到本地已存在 .env 文件，跳过下载。"
else
  echo "下载 .env 模板文件..."
  curl -o .env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
fi

# 下载 docker-compose.yml（始终覆盖）
curl -o docker-compose.yml https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/docker-compose.yml

# 是否进行自动配置
read -p "是否进入自动配置 .env 环境变量？(y/n): " auto_config_choice
if [ "$auto_config_choice" = "y" ]; then
  echo "开始配置 .env..."

  VARS_TO_CONFIGURE=(
    APP_DEBUG
    APP_HOST
    CRONTAB_KEY
    DB_TYPE
    DB_HOST
    DB_NAME
    DB_USER
    DB_PASS
    DB_PORT
    CACHE_TYPE
    EMBY_URLBASE
    EMBY_APIKEY
    EMBY_ADMINUSERID
    EMBY_TEMPLATEUSERID
    EMBY_LINE_LIST_0_NAME
    EMBY_LINE_LIST_0_URL
  )

  > .env
  for var in "${VARS_TO_CONFIGURE[@]}"; do
    read -p "请输入 $var 的值: " value
    echo "$var=$value" >> .env
  done

  echo ".env 配置完成 ✅"
else
  echo "跳过自动配置，继续使用当前 .env。"
fi

# 启动方式选择
while true; do
  echo "请选择创建容器的方法:"
  echo "1) Docker"
  echo "2) Docker Compose"
  read -p "请输入你的选择 (1 或 2): " choice

  if [ "$choice" -eq 1 ]; then
    docker run -d -p 8018:8018 --name emby-controller --env-file .env -v $(pwd)/.env:/app/.env ranjie/emby-controller:latest
    break
  elif [ "$choice" -eq 2 ]; then
    docker-compose up -d
    break
  else
    echo "无效的选择，请重新输入。"
  fi
done

echo "完成！如有需要可手动编辑 .env 并重启容器。"
