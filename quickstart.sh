#!/bin/bash

# 日志函数
log_info() {
    echo "[INFO] $1"
}

log_warn() {
    echo -e "\033[33m[WARN] $1\033[0m" >&2
}

log_error() {
    echo -e "\033[31m[ERROR] $1\033[0m" >&2
}

# 错误处理
handle_error() {
    local exit_code=$?
    log_error "发生错误：$1"
    if [ -f ".env.backup" ]; then
        log_info "正在恢复备份..."
        mv .env.backup .env
    fi
    exit $exit_code
}

trap 'handle_error "意外退出"' ERR

# 检查命令是否存在
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# 安装 Docker
install_docker() {
    log_info "正在安装 Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    log_info "Docker 安装完成！"
}

# 安装 Docker Compose
install_docker_compose() {
    log_info "正在安装 Docker Compose..."
    sudo curl -L "https://github.com/docker/compose/releases/download/$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep -oP '"tag_name": "\K(.*)(?=")')/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
    log_info "Docker Compose 安装完成。"
}

# 安装 MySQL 服务器
install_mysql_server() {
    log_info "正在安装 MySQL 服务器..."
    if command_exists apt-get; then
        # Debian/Ubuntu 系统
        log_info "检测到 Debian/Ubuntu 系统"
        
        # 检查是否有旧的 MySQL 安装
        if dpkg -l | grep -q mysql-server; then
            log_info "检测到已安装的 MySQL，尝试修复..."
            sudo apt-get remove --purge -y mysql-server mysql-server-8.0 mysql-common
            sudo apt-get autoremove -y
            sudo apt-get autoclean
            sudo rm -rf /var/lib/mysql
            sudo rm -rf /etc/mysql
        fi
        
        # 更新软件包列表
        log_info "更新软件包列表..."
        sudo apt-get update
        
        # 预配置 root 密码（避免交互式提示）
        log_info "预配置 MySQL root 密码..."
        sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password password your_password'
        sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password your_password'
        
        # 安装 MySQL
        log_info "开始安装 MySQL..."
        if ! sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server; then
            log_error "MySQL 安装失败"
            log_info "尝试查看详细错误信息..."
            sudo apt-get install -y mysql-server
            return 1
        fi
        
        # 检查安装状态
        if ! dpkg -l | grep -q "^ii.*mysql-server"; then
            log_error "MySQL 安装不完整，尝试修复..."
            sudo apt-get install -f
            if ! dpkg -l | grep -q "^ii.*mysql-server"; then
                log_error "MySQL 安装修复失败"
                return 1
            fi
        fi
        
        # 启动 MySQL 服务
        log_info "正在启动 MySQL 服务..."
        sudo systemctl enable mysql || true
        if ! sudo systemctl start mysql; then
            log_error "MySQL 服务启动失败"
            log_info "查看 MySQL 错误日志..."
            sudo tail -n 50 /var/log/mysql/error.log
            log_info "查看系统日志..."
            sudo journalctl -xe --no-pager | grep -i mysql
            return 1
        fi
        
        # 等待服务完全启动
        log_info "等待 MySQL 服务启动..."
        sleep 10
        
        # 验证服务状态
        if ! systemctl is-active --quiet mysql; then
            log_error "MySQL 服务未能正常运行"
            log_info "查看服务状态..."
            sudo systemctl status mysql
            return 1
        fi
        
    elif command_exists yum; then
        # CentOS/RHEL 系统
        log_info "检测到 CentOS/RHEL 系统"
        sudo yum install -y mysql-server
        sudo systemctl enable mysqld
        sudo systemctl start mysqld
    elif command_exists dnf; then
        # Fedora 系统
        log_info "检测到 Fedora 系统"
        sudo dnf install -y mysql-server
        sudo systemctl enable mysqld
        sudo systemctl start mysqld
    else
        log_error "无法确定包管理器，请手动安装 MySQL 服务器"
        return 1
    fi
    
    log_info "MySQL 服务器安装完成。"
    
    # 最终检查 MySQL 服务状态
    if systemctl is-active --quiet mysql || systemctl is-active --quiet mysqld; then
        log_info "MySQL 服务已成功启动"
        # 显示 MySQL 版本信息
        mysql --version
        return 0
    else
        log_error "MySQL 服务启动失败，请检查系统日志"
        return 1
    fi
}

# 测试 MySQL 连接
test_mysql_connection() {
    local host="$1"
    local port="$2"
    local user="$3"
    local pass="$4"
    local db="$5"

    log_info "正在测试 MySQL 连接..."
    if command_exists mysql; then
        if mysql -h "$host" -P "$port" -u "$user" -p"$pass" "$db" -e "SELECT 1" >/dev/null 2>&1; then
            log_info "MySQL 连接测试成功"
            return 0
        else
            log_error "MySQL 连接测试失败"
            return 1
        fi
    else
        log_error "MySQL 客户端未安装，无法测试连接"
        return 1
    fi
}

# 检查 MySQL 服务状态
check_mysql_service() {
    log_info "检查 MySQL 服务状态..."
    if systemctl is-active --quiet mysql || systemctl is-active --quiet mysqld; then
        log_info "MySQL 服务正在运行"
        return 0
    else
        log_warn "MySQL 服务未运行"
        return 1
    fi
}

# 环境检查
check_environment() {
    log_info "检查系统环境..."
    
    # 检查操作系统
    if [ "$(uname)" != "Linux" ]; then
        log_warn "警告：脚本主要在Linux环境下测试，其他系统可能存在兼容性问题"
    fi
    
    # 检查内存
    if command_exists free; then
        total_mem=$(free -m | awk '/^Mem:/{print $2}')
        if [ $total_mem -lt 1024 ]; then
            log_warn "警告：系统内存小于1GB，可能影响运行性能"
        fi
    else
        log_warn "警告：无法检查系统内存"
    fi
    
    # 检查磁盘空间
    free_space=$(df -m . | awk 'NR==2 {print $4}')
    if [ $free_space -lt 1024 ]; then
        log_warn "警告：当前目录可用空间小于1GB"
    fi
    
    log_info "环境检查完成"
}

# 验证输入
validate_input() {
    local var="$1"
    local value="$2"
    
    case "$var" in
        "APP_DEBUG"|"IS_DOCKER"|"SOCKS5_ENABLE"|"MAIL_USE_SOCKS5"|"TG_BOT_GROUP_NOTIFY")
            if [[ ! "$value" =~ ^(true|false)$ ]]; then
                log_error "$var 必须是 true 或 false"
                return 1
            fi
            ;;
        "DB_PORT"|"REDIS_PORT"|"SOCKS5_PORT"|"MAIL_PORT")
            if [[ ! "$value" =~ ^[0-9]+$ ]]; then
                log_error "$var 必须是数字"
                return 1
            fi
            ;;
        "APP_HOST"|"DB_HOST"|"REDIS_HOST"|"MAIL_HOST"|"EMBY_URLBASE"|"PAY_URL")
            if [[ ! "$value" =~ ^https?:// && ! "$value" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
                log_warn "$var 可能格式不正确"
            fi
            ;;
    esac
    return 0
}

# 检查配置依赖
check_dependencies() {
    local has_error=0
    
    # 检查必需的配置项
    for required in "DB_HOST" "DB_NAME" "DB_USER" "DB_PASS" "EMBY_URLBASE" "EMBY_APIKEY"; do
        # 使用grep直接从文件中读取值
        value=$(grep "^$required[[:space:]]*=" .env | sed "s/^$required[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
        if [ -z "$value" ] || [ "$value" = "null" ]; then
            log_error "$required 是必需的配置项"
            has_error=1
        fi
    done
    
    # 如果启用了Redis，检查相关配置
    cache_type=$(grep "^CACHE_TYPE[[:space:]]*=" .env | sed "s/^CACHE_TYPE[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
    if [ "$cache_type" = "redis" ]; then
        redis_host=$(grep "^REDIS_HOST[[:space:]]*=" .env | sed "s/^REDIS_HOST[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
        redis_port=$(grep "^REDIS_PORT[[:space:]]*=" .env | sed "s/^REDIS_PORT[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
        if [ -z "$redis_host" ] || [ -z "$redis_port" ]; then
            log_error "启用Redis缓存需要配置 REDIS_HOST 和 REDIS_PORT"
            has_error=1
        fi
    fi
    
    # 如果启用了Socks5代理，检查相关配置
    socks5_enable=$(grep "^SOCKS5_ENABLE[[:space:]]*=" .env | sed "s/^SOCKS5_ENABLE[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
    if [ "$socks5_enable" = "true" ]; then
        socks5_host=$(grep "^SOCKS5_HOST[[:space:]]*=" .env | sed "s/^SOCKS5_HOST[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
        socks5_port=$(grep "^SOCKS5_PORT[[:space:]]*=" .env | sed "s/^SOCKS5_PORT[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed 's/^["\x27]\(.*\)["\x27]$/\1/')
        if [ -z "$socks5_host" ] || [ -z "$socks5_port" ]; then
            log_error "启用Socks5代理需要配置 SOCKS5_HOST 和 SOCKS5_PORT"
            has_error=1
        fi
    fi
    
    return $has_error
}

# 变量说明
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

# 配置分组
declare -A VARS_GROUPS=(
    ["基础配置"]="APP_DEBUG APP_HOST IS_DOCKER CRONTAB_KEY DEFAULT_LANG"
    ["数据库配置"]="DB_TYPE DB_HOST DB_NAME DB_USER DB_PASS DB_PORT DB_CHARSET"
    ["缓存配置"]="CACHE_TYPE REDIS_HOST REDIS_PORT REDIS_PASS REDIS_DB"
    ["代理配置"]="SOCKS5_ENABLE SOCKS5_HOST SOCKS5_PORT SOCKS5_USERNAME SOCKS5_PASSWORD"
    ["邮件配置"]="MAIL_TYPE MAIL_HOST MAIL_PORT MAIL_USER MAIL_PASS MAIL_FROM_NAME MAIL_FROM_EMAIL MAIL_USE_SOCKS5"
    ["Emby配置"]="EMBY_URLBASE EMBY_APIKEY EMBY_ADMINUSERID EMBY_TEMPLATEUSERID EMBY_LINE_LIST_0_NAME EMBY_LINE_LIST_0_URL EMBY_LINE_LIST_1_NAME EMBY_LINE_LIST_1_URL"
    ["支付配置"]="PAY_URL PAY_MCHID PAY_KEY AVAILABLE_PAYMENT_0"
    ["Telegram配置"]="TG_BOT_TOKEN TG_BOT_USERNAME TG_BOT_ADMIN_ID TG_BOT_GROUP_ID TG_BOT_GROUP_NOTIFY"
    ["AI和验证配置"]="XFYUNLIST_28654731426_APPID XFYUNLIST_28654731426_APIKEY XFYUNLIST_28654731426_APISECRET CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SITEKEY CLOUDFLARE_TURNSTILE_NONINTERACTIVE_SECRET CLOUDFLARE_TURNSTILE_INVISIBLE_SITEKEY CLOUDFLARE_TURNSTILE_INVISIBLE_SECRET TENCENT_MAP_KEY TENCENT_MAP_SK GEMINI_API_KEY"
)

# 定义配置组顺序
GROUP_ORDER=(
    "基础配置"
    "数据库配置"
    "缓存配置"
    "代理配置"
    "邮件配置"
    "Emby配置"
    "支付配置"
    "Telegram配置"
    "AI和验证配置"
)

# 创建卸载脚本
create_uninstall_script() {
    cat > uninstall.sh << 'EOF'
#!/bin/bash
echo "开始卸载..."
docker-compose down
docker rmi ranjie/emby-controller:latest
rm -f .env .env.backup docker-compose.yml
echo "卸载完成"
EOF
    chmod +x uninstall.sh
    log_info "已创建卸载脚本：uninstall.sh"
}

# 创建更新脚本
create_update_script() {
    cat > update.sh << 'EOF'
#!/bin/bash
echo "开始更新..."
docker-compose pull
docker-compose down
docker-compose up -d
echo "更新完成"
EOF
    chmod +x update.sh
    log_info "已创建更新脚本：update.sh"
}

# 检查1panel是否安装
check_1panel() {
    if command_exists 1pctl || [ -d "/opt/1panel" ]; then
        log_info "检测到1panel已安装"
        return 0
    else
        log_info "未检测到1panel"
        return 1
    fi
}

# 检查1panel-network是否存在
check_1panel_network() {
    if docker network ls | grep -q "1panel-network"; then
        log_info "检测到1panel-network网络"
        return 0
    else
        log_info "未检测到1panel-network网络"
        return 1
    fi
}

# 配置docker-compose网络
configure_network() {
    local use_1panel=false
    local compose_content=""
    
    if check_1panel && check_1panel_network; then
        log_info "检测到1panel环境和1panel-network"
        read -p "是否正在使用1panel应用商店安装的MySQL？(y/n): " use_1panel_mysql
        if [ "$use_1panel_mysql" = "y" ]; then
            use_1panel=true
        fi
    fi
    
    # 生成基础的docker-compose.yml内容
    compose_content="services:
  emby-controller:
    container_name: emby-controller
    image: 233bit/emby-controller:latest
    env_file: ./.env
    volumes:
      - ./.env:/app/.env
    ports:
      - \"8018:8018\"
    restart: always
    healthcheck:
      test: [\"CMD\", \"curl\", \"-f\", \"http://localhost:8018/\"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
    deploy:
      resources:
        limits:
          memory: 1G
        reservations:
          memory: 128M
    labels:
      - \"com.docker.compose.project=emby-controller\"
      - \"com.docker.compose.service=emby-controller\"
      - \"maintainer=233bit\"
    networks:
      - default"

    if [ "$use_1panel" = true ]; then
        # 添加1panel-network配置
        compose_content="${compose_content}
      - 1panel-network

networks:
  default:
    driver: bridge
    name: emby-controller-network
  1panel-network:
    external: true
    name: 1panel-network"
    else
        # 只使用默认网络
        compose_content="${compose_content}

networks:
  default:
    driver: bridge
    name: emby-controller-network"
    fi
    
    # 写入docker-compose.yml
    echo "$compose_content" > docker-compose.yml
    log_info "已生成docker-compose.yml配置文件"
}

# 主函数
main() {
    # 检查环境
    check_environment

    # 检查 Docker
    if ! command_exists docker; then
        log_warn "Docker 未安装"
        read -p "是否安装 Docker? (y/n): " install_docker_choice
        if [ "$install_docker_choice" = "y" ]; then
            install_docker
        else
            log_error "Docker 是必须的。退出。"
            exit 1
        fi
    fi

    # 检查 Docker Compose
    if ! command_exists docker-compose; then
        log_warn "Docker Compose 未安装"
        read -p "是否安装 Docker Compose? (y/n): " install_docker_compose_choice
        if [ "$install_docker_compose_choice" = "y" ]; then
            install_docker_compose
        else
            log_error "Docker Compose 是必须的。退出。"
            exit 1
        fi
    fi

    # 检查 MySQL 服务器
    if ! command_exists mysqld || ! check_mysql_service; then
        log_warn "MySQL 服务器未安装或未运行"
        read -p "是否安装 MySQL 服务器? (y/n): " install_mysql_choice
        if [ "$install_mysql_choice" = "y" ]; then
            install_mysql_server
        else
            log_warn "跳过 MySQL 服务器安装，但这可能会影响后续配置"
        fi
    fi

    # 创建目录并进入
    mkdir -p EmbyController
    cd EmbyController

    # 移动脚本到当前目录（如果脚本在父目录）
    if [ -f "../quickstart.sh" ]; then
        log_info "移动脚本到 EmbyController 目录..."
        mv ../quickstart.sh ./
    fi

    # 检查是否存在本地 .env 文件
    if [ -f ".env" ]; then
        log_info "检测到本地 .env 文件已存在"
        read -p "是否使用现有的 .env 文件？(y/n): " use_existing_env
        if [ "$use_existing_env" = "y" ]; then
            log_info "使用现有的 .env 文件"
            read -p "是否进入自定义配置？(y/n): " custom_config_choice
            if [ "$custom_config_choice" = "y" ]; then
                # 备份原有的 .env 文件
                cp .env .env.backup
                
                log_info "开始自定义配置..."
                echo "提示：直接回车将使用默认值"
                echo "=================="

                # 按预定义顺序显示和配置
                for group in "${GROUP_ORDER[@]}"; do
                    echo -e "\n=== $group ==="
                    for var in ${VARS_GROUPS[$group]}; do
                        echo -e "\n配置项: $var"
                        echo "说明: ${VARS_DESC[$var]}"
                        
                        # 获取当前值
                        current_value=$(grep "^$var[[:space:]]*=" .env | sed "s/^$var[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                        
                        if [ -n "$current_value" ]; then
                            echo "当前值: $current_value"
                            read -p "请输入新值 (直接回车保持当前值): " value
                            if [ -n "$value" ]; then
                                if validate_input "$var" "$value"; then
                                    # 使用更严格的模式匹配和替换
                                    if grep -q "^[[:space:]]*$var[[:space:]]*=" .env; then
                                        sed -i "/^[[:space:]]*$var[[:space:]]*=/c\\$var = $value" .env
                                        
                                        # 如果是数据库相关配置，尝试测试连接
                                        if [ "$var" = "DB_HOST" ] || [ "$var" = "DB_PORT" ] || [ "$var" = "DB_USER" ] || [ "$var" = "DB_PASS" ] || [ "$var" = "DB_NAME" ]; then
                                            db_host=$(grep "^DB_HOST[[:space:]]*=" .env | sed "s/^DB_HOST[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_port=$(grep "^DB_PORT[[:space:]]*=" .env | sed "s/^DB_PORT[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_user=$(grep "^DB_USER[[:space:]]*=" .env | sed "s/^DB_USER[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_pass=$(grep "^DB_PASS[[:space:]]*=" .env | sed "s/^DB_PASS[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_name=$(grep "^DB_NAME[[:space:]]*=" .env | sed "s/^DB_NAME[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            
                                            if [ -n "$db_host" ] && [ -n "$db_port" ] && [ -n "$db_user" ] && [ -n "$db_pass" ] && [ -n "$db_name" ]; then
                                                if command_exists mysql; then
                                                    test_mysql_connection "$db_host" "$db_port" "$db_user" "$db_pass" "$db_name"
                                                else
                                                    log_warn "MySQL 客户端未安装，无法测试数据库连接"
                                                fi
                                            fi
                                        fi
                                    else
                                        echo "$var = $value" >> .env
                                    fi
                                else
                                    log_warn "保持原值: $current_value"
                                fi
                            fi
                        else
                            read -p "请输入值 (直接回车跳过): " value
                            if [ -n "$value" ]; then
                                if validate_input "$var" "$value"; then
                                    # 检查变量是否已存在
                                    if grep -q "^[[:space:]]*$var[[:space:]]*=" .env; then
                                        sed -i "/^[[:space:]]*$var[[:space:]]*=/c\\$var = $value" .env
                                    else
                                        echo "$var = $value" >> .env
                                    fi
                                fi
                            else
                                # 检查是否已存在注释的变量
                                if ! grep -q "^[[:space:]]*#[[:space:]]*$var[[:space:]]*=" .env; then
                                    echo "# $var = " >> .env
                                fi
                            fi
                        fi
                    done
                done
            else
                log_info "跳过自定义配置"
            fi
        else
            log_warn "将下载新的 .env 文件并覆盖本地文件..."
            curl -o .env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
            read -p "是否进入自定义配置？(y/n): " custom_config_choice
            if [ "$custom_config_choice" = "y" ]; then
                # 备份原有的 .env 文件
                cp .env .env.backup
                
                log_info "开始自定义配置..."
                echo "提示：直接回车将使用默认值"
                echo "=================="

                # 按预定义顺序显示和配置
                for group in "${GROUP_ORDER[@]}"; do
                    echo -e "\n=== $group ==="
                    for var in ${VARS_GROUPS[$group]}; do
                        echo -e "\n配置项: $var"
                        echo "说明: ${VARS_DESC[$var]}"
                        
                        # 获取当前值
                        current_value=$(grep "^$var[[:space:]]*=" .env | sed "s/^$var[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                        
                        if [ -n "$current_value" ]; then
                            echo "当前值: $current_value"
                            read -p "请输入新值 (直接回车保持当前值): " value
                            if [ -n "$value" ]; then
                                if validate_input "$var" "$value"; then
                                    # 使用更严格的模式匹配和替换
                                    if grep -q "^[[:space:]]*$var[[:space:]]*=" .env; then
                                        sed -i "/^[[:space:]]*$var[[:space:]]*=/c\\$var = $value" .env
                                        
                                        # 如果是数据库相关配置，尝试测试连接
                                        if [ "$var" = "DB_HOST" ] || [ "$var" = "DB_PORT" ] || [ "$var" = "DB_USER" ] || [ "$var" = "DB_PASS" ] || [ "$var" = "DB_NAME" ]; then
                                            db_host=$(grep "^DB_HOST[[:space:]]*=" .env | sed "s/^DB_HOST[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_port=$(grep "^DB_PORT[[:space:]]*=" .env | sed "s/^DB_PORT[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_user=$(grep "^DB_USER[[:space:]]*=" .env | sed "s/^DB_USER[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_pass=$(grep "^DB_PASS[[:space:]]*=" .env | sed "s/^DB_PASS[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            db_name=$(grep "^DB_NAME[[:space:]]*=" .env | sed "s/^DB_NAME[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                            
                                            if [ -n "$db_host" ] && [ -n "$db_port" ] && [ -n "$db_user" ] && [ -n "$db_pass" ] && [ -n "$db_name" ]; then
                                                if command_exists mysql; then
                                                    test_mysql_connection "$db_host" "$db_port" "$db_user" "$db_pass" "$db_name"
                                                else
                                                    log_warn "MySQL 客户端未安装，无法测试数据库连接"
                                                fi
                                            fi
                                        fi
                                    else
                                        echo "$var = $value" >> .env
                                    fi
                                else
                                    log_warn "保持原值: $current_value"
                                fi
                            fi
                        else
                            read -p "请输入值 (直接回车跳过): " value
                            if [ -n "$value" ]; then
                                if validate_input "$var" "$value"; then
                                    # 检查变量是否已存在
                                    if grep -q "^[[:space:]]*$var[[:space:]]*=" .env; then
                                        sed -i "/^[[:space:]]*$var[[:space:]]*=/c\\$var = $value" .env
                                    else
                                        echo "$var = $value" >> .env
                                    fi
                                fi
                            else
                                # 检查是否已存在注释的变量
                                if ! grep -q "^[[:space:]]*#[[:space:]]*$var[[:space:]]*=" .env; then
                                    echo "# $var = " >> .env
                                fi
                            fi
                        fi
                    done
                done
            else
                log_info "跳过自定义配置"
            fi
        fi
    else
        log_info "未检测到 .env 文件，正在下载..."
        curl -o .env https://raw.githubusercontent.com/pjy02/EmbyController/refs/heads/main/example.env
        read -p "是否进入自定义配置？(y/n): " custom_config_choice
        if [ "$custom_config_choice" = "y" ]; then
            # 备份原有的 .env 文件
            cp .env .env.backup
            
            log_info "开始自定义配置..."
            echo "提示：直接回车将使用默认值"
            echo "=================="

            # 按预定义顺序显示和配置
            for group in "${GROUP_ORDER[@]}"; do
                echo -e "\n=== $group ==="
                for var in ${VARS_GROUPS[$group]}; do
                    echo -e "\n配置项: $var"
                    echo "说明: ${VARS_DESC[$var]}"
                    
                    # 获取当前值
                    current_value=$(grep "^$var[[:space:]]*=" .env | sed "s/^$var[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                    
                    if [ -n "$current_value" ]; then
                        echo "当前值: $current_value"
                        read -p "请输入新值 (直接回车保持当前值): " value
                        if [ -n "$value" ]; then
                            if validate_input "$var" "$value"; then
                                # 使用更严格的模式匹配和替换
                                if grep -q "^[[:space:]]*$var[[:space:]]*=" .env; then
                                    sed -i "/^[[:space:]]*$var[[:space:]]*=/c\\$var = $value" .env
                                    
                                    # 如果是数据库相关配置，尝试测试连接
                                    if [ "$var" = "DB_HOST" ] || [ "$var" = "DB_PORT" ] || [ "$var" = "DB_USER" ] || [ "$var" = "DB_PASS" ] || [ "$var" = "DB_NAME" ]; then
                                        db_host=$(grep "^DB_HOST[[:space:]]*=" .env | sed "s/^DB_HOST[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                        db_port=$(grep "^DB_PORT[[:space:]]*=" .env | sed "s/^DB_PORT[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                        db_user=$(grep "^DB_USER[[:space:]]*=" .env | sed "s/^DB_USER[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                        db_pass=$(grep "^DB_PASS[[:space:]]*=" .env | sed "s/^DB_PASS[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                        db_name=$(grep "^DB_NAME[[:space:]]*=" .env | sed "s/^DB_NAME[[:space:]]*=[[:space:]]*//" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
                                        
                                        if [ -n "$db_host" ] && [ -n "$db_port" ] && [ -n "$db_user" ] && [ -n "$db_pass" ] && [ -n "$db_name" ]; then
                                            if command_exists mysql; then
                                                test_mysql_connection "$db_host" "$db_port" "$db_user" "$db_pass" "$db_name"
                                            else
                                                log_warn "MySQL 客户端未安装，无法测试数据库连接"
                                            fi
                                        fi
                                    fi
                                else
                                    echo "$var = $value" >> .env
                                fi
                            else
                                log_warn "保持原值: $current_value"
                            fi
                        fi
                    else
                        read -p "请输入值 (直接回车跳过): " value
                        if [ -n "$value" ]; then
                            if validate_input "$var" "$value"; then
                                # 检查变量是否已存在
                                if grep -q "^[[:space:]]*$var[[:space:]]*=" .env; then
                                    sed -i "/^[[:space:]]*$var[[:space:]]*=/c\\$var = $value" .env
                                else
                                    echo "$var = $value" >> .env
                                fi
                            fi
                        else
                            # 检查是否已存在注释的变量
                            if ! grep -q "^[[:space:]]*#[[:space:]]*$var[[:space:]]*=" .env; then
                                echo "# $var = " >> .env
                            fi
                        fi
                    fi
                done
            done
        else
            log_info "跳过自定义配置"
        fi
    fi

    # 检查配置依赖
    if ! check_dependencies; then
        log_error "配置检查失败，请修正上述错误"
        exit 1
    fi

    # 下载必要文件
    log_info "配置必要文件..."
    # 不再下载docker-compose.yml，而是由configure_network函数生成
    configure_network

    # 创建辅助脚本
    create_uninstall_script
    create_update_script

    # 启动选择
    echo -e "\n请选择启动方式："
    echo "1) Docker Compose (推荐)"
    echo "2) Docker 命令"
    echo "3) 仅生成配置文件"
    read -p "请选择 (1-3): " start_choice

    case "$start_choice" in
        1)
            log_info "使用 Docker Compose 启动..."
            docker-compose up -d
            if [ $? -eq 0 ]; then
                log_info "服务启动成功！"
                echo "访问地址: http://localhost:8018"
            else
                log_error "服务启动失败，请检查配置"
            fi
            ;;
        2)
            log_info "使用 Docker 命令启动..."
            docker run -d -p 8018:8018 --name emby-controller \
                --env-file .env \
                -v $(pwd)/.env:/app/.env \
                ranjie/emby-controller:latest
            if [ $? -eq 0 ]; then
                log_info "容器启动成功！"
                echo "访问地址: http://localhost:8018"
            else
                log_error "容器启动失败，请检查配置"
            fi
            ;;
        3)
            log_info "配置文件已生成，未启动服务"
            ;;
    esac

    # 显示完成信息
    echo -e "\n部署完成！"
    echo "当前目录结构："
    echo "$(pwd)/"
    echo "├── quickstart.sh"
    echo "├── .env"
    echo "├── .env.backup (如果存在)"
    echo "├── docker-compose.yml"
    echo "├── uninstall.sh"
    echo "└── update.sh"
    echo ""
    echo "注意事项："
    echo "1. 如需修改配置，请编辑 .env 文件"
    echo "2. 修改配置后需要重启容器才能生效"
    echo "3. 重启命令: docker restart emby-controller 或 docker-compose restart"
    echo "4. 查看日志: docker logs emby-controller 或 docker-compose logs"
    echo "5. 更新系统: ./update.sh"
    echo "6. 卸载系统: ./uninstall.sh"
}

# 执行主函数
main
