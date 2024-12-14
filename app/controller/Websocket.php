<?php
namespace app\controller;

use think\Response;
use Workerman\Worker;
use app\websocket\WebSocketServer;

class Websocket
{
    protected $worker;
    
    public function connect()
    {
        // 检查是否是 WebSocket 握手请求
        if (!isset($_SERVER['HTTP_UPGRADE']) || strtolower($_SERVER['HTTP_UPGRADE']) !== 'websocket') {
            return Response::create('非法请求', 'html', 400);
        }

        // 获取 WebSocket 服务器实例
        $this->worker = new Worker();
        
        // 设置 WebSocket 协议
        $this->worker->protocol = 'Workerman\Protocols\Websocket';
        
        // 处理连接
        $connection = new \Workerman\Connection\TcpConnection(1);
        $connection->protocol = $this->worker->protocol;
        
        // 升级 HTTP 协议到 WebSocket 协议
        $connection->onWebSocketConnect = function($connection) {
            // 处理 WebSocket 连接
            $server = new WebSocketServer();
            $server->handleConnection($connection);
        };
        
        // 触发协议升级
        if (isset($_SERVER['HTTP_SEC_WEBSOCKET_KEY'])) {
            $connection->websocketHandshake();
        }
        
        return Response::create('Switching Protocols', 'html', 101)
            ->header([
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => base64_encode(sha1($_SERVER['HTTP_SEC_WEBSOCKET_KEY'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true))
            ]);
    }
} 