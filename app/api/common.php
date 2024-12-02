<?php
// 应用公共文件

use app\media\model\NotificationModel;
use app\media\model\TelegramModel;
use mailer\Mailer;
use think\facade\Cache;
use think\facade\Config;
use app\BaseController;
use Telegram\Bot\Api;
use WebSocket\Client;

function sendStationMessage($id, $message)
{
    $notificationModel = new NotificationModel();
    $notificationModel->save([
        'type' => 0,
        'fromUserId' => 0,
        'toUserId' => $id,
        'message' => $message,
    ]);
}