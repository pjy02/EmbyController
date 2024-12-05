<?php

namespace app\api\controller;

use app\api\model\EmbyUserModel;
use app\api\model\TelegramModel;
use app\api\model\UserModel;
use think\facade\Cache;
use think\facade\Config;
use app\BaseController;
use Telegram\Bot\Api;
use think\facade\Request;
use WebSocket\Client;

class Telegram extends BaseController
{
    public function index()
    {
        $time = time();
        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'time' => $time
            ]
        ]);
    }

    public function ping()
    {
        $time = time();
        return json([
            'code' => 200,
            'msg' => 'pong',
            'data' => [
                'time' => $time
            ]
        ]);
    }

    private $chat_id; //群ID
    private $message_text;//群消息内容


    /**
     * 错误代码
     * @var int
     */
    protected $errorCode;

    /**
     * 错误信息
     * @var string
     */
    protected $errorMessage = '';


    /**
     * 返回错误代码
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * 返回错误信息
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }


    public function restartWebHook()
    {
        $token = Config::get('telegram.botConfig.bots.randallanjie_bot.token');
        $weburl = Config::get('app.app_host');
        if ($token == 'notgbot') {
            return '请先配置Telegram机器人';
        } else if ($weburl == '') {
            return '请先配置APP_HOST';
        } else {
            $telegram = new Api($token);
            $telegram->removeWebhook();
            $telegram->setWebhook(['url' => $weburl . '/api/telegram/listenWebHook']);
            return 'success';
        }
    }

    public function listenWebHook()
    {
        $token = Config::get('telegram.botConfig.bots.randallanjie_bot.token');
        if ($token == 'notgbot') {
            return '请先配置Telegram机器人';
        }
        try {
            $telegram = new Api($token);
            $tgMsg = $telegram->getWebhookUpdates();
            $sendInMsg = $tgMsg['message']['text'];
            $this->chat_id = $tgMsg['message']['chat']['id'];

            if (isset($tgMsg['edit_date']) && $tgMsg['edit_date'] != $tgMsg['date']) {
                exit();
            }

            if (isset($tgMsg['message']['from']['is_bot']) && $tgMsg['message']['from']['is_bot']) {
                exit();
            }

            $commonds = [];
            $replyMsg = '';
            $atFlag = false;
            $cmdFlag = false;

            if (isset($tgMsg['message']['entities'])) {
                $entities = $tgMsg['message']['entities'];
                usort($entities, function ($a, $b) {
                    return $b['offset'] - $a['offset'];
                });
                foreach ($entities as $entity) {
                    if ($entity['type'] == 'bot_command') {
                        $cmdFlag = true;
                        $commonds[] = substr($sendInMsg, $entity['offset'], $entity['length']);
                        $sendInMsg = substr($sendInMsg, 0, $entity['offset']) . substr($sendInMsg, $entity['offset'] + $entity['length']);
                    } else if ($entity['type'] == 'mention') {
                        $mention = substr($tgMsg['message']['text'], $entity['offset'], $entity['length']);
                        $sendInMsg = substr($sendInMsg, 0, $entity['offset']) . substr($sendInMsg, $entity['offset'] + $entity['length']);
                        if ($mention == '@randallanjie_bot') {
                            $atFlag = true;
                        }
                    }
                }
            }
            $sendInMsg = trim(preg_replace('/\s(?=\s)/', '', $sendInMsg));
            $sendInMsgList = explode(' ', $sendInMsg);
            if (isset($tgMsg['message']['chat']['type']) && $tgMsg['message']['chat']['type'] == 'private') {
                if ($cmdFlag) {
                    foreach ($commonds as $cmd) {
                        if ($cmd == '/start') {
                            if ($sendInMsgList[0] && $sendInMsgList[0] != '') {
                                $key = $sendInMsgList[0];
                                $telegramId = $tgMsg['message']['from']['id'];
                                $result = $this->mediaBind($key, $telegramId);
                                if ($result['message'] == '绑定成功') {
                                    $replyMsg = '绑定成功，欢迎使用' . PHP_EOL;
                                } else {
                                    $replyMsg = $result['message'] . PHP_EOL;
                                }
                            }
                            $replyMsg .= $this->getInfo($tgMsg['message']['from']['id']);

                        } else if ($cmd == '/bind') {
                            if ($sendInMsgList[0] && $sendInMsgList[0] != '') {
                                $key = $sendInMsgList[0];
                                $result = $this->mediaBind($key, $tgMsg['message']['from']['id']);
                                $replyMsg = $result['message'];
                            } else {
                                $replyMsg = '请输入绑定密钥，如：/bind 123456';
                            }
                        } else if ($cmd == '/unbind') {
                            if ($sendInMsgList[0] && $sendInMsgList[0] == 'confirm') {
                                $result = $this->mediaUnBind($tgMsg['message']['from']['id']);
                                $replyMsg = $result['message'];
                            } else {
                                $replyMsg = '如果需要解绑请输入：<code>/unbind confirm</code>';
                            }
                        } else if ($cmd == '/coin') {
                            $replyMsg = $this->getCoin($tgMsg['message']['from']['id']);
                        } else if ($cmd == '/sign') {
                            $replyMsg = $this->getSign($tgMsg['message']['from']['id']);
                        } else if ($cmd == '/notification') {
                            if ($sendInMsgList[0] && $sendInMsgList[0] != '') {
                                if ($sendInMsgList[0] == 'on' || $sendInMsgList[0] == 'off') {
                                    $replyMsg = $this->setNotificationStatus($sendInMsgList[0]);
                                } else {
                                    $replyMsg = '未知参数'.$sendInMsgList[0];
                                }
                            } else {
                                $replyMsg = $this->setNotificationStatus('get');
                            }
                        } else {
                            $replyMsg = '未知命令'.$cmd;
                        }
                        $this->message_text = $replyMsg;
                        $this->replayMessage($this->message_text);
                    }
                } else {
                    $this->message_text = $this->getReplyFromAI('chat', $tgMsg['message']['from']['id'], $sendInMsg);
                    if ($this->message_text != '') {
                        $this->replayMessage($this->message_text);
                    }
                }
            } else if (isset($tgMsg['message']['chat']['type']) && $tgMsg['message']['chat']['type'] == 'group') {
                if ($atFlag) {
                    $this->replayMessage("暂不支持群内使用机器人，请私聊使用");
                }
            }
        } catch (\Exception $exception) {
            $message = '第' . $exception->getLine() . '行发生错误：' . $exception->getMessage();
            // 错误内容
            $this->replayMessage($message);
            return false;
        }
    }


    private function replayMessage($result)
    {
        $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
        try {
            return $telegram->sendMessage([
                'chat_id' => $this->chat_id,  // message.chat.id   这个id必须是消息发布的群，不然不能实现回复
                'text' => $result??$this->message_text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $exception) {
            $this->errorCode = -1;
            $this->errorMessage = $exception->getMessage(); // 一般来说都是 chat_id 有误
            return false;
        }
    }

    private function getInfo($telegramId)
    {
        $telegramModel = new TelegramModel();
        $user = $telegramModel
            ->where('telegramId', $telegramId)
            ->join('rc_user', 'rc_user.id = rc_telegram_user.userId')
            ->field('rc_telegram_user.*, rc_user.nickName, rc_user.userName, rc_user.userInfo as userInfoFromUser')
            ->find();
        $message = '';
//        $message.=json_encode($user).PHP_EOL;
        if ($user) {
            $message .= '尊敬的用户 <strong>' . ($user['nickName']??$user['userName']) . '</strong> ';
        }
        $message .= '您好，欢迎使用 @RandallAnjie_bot' . PHP_EOL;
        if ($telegramId != $this->chat_id) {
//            $message .= '当前群组ID是：<code>' . $this->chat_id . '</code>' . PHP_EOL;
        } else {
            $message .= '您的TelegramID是：<code>' . $this->chat_id . '</code>' . PHP_EOL;
        }

        if ($user) {
            $userInfoArray = json_decode($user['userInfoFromUser'], true);
            if (isset($userInfoArray['lastLoginIp'])) {
                $message .= '您上次登录IP是：' . ($telegramId == $this->chat_id?$userInfoArray['lastLoginIp']:'此项已隐藏') . PHP_EOL;
            }
            if (isset($userInfoArray['lastLoginTime'])) {
                $message .= '您上次登录时间是：' . $userInfoArray['lastLoginTime'] . PHP_EOL;
            }
            if (isset($userInfoArray['lastSignTime']) && $userInfoArray['lastSignTime'] == date('Y-m-d')) {
                $message .= '您今天已签到～' . PHP_EOL;
            } else {
                $message .= '您今天还未签到，请前往站点进行签到～' . PHP_EOL;
            }
        } else {
            $message .= '您还未绑定账号';
        }

        return $message;

    }

    private function mediaBind($key, $telegramId)
    {
        $cacheKey = 'tgBindKey_' . $key;
        if (Cache::has($cacheKey)) {
            $userId = Cache::get($cacheKey);
            $telegramModel = new TelegramModel();
            if ($telegramModel->where('userId', $userId)->find()) {
                return ['code' => 400, 'message' => '该用户已绑定过'];
            }
            if ($telegramModel->where('telegramId', $telegramId)->find()) {
                return ['code' => 400, 'message' => '该Telegram账号已绑定过'];
            }
            $data = [
                'userId' => $userId,
                'telegramId' => $telegramId,
                'type' => 1,
            ];
            $telegramModel->save($data);
            return ['code' => 200, 'message' => '绑定成功'];
        } else {
            return ['code' => 400, 'message' => '绑定密钥无效'];
        }
    }

    private function mediaUnBind($telegramId)
    {
        $telegramModel = new TelegramModel();
        $user = $telegramModel->where('telegramId', $telegramId)->find();
        if ($user) {
            $telegramModel->where('telegramId', $telegramId)->delete();
            return ['code' => 200, 'message' => '解绑成功'];
        } else {
            return ['code' => 400, 'message' => '未绑定账号'];
        }
    }

    private function getCoin($telegramId)
    {
        $telegramModel = new TelegramModel();
        $user = $telegramModel
            ->where('telegramId', $telegramId)
            ->join('rc_user', 'rc_user.id = rc_telegram_user.userId')
            ->field('rc_telegram_user.*, rc_user.nickName, rc_user.userName, rc_user.rCoin, rc_user.userInfo as userInfoFromUser')
            ->find();
        if ($user) {
            return '您的余额是： <strong>' . $user['rCoin'] . '</strong> R币';
        } else {
            return '请先绑定账号';
        }
    }

    private function getReplyFromAI($type, $telegramId, $inComeMessage)
    {

        $keyList = Config::get('apiinfo.xfyunList');

        if (empty($keyList)) {
            return "";
        }

        $telegramUser = new TelegramModel();
        $user = $telegramUser->where('telegramId', $telegramId)->find();
        if (!$user) {
            return '请先绑定账号';
        }
        if ($type=='chat') {
            $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，你叫R_BOT，你是为我提供服务，请你记住这一点。接下来开始对话，我要说的是：" . $inComeMessage;
            return xfyun($inComeMessage);
        } else if ($type=='welcome') {
            $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，你叫R_BOT，你是为我提供服务，请你记住这一点。现在有一位名叫“" . $inComeMessage . "”的用户加入了群聊，请你根据他名字的特点，生成欢迎语，请直接返回欢迎语。";
            return xfyun($inComeMessage);
        }
    }

    private function setNotificationStatus(string $int)
    {
        $telegramModel = new TelegramModel();
        $telegramId = $this->chat_id;
        $user = $telegramModel->where('telegramId', $telegramId)->find();
        if ($user) {
            $userInfoArray = json_decode(json_encode($user['userInfo']), true);
            if ($int == 'get') {
                if (isset($userInfoArray['notification']) && ($userInfoArray['notification'] == 1 || $userInfoArray['notification'] == "1")) {
                    return '您的TG通知状态是： <strong>开启</strong>' . PHP_EOL . '如果需要关闭通知请使用命令：<code>/notification off</code>';
                } else {
                    return '您的TG通知状态是： <strong>关闭</strong>' . PHP_EOL . '如果需要开启通知请使用命令：<code>/notification on</code>';
                }
            } else if ($int == 'on') {
                $userInfoArray['notification'] = 1;
                $telegramModel->where('telegramId', $telegramId)->update(['userInfo' => json_encode($userInfoArray)]);
                return '通知已开启';
            } else if ($int == 'off') {
                $userInfoArray['notification'] = 0;
                $telegramModel->where('telegramId', $telegramId)->update(['userInfo' => json_encode($userInfoArray)]);
                return '通知已关闭';
            }
        } else {
            return '请先绑定账号';
        }
    }

    private function getSign($id)
    {
        $telegramModel = new TelegramModel();
        $telegramId = $id;
        $tgUser = $telegramModel->where('telegramId', $telegramId)->find();
        if ($tgUser) {
            $userModel = new UserModel();
            $user = $userModel->where('id', $tgUser['userId'])->find();
            $userInfoArray = json_decode(json_encode($user['userInfo']), true);
            if ((isset($userInfoArray['lastSignTime']) && $userInfoArray['lastSignTime'] != date('Y-m-d')) || !isset($userInfoArray['lastSignTime'])) {
                // 生成两个随机字符串
                $randStr = substr(md5(time()), 0, 8);
                $signKey = substr(md5(time()), 8, 8);
                Cache::set('get_sign_' . $signKey, $randStr, 300);
                Cache::set('post_signkey_' . $randStr, $user['id'], 300);
                return '请点击链接签到：<a href="https://randallanjie.com/index/account/sign?signkey=' . $signKey . '">点击签到</a>';
            } else {
                return '您今天已签到～';
            }
        } else {
            return '请先绑定账号';
        }
    }

    public function sendMsgToGroup()
    {
        // 获取get参数
        $data = Request::get();
        $token = Config::get('telegram.botConfig.bots.randallanjie_bot.token');
        // 判断是否有参数
        if (isset($data['key']) && isset($data['message']) && $data['key'] == Config::get('media.crontabKey') && $token != 'notgbot') {
            $groupSetting = Config::get('telegram.groupSetting');
            if (isset($groupSetting['allow_notify']) && $groupSetting['allow_notify']) {
                $telegram = new Api($token);
                $telegram->sendMessage([
                    'chat_id' => $groupSetting['chat_id'],
                    'text' => $data['message'],
                    'parse_mode' => 'HTML',
                ]);
            }
        }
    }

}
