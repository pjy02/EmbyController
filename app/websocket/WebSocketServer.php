<?php
namespace app\websocket;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Channel\Client as ChannelClient;
use app\media\model\NotificationModel;
use app\media\model\UserModel;
use think\facade\Db;
use think\facade\Cache;

class WebSocketServer
{
    private static $clients = [];
    private static $instance = null;
    private $channelClient;
    private $logDir = __DIR__ . '/../../runtime/log';
    private $isChannelConnected = false;

    private function __construct() {
        $this->initializeChannel();
    }

    private function initializeChannel() {
        try {
            // 设置 Channel 客户端
            ChannelClient::connect('127.0.0.1', 2206);
            $this->channelClient = new ChannelClient();
            $this->isChannelConnected = true;
            
            // 订阅消息
            ChannelClient::on('broadcast', function($event) {
                $this->handleChannelBroadcast($event);
            });
            
            $this->writeLog('channel_info.log', 'Channel client initialized successfully');
        } catch (\Exception $e) {
            $this->isChannelConnected = false;
            $this->writeLog('channel_error.log', 'Channel client init error: ' . $e->getMessage());
            
            // 尝试重新连接
            $this->scheduleChannelReconnect();
        }
    }

    private function scheduleChannelReconnect() {
        // 使用定时器尝试重新连接
        \Workerman\Lib\Timer::add(10, function() {
            if (!$this->isChannelConnected) {
                $this->initializeChannel();
            }
        }, [], false);
    }

    private function handleChannelBroadcast($event) {
        try {
            $data = json_decode($event, true);
            if (!$data || !isset($data['type'])) {
                return;
            }

            if ($data['userId'] == 0) {
                // Broadcast to all users
                $this->broadcastToAllUsers($data);
            } elseif (isset(self::$clients[$data['userId']])) {
                // Send to specific user
                $this->sendToSpecificUser($data['userId'], $data);
            } else {
                // 用户不在线，记录日志用于调试
                $this->writeLog('offline_message.log', 
                    "Message for offline user {$data['userId']}: " . json_encode($data));
            }
        } catch (\Exception $e) {
            $this->writeLog('channel_error.log', 'Channel broadcast error: ' . $e->getMessage());
        }
    }

    private function broadcastToAllUsers($data) {
        foreach (self::$clients as $userId => $connections) {
            foreach ($connections as $connection) {
                try {
                    $connection->send(json_encode([
                        'type' => $data['type'],
                        'data' => $data['data']
                    ]));
                } catch (\Exception $e) {
                    $this->writeLog('send_error.log', 
                        "Error sending to user $userId: " . $e->getMessage());
                    // 移除有问题的连接
                    $this->removeConnection($connection, $userId);
                }
            }
        }
    }

    private function sendToSpecificUser($userId, $data) {
        if (!isset(self::$clients[$userId])) {
            return;
        }

        foreach (self::$clients[$userId] as $key => $connection) {
            try {
                $connection->send(json_encode([
                    'type' => $data['type'],
                    'data' => $data['data']
                ]));
            } catch (\Exception $e) {
                $this->writeLog('send_error.log', 
                    "Error sending to user $userId: " . $e->getMessage());
                // 移除有问题的连接
                $this->removeConnection($connection, $userId);
            }
        }
    }

