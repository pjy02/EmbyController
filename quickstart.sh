#!/bin/bash

# Function to check if a command exists
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

# Function to install Docker
install_docker() {
  echo "\n正在安装 Docker..."
  curl -fsSL https://get.docker.com -o get-docker.sh
  sh get-docker.sh
  rm get-docker.sh
  echo "Docker 安装完成。"
}

# Function to install Docker Compose
install_docker_compose() {
  echo "\n正在安装 Docker Compose..."
  sudo curl -L "https://github.com/docker/compose/releases/download/$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep -oP '"tag_name": \"\K(.*)(?=\")')/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
  sudo chmod +x /usr/local/bin/docker-compose
  echo "Docker Compose 安装完成。"
}

# Check if Docker is installed
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

# Check if Docker Compose is installed
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

# 创建目录
mkdir -p EmbyController
cd EmbyController

# 下载 docker-compose.yml 文件
curl -o docker-compose.yml https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/docker-compose.yml

# 下载 .env 文件（如不存在）
if [ ! -f .env ]; then
  echo "未检测到本地 .env，正在从远程下载 example.env..."
  curl -o example.env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
  cp example.env .env
else
  echo "检测到本地已有 .env 文件，跳过下载。"
fi

read -p "是否进入 .env 自动配置? (y/n): " config_choice
if [ "$config_choice" = "y" ]; then
  echo "开始自动配置 .env 文件..."
  temp_file=".env.tmp"
  > "$temp_file"

  IFS=''
  while read -r line; do
    if [[ "$line" =~ ^#.* ]]; then
      echo "$line" >> "$temp_file"
    elif [[ "$line" =~ ^[A-Za-z0-9_]+\ *=.* ]]; then
      key="$(echo "$line" | cut -d '=' -f1 | xargs)"
      val="$(echo "$line" | cut -d '=' -f2- | xargs)"
      echo -e "当前变量: $key\n当前值: $val"
      read -p "请输入新值 (直接回车表示保留当前值): " newval
      if [ -z "$newval" ]; then
        echo "$key = $val" >> "$temp_file"
      else
        echo "$key = $newval" >> "$temp_file"
      fi
    else
      echo "$line" >> "$temp_file"
    fi
  done < .env

  mv "$temp_file" .env
  echo ".env 配置完成。"
fi

# 选择容器部署方式
while true; do
  echo "\n请选择创建容器的方法:"
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

echo "\n部署完成，请根据需要再次修改 .env 并重启容器。"
