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

# 确保当前目录是 EmbyController
if [ "$(basename "$PWD")" != "EmbyController" ]; then
  echo "请先执行以下命令来运行脚本："
  echo "mkdir -p EmbyController && cd EmbyController && wget https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/quickstart.sh && chmod +x quickstart.sh && ./quickstart.sh"
  exit 1
fi

# 检查 Docker 是否安装
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

# 检查 Docker Compose 是否安装
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

# 下载 .env（如果本地不存在）
if [ ! -f .env ]; then
  echo ".env 文件不存在，正在下载 example.env 并重命名为 .env..."
  curl -fsSL https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env -o .env
else
  echo "已检测到本地 .env 文件，跳过下载。"
fi

# 下载 docker-compose.yml（始终更新）
curl -fsSL https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/docker-compose.yml -o docker-compose.yml

# 是否进行自动配置
read -p "是否进行 .env 自动配置？(y/n): " auto_config_choice
if [ "$auto_config_choice" = "y" ]; then
  echo "开始进行交互式配置 .env ..."

  # 创建临时文件保存新配置
  tmp_env=".env.tmp"
  > "$tmp_env"

  while IFS= read -r line || [ -n "$line" ]; do
    # 如果是注释或空行，直接保留
    if [[ "$line" =~ ^[[:space:]]*#.*$ || "$line" =~ ^[[:space:]]*$ ]]; then
      echo "$line" >> "$tmp_env"
      continue
    fi

    # 提取 KEY 和默认值
    key=$(echo "$line" | cut -d '=' -f1 | xargs)
    default_val=$(echo "$line" | cut -d '=' -f2- | xargs)

    echo -e "\n$key（默认值: $default_val）"
    read -p "请输入新值（直接回车使用默认值）: " input_val

    if [ -z "$input_val" ]; then
      echo "$key = $default_val" >> "$tmp_env"
    else
      echo "$key = $input_val" >> "$tmp_env"
    fi
  done < .env

  mv "$tmp_env" .env
  echo -e "\n.env 配置已更新。"
fi

# 容器创建方式选择
while true; do
  echo "请选择创建容器的方法:"
  echo "1) Docker"
  echo "2) Docker Compose"
  read -p "请输入你的选择 (1 或 2): " choice

  if [ "$choice" = "1" ]; then
    docker run -d -p 8018:8018 --name emby-controller --env-file .env -v "$(pwd)/.env:/app/.env" ranjie/emby-controller:latest
    break
  elif [ "$choice" = "2" ]; then
    docker-compose up -d
    break
  else
    echo "无效的选择。请重新输入。"
  fi
done

echo -e "\n✅ 操作完成，请根据需要修改 .env 文件后重启容器。"
