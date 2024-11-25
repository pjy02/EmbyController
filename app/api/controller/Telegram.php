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
        $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
        $telegram->removeWebhook();
        $telegram->setWebhook(['url' => 'https://randallanjie.com/api/telegram/listenWebHook']);
        return 'success';
    }

    public function listenWebHook()
    {
        try {
            $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
            $tgMsg = $telegram->getWebhookUpdates();
            $sendInMsg = $tgMsg['message']['text'];
            $this->chat_id = $tgMsg['message']['chat']['id'];

//            // 写入文件tgmsg.log，判断文件在不在，不在就创建
//            $file = 'tgmsg.log';
//            if (!file_exists($file)) {
//                fopen($file, 'w');
//            }
//            // 写入文件
//            file_put_contents($file, json_encode($tgMsg) . PHP_EOL, FILE_APPEND);

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
                    $this->replayMessage($this->message_text);
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
        $telegramUser = new TelegramModel();
        $user = $telegramUser->where('telegramId', $telegramId)->find();
        if (!$user) {
            return '请先绑定账号';
        }
        if ($type=='chat') {
            $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，你叫R_BOT，你是为我提供服务，请你记住这一点。接下来开始对话，我要说的是：" . $inComeMessage;
            return $this->xfyun($inComeMessage);
        } else if ($type=='welcome') {
            $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，你叫R_BOT，你是为我提供服务，请你记住这一点。现在有一位名叫“" . $inComeMessage . "”的用户加入了群聊，请你根据他名字的特点，生成欢迎语，请直接返回欢迎语。";
            return $this->xfyun($inComeMessage);
        }
    }

    private function xfyun($inComeMessage){
        $addr = "wss://aichat.xf-yun.com/v1/chat";
        //密钥信息，在开放平台-控制台中获取：https://console.xfyun.cn/services/cbm
        $keyList = Config::get('apiinfo.xfyunList');
        // 在$keyList中随机取出一个用户的密钥信息
        $key = array_rand($keyList);
        $Appid = $keyList[$key]['appid'];
        $Apikey = $keyList[$key]['apikey'];
        // $XCurTime =time();
        $ApiSecret = $keyList[$key]['apiSecret'];
        // $XCheckSum ="";

        // $data = $this->getBody("你是谁？");
        $authUrl = $this->assembleAuthUrl("GET",$addr,$Apikey,$ApiSecret);
        //创建ws连接对象
        $client = new Client($authUrl);

        // 连接到 WebSocket 服务器
        if ($client) {
            // 发送数据到 WebSocket 服务器
            $data = $this->getBody($Appid, $inComeMessage);
            $client->send($data);

            // 从 WebSocket 服务器接收数据
            $answer = "";
            while(true){
                $response = $client->receive();
                $resp = json_decode($response,true);
                $code = $resp["header"]["code"];
                echo "从服务器接收到的数据： " . $response;
                if(0 == $code){
                    $status = $resp["header"]["status"];
                    if($status != 2){
                        $content = $resp['payload']['choices']['text'][0]['content'];
                        $answer .= $content;
                    }else{
                        $content = $resp['payload']['choices']['text'][0]['content'];
                        $answer .= $content;
                        $total_tokens = $resp['payload']['usage']['text']['total_tokens'];
                        print("\n本次消耗token用量：\n");
                        print($total_tokens);
                        break;
                    }
                }else{
                    echo "服务返回报错".$response;
                    break;
                }
            }

            return $answer . PHP_EOL . PHP_EOL . "——内容由AI生成，RandallAnjie.com仅提供技术支持，不对内容负责，请核实内容准确性";
        } else {
            return "无法连接到 WebSocket 服务器";
        }



    }

    /**
     * 发送post请求
     * @param string $url 请求地址
     * @param array $post_data post键值对数据
     * @return string
     */
    private function http_request($url, $post_data, $headers) {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => $headers,
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        echo $result;

        return "success";
    }

    //构造参数体
    private function getBody($appid,$question){
        $header = array(
            "app_id" => $appid,
            "uid" => "12345"
        );

        $parameter = array(
            "chat" => array(
                "domain" => "general",
                "temperature" => 0.5,
                "max_tokens" => 1024
            )
        );

        $payload = array(
            "message" => array(
                "text" => array(
                    // 需要联系上下文时，要按照下面的方式上传历史对话
                    // array("role" => "user", "content" => "你是谁"),
                    // array("role" => "assistant", "content" => "....."),
                    // ...省略的历史对话
                    array("role" => "user", "content" => $question)
                )
            )
        );

        $json_string = json_encode(array(
            "header" => $header,
            "parameter" => $parameter,
            "payload" => $payload
        ));

        return $json_string;

    }
    //鉴权方法
    private function assembleAuthUrl($method, $addr, $apiKey, $apiSecret) {
        if ($apiKey == "" && $apiSecret == "") { // 不鉴权
            return $addr;
        }

        $ul = parse_url($addr); // 解析地址
        if ($ul === false) { // 地址不对，也不鉴权
            return $addr;
        }

        // // $date = date(DATE_RFC1123); // 获取当前时间并格式化为RFC1123格式的字符串
        $timestamp = time();
        $rfc1123_format = gmdate("D, d M Y H:i:s \G\M\T", $timestamp);
        // $rfc1123_format = "Mon, 31 Jul 2023 08:24:03 GMT";


        // 参与签名的字段 host, date, request-line
        $signString = array("host: " . $ul["host"], "date: " . $rfc1123_format, $method . " " . $ul["path"] . " HTTP/1.1");

        // 对签名字符串进行排序，确保顺序一致
        // ksort($signString);

        // 将签名字符串拼接成一个字符串
        $sgin = implode("\n", $signString);
        print( $sgin);

        // 对签名字符串进行HMAC-SHA256加密，得到签名结果
        $sha = hash_hmac('sha256', $sgin, $apiSecret,true);
        print("signature_sha:\n");
        print($sha);
        $signature_sha_base64 = base64_encode($sha);

        // 将API密钥、算法、头部信息和签名结果拼接成一个授权URL
        $authUrl = "api_key=\"$apiKey\", algorithm=\"hmac-sha256\", headers=\"host date request-line\", signature=\"$signature_sha_base64\"";

        // 对授权URL进行Base64编码，并添加到原始地址后面作为查询参数
        $authAddr = $addr . '?' . http_build_query(array(
                'host' => $ul['host'],
                'date' => $rfc1123_format,
                'authorization' => base64_encode($authUrl),
            ));

        return $authAddr;
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
        // 判断是否有参数
        if (isset($data['key']) && isset($data['message']) && $data['key'] == Config::get('media.crontabKey')) {
            $groupSetting = Config::get('telegram.groupSetting');
            if (isset($groupSetting['allow_notify']) && $groupSetting['allow_notify']) {
                $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
                $telegram->sendMessage([
                    'chat_id' => $groupSetting['chat_id'],
//                    'chat_id' => '5165597015',
                    'text' => $data['message'],
                    'parse_mode' => 'HTML',
                ]);
            }
        }
    }

    public function startWatching()
    {
        try {
            // 获取get参数
            $data = Request::get();

            // 判断是否有参数
            if (isset($data['key']) && $data['key'] == Config::get('media.crontabKey')) {
                $id = $data['id'] ?? '';
                $userid = $data['userid'] ?? '';
                $type = $data['type'] ?? '';
                $seriesid = $data['seriesid'] ?? '';

                // 根据$userid查询用户信息
                $embyUserModel = new EmbyUserModel();
                $userModel = new UserModel();
                $telegramModel = new TelegramModel();

                $embyUser = $embyUserModel->where('embyId', $userid)->find();
                $user = $userModel->where('id', $embyUser['userId'])->find();
                $userInfoArray = json_decode(json_encode($user['userInfo']), true);
                if (!isset($userInfoArray['lastSeenItem'])) {
                    $userInfoArray['lastSeenItem'] = [];
                }
                $watchNow = [];
                if ($type == 'movies') {
                    $watchNow = [
                        'id' => $id,
                        'time' => time(),
                        'jumpId' => $id
                    ];
                } else if ($type == 'tvshows') {
                    $watchNow = [
                        'id' => $id,
                        'time' => time(),
                        'jumpId' => $seriesid
                    ];
                }
                // 查找是否已经存在，如果存在则删除，然后添加到第一个，如果数量大于10则删除最后一个
                foreach ($userInfoArray['lastSeenItem'] as $key => $item) {
                    if ($item['id'] == $id) {
                        unset($userInfoArray['lastSeenItem'][$key]);
                    }
                }
                array_unshift($userInfoArray['lastSeenItem'], $watchNow);
                if (count($userInfoArray['lastSeenItem']) > 10) {
                    array_pop($userInfoArray['lastSeenItem']);
                }
                $userModel->where('id', $user['id'])->update(['userInfo' => json_encode($userInfoArray)]);

            }
        } catch (\Exception $exception) {
            $message = '第' . $exception->getLine() . '行发生错误：' . $exception->getMessage();
            // 错误内容
            $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
            $telegram->sendMessage([
                'chat_id' => '5165597015',
                'text' => $message . json_encode(Request::get()),
                'parse_mode' => 'HTML',
            ]);
            return false;
        }
    }

    public function finishWatching()
    {
        try {
            // 获取get参数
            $data = Request::get();

            // 判断是否有参数
            if (isset($data['key']) && $data['key'] == Config::get('media.crontabKey')) {
                $id = $data['id'] ?? '';
                $userid = $data['userid'] ?? '';
                $type = $data['type'] ?? '';
                $overview = $data['overview'] ?? '';
                $name = $data['name'] ?? '';
                $seriesid = $data['seriesid'] ?? '';
                $seriesname = $data['seriesname'] ?? '';


                // 根据$userid查询用户信息
                $embyUserModel = new EmbyUserModel();
                $userModel = new UserModel();
                $telegramModel = new TelegramModel();

                $embyUser = $embyUserModel->where('embyId', $userid)->find();
                $user = $userModel->where('id', $embyUser['userId'])->find();
                $telegramUser = $telegramModel->where('userId', $user['id'])->find();

                if ($telegramUser) {
                    $telegramUserInfoArray = json_decode(json_encode($telegramUser['userInfo']), true);
                    if ($userid != '' && $type != '') {
                        $msg = '';
                        if ($type == 'movies') {
                            $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，现在用户刚刚看完了电影《" . $name . "》，这部电影的简介是：" . $overview . "，请你根据这部电影的特点，还有你的知识库，对用户表示感谢观看这部电影，并且期望用户在我的网站多看电影，回答内容中要包含电影名，直接告诉我需要告诉用户的内容。";
                            $msg = $this->xfyun($inComeMessage);
                        } else if ($type == 'tvshows') {
                            $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，现在用户刚刚看完了剧集《" . $seriesname . "》中名为《" . $name . "》的一集，这部剧集的简介是：" . $overview . "，请你根据这部剧集的特点，还有你的知识库，对用户表示感谢观看这部剧集，并且期望用户在我的网站多看剧集，回答内容中要包含剧集名称和这一集的名称，直接告诉我需要告诉用户的内容。";
                            $msg = $this->xfyun($inComeMessage);
                        }
                    }

                    if (isset($telegramUserInfoArray['notification']) && $telegramUserInfoArray['notification'] == 1) {
                        $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
                        $telegram->sendMessage([
                            'chat_id' => $telegramUser['telegramId'],
                            'text' => $msg,
                            'parse_mode' => 'HTML',
                        ]);
                    }
                }

                $userInfoArray = json_decode(json_encode($user['userInfo']), true);
                if (!isset($userInfoArray['lastSeenItem'])) {
                    $userInfoArray['lastSeenItem'] = [];
                }
                $watchNow = [];
                if ($type == 'movies') {
                    $watchNow = [
                        'id' => $id,
                        'time' => time(),
                        'jumpId' => $id
                    ];
                } else if ($type == 'tvshows') {
                    $watchNow = [
                        'id' => $id,
                        'time' => time(),
                        'jumpId' => $seriesid
                    ];
                }
                // 查找是否已经存在，如果存在则删除，然后添加到第一个，如果数量大于10则删除最后一个
                foreach ($userInfoArray['lastSeenItem'] as $key => $item) {
                    if ($item['id'] == $id) {
                        unset($userInfoArray['lastSeenItem'][$key]);
                    }
                }
                array_unshift($userInfoArray['lastSeenItem'], $watchNow);
                if (count($userInfoArray['lastSeenItem']) > 10) {
                    array_pop($userInfoArray['lastSeenItem']);
                }
                $userModel->where('id', $user['id'])->update(['userInfo' => json_encode($userInfoArray)]);

            }
        } catch (\Exception $exception) {
            $message = '第' . $exception->getLine() . '行发生错误：' . $exception->getMessage();
            // 错误内容
            $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
            $telegram->sendMessage([
                'chat_id' => '5165597015',
                'text' => $message . json_encode(Request::get()),
                'parse_mode' => 'HTML',
            ]);
            return false;
        }
    }

}
