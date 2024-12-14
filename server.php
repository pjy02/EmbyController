<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;
use think\facade\Db;
use Channel\Server as ChannelServer;

require_once __DIR__ . '/vendor/autoload.php';

// 加载 .env 配置
$env = parse_ini_file(__DIR__ . '/.env');

// 定义数据库配置
define('DB_CONFIG', [
    'type'          => $env['DB_TYPE'] ?? 'mysql',
    'hostname'      => $env['DB_HOST'] ?? '127.0.0.1',
    'database'      => $env['DB_NAME'] ?? '',
    'username'      => $env['DB_USER'] ?? '',
    'password'      => $env['DB_PASS'] ?? '',
    'hostport'      => $env['DB_PORT'] ?? '3306',
    'charset'       => $env['DB_CHARSET'] ?? 'utf8',
    'prefix'        => 'rc_',
]);

// 初始化 Channel 服务器（必须在最前面）
$channel_server = new ChannelServer('127.0.0.1', 2206);

// 确保 Channel 服务器启动后再初始化其他服务
$channel_server->onWorkerStart = function() {
    echo "Channel server started\n";
};

// WebSocket 服务器（内部服务，只监听本地）
$ws = new Worker("websocket://127.0.0.1:2346");
$ws->count = 4;

// 在 Worker 启动时初始化
$ws->onWorkerStart = function($worker) {
    // 等待 Channel 服务器启动
    sleep(1);
    
    // 初始化数据库连接
    $config = DB_CONFIG;
    $dbConfig = [
        'default' => 'mysql',
        'connections' => [
            'mysql' => $config
        ]
    ];
    Db::setConfig($dbConfig);
    echo "Database connection initialized\n";

    // 初始化 WebSocketServer
    global $webSocketServer;
    $webSocketServer = \app\websocket\WebSocketServer::getInstance();
};

$ws->onMessage = function($connection, $data) {
    global $webSocketServer;
    $webSocketServer->onMessage($connection, $data);
};

$ws->onClose = function($connection) {
    global $webSocketServer;
    $webSocketServer->onClose($connection);
};

// WebSocket 代理服务器（对外服务）
$wsProxy = new Worker('websocket://0.0.0.0:2347');
$wsProxy->count = 4;

// 在代理 Worker 启动时也初始化数据库连接
$wsProxy->onWorkerStart = function($worker) {
    // 初始化数据库连接
    $config = DB_CONFIG;
    $dbConfig = [
        'default' => 'mysql',
        'connections' => [
            'mysql' => $config
        ]
    ];
    Db::setConfig($dbConfig);
    echo "Proxy database connection initialized\n";
};

$wsProxy->onConnect = function($connection) {
    echo "New connection\n";
};

$wsProxy->onWebSocketConnect = function($connection, $httpBuffer) {
    echo "WebSocket connection established\n";
    
    // 创建到内部服务器的连接
    $innerConnection = new AsyncTcpConnection('ws://127.0.0.1:2346');
    $connection->innerConnection = $innerConnection;
    
    // 转发消息
    $innerConnection->onMessage = function($innerConnection, $data) use ($connection) {
        try {
            // 记录转发的消息
            $logFile = __DIR__ . '/runtime/log/proxy.log';
            $time = date('Y-m-d H:i:s');
            $message = "[$time] Forwarding to client: $data\n";
            file_put_contents($logFile, $message, FILE_APPEND);

            $connection->send($data);
        } catch (\Exception $e) {
            // 记录错误
            $logFile = __DIR__ . '/runtime/log/proxy_error.log';
            $time = date('Y-m-d H:i:s');
            $message = "[$time] Error forwarding message: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $message, FILE_APPEND);
        }
    };
    
    $connection->onMessage = function($connection, $data) use ($innerConnection) {
        try {
            // 记录接收到的消息
            $logFile = __DIR__ . '/runtime/log/proxy.log';
            $time = date('Y-m-d H:i:s');
            $message = "[$time] Received from client: $data\n";
            file_put_contents($logFile, $message, FILE_APPEND);

            $innerConnection->send($data);
        } catch (\Exception $e) {
            // 记录错误
            $logFile = __DIR__ . '/runtime/log/proxy_error.log';
            $time = date('Y-m-d H:i:s');
            $message = "[$time] Error sending to inner server: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $message, FILE_APPEND);
        }
    };
    
    // 处理连接关闭
    $innerConnection->onClose = function($innerConnection) use ($connection) {
        echo "Inner connection closed\n";
        $connection->close();
    };
    
    $connection->onClose = function($connection) {
        echo "Client connection closed\n";
        if (isset($connection->innerConnection)) {
            $connection->innerConnection->close();
        }
    };

    // 处理错误
    $innerConnection->onError = function($connection, $code, $msg) {
        $logFile = __DIR__ . '/runtime/log/proxy_error.log';
        $time = date('Y-m-d H:i:s');
        $message = "[$time] Inner connection error: $code - $msg\n";
        file_put_contents($logFile, $message, FILE_APPEND);
    };
    
    // 连接到内部服务器
    $innerConnection->connect();
};

$wsProxy->onMessage = function($connection, $data) {
    // 记录代理服务器收到的消息
    $logFile = __DIR__ . '/runtime/log/proxy.log';
    $time = date('Y-m-d H:i:s');
    $message = "[$time] Proxy received: $data\n";
    file_put_contents($logFile, $message, FILE_APPEND);
};

$wsProxy->onClose = function($connection) {
    echo "Connection closed\n";
};

$wsProxy->onError = function($connection, $code, $msg) {
    $logFile = __DIR__ . '/runtime/log/proxy_error.log';
    $time = date('Y-m-d H:i:s');
    $message = "[$time] Proxy error: $code - $msg\n";
    file_put_contents($logFile, $message, FILE_APPEND);
};

// 启动所有服务器
Worker::runAll(); 