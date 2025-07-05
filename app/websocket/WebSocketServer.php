<?php
namespace app\websocket;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Channel\Client as ChannelClient;
use app\media\model\NotificationModel;
use app\media\model\UserModel;
use think\facade\Db;

class WebSocketServer
{
    private static $clients = [];
    private static $instance = null;
    private $channelClient;

    private $logDir = __DIR__ . '/../../runtime/log';

    private function __construct() {
        try {
            // 设置 Channel 客户端
            ChannelClient::connect('127.0.0.1', 2206);
            $this->channelClient = new ChannelClient();
            
            // 订阅消息
            ChannelClient::on('broadcast', function($event) {
                try {
                    $data = json_decode($event, true);
                    if ($data['userId'] == 0) {
                        // Broadcast to all users
                        foreach (self::$clients as $userId => $connections) {
                            foreach ($connections as $connection) {
                                $connection->send(json_encode([
                                    'type' => $data['type'],
                                    'data' => $data['data']
                                ]));
                            }
                        }
                    } elseif (isset(self::$clients[$data['userId']])) {
                        // Send to specific user
                        foreach (self::$clients[$data['userId']] as $connection) {
                            $connection->send(json_encode([
                                'type' => $data['type'],
                                'data' => $data['data']
                            ]));
                        }
                    }
                } catch (\Exception $e) {
                    $this->safeLog('channel_error.log', "Channel broadcast error: " . $e->getMessage());
                }
            });
        } catch (\Exception $e) {
            $this->safeLog('channel_error.log', "Channel client init error: " . $e->getMessage());
        }
    }

    /**
     * 安全的日志写入方法，处理权限问题
     */
    private function safeLog($filename, $message)
    {
        try {
            $logFile = $this->logDir . '/' . $filename;
            $time = date('Y-m-d H:i:s');
            $fullMessage = "[$time] $message\n";
            
            // 检查并创建日志目录
            if (!$this->ensureLogDirectory()) {
                return false;
            }
            
            // 尝试写入日志文件
            if ($this->writeLogFile($logFile, $fullMessage)) {
                return true;
            }
            
            // 如果写入失败，尝试写入到临时目录
            $tempLogFile = sys_get_temp_dir() . '/' . $filename;
            return $this->writeLogFile($tempLogFile, $fullMessage);
            
        } catch (\Exception $e) {
            // 如果所有方法都失败，至少记录到错误日志
            error_log("WebSocket Log Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 确保日志目录存在且可写
     */
    private function ensureLogDirectory()
    {
        try {
            if (!file_exists($this->logDir)) {
                if (!mkdir($this->logDir, 0755, true)) {
                    return false;
                }
            }
            
            // 检查目录是否可写
            if (!is_writable($this->logDir)) {
                // 尝试修改权限
                @chmod($this->logDir, 0755);
                if (!is_writable($this->logDir)) {
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 安全地写入日志文件
     */
    private function writeLogFile($logFile, $message)
    {
        try {
            // 如果文件不存在，创建它
            if (!file_exists($logFile)) {
                if (!touch($logFile)) {
                    return false;
                }
                @chmod($logFile, 0644);
            }
            
            // 检查文件是否可写
            if (!is_writable($logFile)) {
                @chmod($logFile, 0644);
                if (!is_writable($logFile)) {
                    return false;
                }
            }
            
            // 写入文件
            return file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX) !== false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    private function logClients($action)
    {
        $clientsInfo = [];
        foreach (self::$clients as $userId => $connections) {
            $clientsInfo[$userId] = count($connections);
        }
        $message = "$action - Current clients: " . json_encode($clientsInfo) . " in " . posix_getpid();
        $this->safeLog('clients.log', $message);
    }

    public function onMessage($connection, $data)
    {
        $this->logClients('Before onMessage');

        if (empty($data)) {
            return;
        } elseif ($data === 'ping') {
            $connection->send('pong');
            return;
        }

        $message = json_decode($data, true);

        if ($message && isset($message['type']) && $message['type'] === 'auth') {
            $userId = $message['userId'];

            $userModel = new UserModel();
            $user = $userModel->where('id', $userId)->find();
            if ($user) {
                $key = $message['key'] ?? '';
                $clientKey = md5($userId . $user->password);
                if ($key == $clientKey) {
                    if (!isset(self::$clients[$userId])) {
                        self::$clients[$userId] = [];
                    }
                    self::$clients[$userId][] = $connection;
                    $connection->userId = $userId;

                    // 发送当前所有websocket连接数
//                    $this->sendToUser($userId, 'connection_count', [
//                        'count' => count(self::$clients)
//                    ]);

                    // 发送初始未读消息数
                    $this->sendToUser($userId, 'unread_count', [
                        'count' => $this->getUnreadCount($userId)
                    ]);

                    // 给所有用户发送当前连接数
                    $this->sendToUser(0, 'connection_count', [
                        'count' => count(self::$clients)
                    ]);

                } else {
                    $connection->close();
                }
            } else {
                $connection->close();
            }
        }

        if ($message && isset($message['type']) && $message['type'] === 'read_message') {
            // 在连接池中查找用户的连接
            if (isset($connection->userId) && isset(self::$clients[$connection->userId])) {
                $notificationId = $message['notificationId'];
                // 判断消息tuUserId是否是当前用户
                $notificationModel = new NotificationModel();
                $notification = $notificationModel->where('id', $notificationId)->find();
                if ($notification && $notification->toUserId == $connection->userId) {
                    // 更新消息状态
                    $notification->readStatus = 1;
                    $notification->save();
                    // 发送未读消息数
                    $this->sendToUser($connection->userId, 'unread_count', [
                        'count' => $this->getUnreadCount($connection->userId)
                    ]);

                    $this->sendToUser($notification->fromUserId, 'read_message', [
                        'notificationId' => $notification->id,
                        'toUserId' => $notification->toUserId
                    ]);

                }
            } else {
                $connection->close();
            }
        }
        $this->logClients('After onMessage');
    }

    public function onClose($connection)
    {
        $this->logClients('Before onClose');
        if (isset($connection->userId)) {
            $userId = $connection->userId;
            if (isset(self::$clients[$userId])) {
                $index = array_search($connection, self::$clients[$userId]);
                if ($index !== false) {
                    unset(self::$clients[$userId][$index]);
                }
                if (empty(self::$clients[$userId])) {
                    unset(self::$clients[$userId]);
                }
            }
        }
        // 给所有用户发送当前连接数
        $this->sendToUser(0, 'connection_count', [
            'count' => count(self::$clients)
        ]);
        $this->logClients('After onClose');
    }

    public function sendToUser($userId, $type, $data = [])
    {
        $this->logClients('Before sendToUser');
        
        try {
            // 通过 Channel 广播消息到所有进程
            ChannelClient::publish('broadcast', json_encode([
                'userId' => $userId,
                'type' => $type,
                'data' => $data
            ]));
        } catch (\Exception $e) {
            $this->safeLog('channel_error.log', "Error publishing message: " . $e->getMessage());
        }

        $this->logClients('After sendToUser');
    }

    private function getUnreadCount($userId)
    {
        try {
            $notificationModel = new NotificationModel();
            return $notificationModel
                ->where('toUserId', $userId)
                ->where('readStatus', 0)
                ->count();
        } catch (\Exception $e) {
            $this->safeLog('websocket_error.log', "Error getting unread count for user $userId: " . $e->getMessage());
            return 0;
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}
}
