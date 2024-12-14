<?php
namespace app\websocket;

use think\Response;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

class Proxy
{
    protected $localPort = 2347;
    protected $logFile;

    public function __construct()
    {
        $this->logFile = __DIR__ . '/../../runtime/log/proxy.log';
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    protected function log($message)
    {
        $time = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$time] $message\n", FILE_APPEND);
    }

    protected function finishRequest()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // 在非 FastCGI 环境下，我们只需要确保所有输出都已发送
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
            }
            flush();
        }
    }

    public function connect()
    {
        try {
            $this->log("New connection attempt");
            
            // 检查是否是 WebSocket 握手请求
            if (!isset($_SERVER['HTTP_UPGRADE']) || strtolower($_SERVER['HTTP_UPGRADE']) !== 'websocket') {
                $this->log("Invalid request - not a websocket upgrade");
                return Response::create('非法请求', 'html', 400);
            }

            // 生成 WebSocket 握手响应
            $key = $_SERVER['HTTP_SEC_WEBSOCKET_KEY'] ?? '';
            if (empty($key)) {
                $this->log("Missing WebSocket key");
                return Response::create('Missing WebSocket Key', 'html', 400);
            }

            // 生成握手响应
            $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            
            // 创建响应
            $response = Response::create('', 'html', 101);
            
            // 设置响应头
            $headers = [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $accept,
                'Sec-WebSocket-Version' => '13'
            ];

            $requestHeaders = getallheaders();
            if (isset($requestHeaders['Sec-WebSocket-Protocol'])) {
                $headers['Sec-WebSocket-Protocol'] = $requestHeaders['Sec-WebSocket-Protocol'];
            }

            if (isset($requestHeaders['Sec-WebSocket-Extensions'])) {
                $headers['Sec-WebSocket-Extensions'] = $requestHeaders['Sec-WebSocket-Extensions'];
            }

            $this->log("Sending handshake response with headers: " . json_encode($headers));
            $response->header($headers);
            
            // 发送响应头
            $response->send();

            // 结束请求但保持连接
            $this->finishRequest();

            // 返回响应
            return $response;

        } catch (\Throwable $e) {
            $error = sprintf(
                "Error: %s\nFile: %s\nLine: %d\nTrace:\n%s",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            $this->log($error);
            return Response::create('Internal Server Error: ' . $e->getMessage(), 'html', 500);
        }
    }
} 