    private function removeConnection($connection, $userId) {
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

    private function writeLog($filename, $message) {
        try {
            $logFile = $this->logDir . '/' . $filename;
            $time = date('Y-m-d H:i:s');
            $logMessage = "[$time] $message\n";
            
            // 确保目录存在
            if (!file_exists($this->logDir)) {
                mkdir($this->logDir, 0777, true);
            }
            
            // 写入日志
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // 如果日志写入失败，也不要抛出异常
            error_log("Failed to write log: " . $e->getMessage());
        }
    }

    private function logClients($action) {
        $clientsInfo = [];
        foreach (self::$clients as $userId => $connections) {
            $clientsInfo[$userId] = count($connections);
        }
        $message = "$action - Current clients: " . json_encode($clientsInfo) . " in " . posix_getpid();
        $this->writeLog('clients.log', $message);
    }

    public function onMessage($connection, $data) {
        $this->logClients('Before onMessage');

        try {
            if (empty($data)) {
                return;
            } elseif ($data === 'ping') {
                $connection->send('pong');
                return;
            }

            $message = json_decode($data, true);
            if (!$message || !isset($message['type'])) {
                $this->writeLog('message_error.log', 'Invalid message format: ' . $data);
                return;
            }

            switch ($message['type']) {
                case 'auth':
                    $this->handleAuth($connection, $message);
                    break;
                case 'read_message':
                    $this->handleReadMessage($connection, $message);
                    break;
                default:
                    $this->writeLog('message_error.log', 'Unknown message type: ' . $message['type']);
                    break;
            }
        } catch (\Exception $e) {
            $this->writeLog('message_error.log', 'onMessage error: ' . $e->getMessage());
        }

        $this->logClients('After onMessage');
    }

    private function handleAuth($connection, $message) {
        try {
            $userId = $message['userId'] ?? null;
            if (!$userId) {
                $connection->close();
                return;
            }

            // 尝试从缓存获取用户信息
            $cacheKey = "user_{$userId}";
            $user = Cache::get($cacheKey);
            
            if (!$user) {
                $userModel = new UserModel();
                $user = $userModel->where('id', $userId)->find();
                if (!$user) {
                    $connection->close();
                    return;
                }
                // 将用户信息存入缓存，有效期30分钟
                Cache::set($cacheKey, $user, 1800);
            } else {
                // 从缓存获取的用户对象需要转换为模型对象
                $userModel = new UserModel();
                $user = $userModel->where('id', $userId)->find();
                if (!$user) {
                    $connection->close();
                    return;
                }
            }

            $key = $message['key'] ?? '';
            $clientKey = md5($userId . $user->password);
            if ($key !== $clientKey) {
                $connection->close();
                return;
            }

            // 认证成功，添加连接
            if (!isset(self::$clients[$userId])) {
                self::$clients[$userId] = [];
            }
            self::$clients[$userId][] = $connection;
            $connection->userId = $userId;

            // 发送初始未读消息数
            $this->sendToUser($userId, 'unread_count', [
                'count' => $this->getUnreadCount($userId)
            ]);

            // 给所有用户发送当前连接数
            $this->sendToUser(0, 'connection_count', [
                'count' => count(self::$clients)
            ]);

            $this->writeLog('auth_success.log', "User $userId authenticated successfully");
        } catch (\Exception $e) {
            $this->writeLog('auth_error.log', 'Auth error: ' . $e->getMessage());
            $connection->close();
        }
    }

    private function handleReadMessage($connection, $message) {
        try {
            if (!isset($connection->userId) || !isset(self::$clients[$connection->userId])) {
                $connection->close();
                return;
            }

            $notificationId = $message['notificationId'] ?? null;
            if (!$notificationId) {
                return;
            }

            // 尝试从缓存获取通知信息
            $cacheKey = "notification_{$notificationId}";
            $notification = Cache::get($cacheKey);
            
            if (!$notification) {
                $notificationModel = new NotificationModel();
                $notification = $notificationModel->where('id', $notificationId)->find();
            }
            
            if (!$notification || $notification->toUserId != $connection->userId) {
                return;
            }

            // 更新消息状态
            $notification->readStatus = 1;
            $notification->save();
            
            // 更新缓存中的通知信息
            Cache::set($cacheKey, $notification, 1800);
            
            // 清除用户的未读消息数缓存
            $unreadCacheKey = "unread_count_{$connection->userId}";
            Cache::rm($unreadCacheKey);

            // 发送未读消息数
            $this->sendToUser($connection->userId, 'unread_count', [
                'count' => $this->getUnreadCount($connection->userId)
            ]);

            // 通知发送者消息已读
            $this->sendToUser($notification->fromUserId, 'read_message', [
                'notificationId' => $notification->id,
                'toUserId' => $notification->toUserId
            ]);

            $this->writeLog('read_message.log', "Message $notificationId marked as read by user {$connection->userId}");
        } catch (\Exception $e) {
            $this->writeLog('read_message_error.log', 'Read message error: ' . $e->getMessage());
        }
    }

    public function onClose($connection) {
        $this->logClients('Before onClose');
        
        try {
            if (isset($connection->userId)) {
                $userId = $connection->userId;
                $this->removeConnection($connection, $userId);
                $this->writeLog('connection.log', "User $userId disconnected");
            }

            // 给所有用户发送当前连接数
            $this->sendToUser(0, 'connection_count', [
                'count' => count(self::$clients)
            ]);
        } catch (\Exception $e) {
            $this->writeLog('close_error.log', 'onClose error: ' . $e->getMessage());
        }

        $this->logClients('After onClose');
    }

    public function sendToUser($userId, $type, $data = []) {
        $this->logClients('Before sendToUser');
        
        try {
            if (!$this->isChannelConnected) {
                $this->writeLog('channel_error.log', 'Channel not connected, attempting to reconnect');
                $this->initializeChannel();
                
                if (!$this->isChannelConnected) {
                    $this->writeLog('channel_error.log', 'Failed to reconnect to channel');
                    return false;
                }
            }

            // 通过 Channel 广播消息到所有进程
            ChannelClient::publish('broadcast', json_encode([
                'userId' => $userId,
                'type' => $type,
                'data' => $data
            ]));

            $this->writeLog('send_success.log', "Message sent to user $userId: $type");
            return true;
        } catch (\Exception $e) {
            $this->isChannelConnected = false;
            $this->writeLog('channel_error.log', 'Error publishing message: ' . $e->getMessage());
            return false;
        }

        $this->logClients('After sendToUser');
    }

    private function getUnreadCount($userId) {
        try {
            // 尝试从缓存获取未读消息数
            $cacheKey = "unread_count_{$userId}";
            $count = Cache::get($cacheKey);
            
            if ($count === false) {
                $notificationModel = new NotificationModel();
                $count = $notificationModel
                    ->where('toUserId', $userId)
                    ->where('readStatus', 0)
                    ->count();
                // 将未读消息数存入缓存，有效期5分钟
                Cache::set($cacheKey, $count, 300);
            }
            
            return $count;
        } catch (\Exception $e) {
            $this->writeLog('websocket_error.log', "Error getting unread count for user $userId: " . $e->getMessage());
            return 0;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}
    
    // 添加健康检查方法
    public function getStatus() {
        return [
            'channel_connected' => $this->isChannelConnected,
            'client_count' => count(self::$clients),
            'clients' => array_map(function($connections) {
                return count($connections);
            }, self::$clients)
        ];
    }
}
