<?php
// 应用公共文件
use Carbon\Carbon;
use think\facade\Config;

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
    $signstr .= Config::get('payment.key');
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