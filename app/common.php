<?php
// 应用公共文件
use app\media\model\NotificationModel;
use Carbon\Carbon;
use think\facade\Config;
use think\facade\Request;
use WebSocket\Client;

/**
 * 获取Gravatar头像 QQ邮箱取用qq头像
 * @param $email
 * @param $s
 * @param $d
 * @param $r
 * @param $img
 * @param $atts
 * @return string
 * @author Anjie
 * @date 2024-07-07
 */
function getGravatar($email, $s = 96, $d = 'mp', $r = 'g', $img = false, $atts = array())
{
    preg_match_all('/((\d)*)@qq.com/', $email, $vai);
    if (empty($vai['1']['0'])) {
        // 使用Gravatar服务
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val)
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
    } else {
        // 使用QQ邮箱头像服务
        $uin = $vai['1']['0'];
        // 自适应判断应该选择哪一个大小的spec
        if ($s <= 70) {
            $spec = 1;
        } elseif ($s <= 120) {
            $spec = 3;
        } elseif ($s <= 390) {
            $spec = 4;
        } else {
            $spec = 5;
        }
        $url = 'https://q2.qlogo.cn/headimg_dl?dst_uin=' . $uin . '&spec=' . $spec;

        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val)
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
    }
    return $url;
}

/**
 * 格式化字节大小
 * @param $bytes
 * @param $precision
 * @return string
 * @author Anjie
 * @date 2024-07-07
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
}

function timeAgo($datetime)
{
    // 尝试将字符串转换为时间戳
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    $time = Carbon::createFromTimestamp($timestamp);
    $now = Carbon::now();

    $diffInSeconds = -1 * $now->diffInSeconds($time);
    if ($diffInSeconds < 60) {
        return round($diffInSeconds) . '秒前';
    }

    $diffInMinutes = -1 * $now->diffInMinutes($time);

    if ($diffInMinutes < 60) {
        return round($diffInMinutes) . '分钟前';
    }

    $diffInHours = -1 * $now->diffInHours($time);
    if ($diffInHours < 24) {
        return round($diffInHours) . '小时前';
    }

    $diffInDays = -1 * $now->diffInDays($time);
    if ($diffInDays < 7) {
        return round($diffInDays) . '天前';
    }

    return $time->format('Y-m-d H:i');
}

function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

function getPaySign($param){
    ksort($param);
    reset($param);
    $signstr = '';

    foreach($param as $k => $v){
        if($k != "sign" && $k != "sign_type" && $v!=''){
            $signstr .= $k.'='.$v.'&';
        }
    }
    $signstr = substr($signstr,0,-1);
    $signstr .= Config::get('payment.epay.key');
    $sign = md5($signstr);
    return $sign;
}

function getHttpResponse($url, $post = false, $timeout = 10){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $httpheader[] = "Accept: */*";
    $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
    $httpheader[] = "Connection: close";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if($post){
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function xfyun($inComeMessage){
    $addr = "wss://aichat.xf-yun.com/v1/chat";
    //密钥信息，在开放平台-控制台中获取：https://console.xfyun.cn/services/cbm
    $keyList = Config::get('apiinfo.xfyunList');

    if (empty($keyList)) {
        return "请先在后台配置讯飞AI密钥";
    }

    // 在$keyList中随机取出一个用户的密钥信息
    $key = array_rand($keyList);
    $Appid = $keyList[$key]['appid'];
    $Apikey = $keyList[$key]['apikey'];
    // $XCurTime =time();
    $ApiSecret = $keyList[$key]['apisecret'];
    // $XCheckSum ="";

    // $data = $this->getBody("你是谁？");
    $authUrl = assembleAuthUrl("GET",$addr,$Apikey,$ApiSecret);
    //创建ws连接对象
    $client = new Client($authUrl);

    // 连接到 WebSocket 服务器
    if ($client) {
        // 发送数据到 WebSocket 服务器
        $data = getBody($Appid, $inComeMessage);
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
function http_request($url, $post_data, $headers) {
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
function getBody($appid,$question){
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
function assembleAuthUrl($method, $addr, $apiKey, $apiSecret) {
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

function getRealIp()
{
    $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
        $_SERVER['HTTP_X_REAL_IP'] ??
        $_SERVER['HTTP_CF_CONNECTING_IP'] ??
        Request::ip();

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $realIp = trim($ipList[0]);
    }

    return $realIp;
}

function judgeCloudFlare($type = 'noninteractive', $cfToken){
    $SECRET_KEY = Config::get('apiinfo.cloudflareTurnstile.' . $type . '.secret');
    $SITE_KEY = Config::get('apiinfo.cloudflareTurnstile.' . $type . '.sitekey');

    if (!$SECRET_KEY || !$SITE_KEY || $SECRET_KEY == '' || $SITE_KEY == '') {
        return true;
    }

    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $vdata = [
        'secret' => $SECRET_KEY,
        'response' => $cfToken,
        'remoteip' => getRealIp()
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $vdata);
    $output = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($output, true);
    if (!$output['success']) {
        return false;
    }
    return true;
}

function sendStationMessage($id, $message)
{
    $notificationModel = new NotificationModel();
    $notification = $notificationModel->create([
        'type' => 0,
        'fromUserId' => 0,
        'toUserId' => $id,
        'message' => $message,
    ]);

    $webSocketServer = \app\websocket\WebSocketServer::getInstance();

    // 发送新消息通知
    $webSocketServer->sendToUser($id, 'new_message', [
        'message' => $message,
        'notificationId' => $notification->id,
        'createdAt' => $notification->createdAt,
        'fromUserId' => 0,
        'toUserId' => $id,
        'fromUserName' => 'System',
    ]);


    // 更新未读消息数
    $webSocketServer->sendToUser($id, 'unread_count', [
        'count' => $notificationModel->where('toUserId', $id)->where('readStatus', 0)->count()
    ]);
}