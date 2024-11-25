<?php

namespace app\index\controller;

use app\BaseController;
use app\index\model\FinanceRecordModel;
use app\index\model\UserModel;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Request;
use think\facade\View;

class Account extends BaseController
{
    public function sign()
    {
        View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.non-interactive.sitekey'));
        if (request()->isGet()) {
            $signkey = input('signkey', "", 'trim');
            $errMsg = '';
            if ($signkey && $signkey != '' && Cache::has('get_sign_' . $signkey)) {
                $signkey = Cache::get('get_sign_' . $signkey);
            } else {
                $signkey = '';
                $errMsg = '签到链接已失效'. $signkey;
            }
            View::assign('signkey', $signkey);
            View::assign('errMsg', $errMsg);
            return view();
        } else if (request()->isPost()) {
            $data = request()->post();
            $cfToken = $data['token']??'';
            $signkey = $data['signkey']??'';

            if ($cfToken == '' || $signkey == '') {
                return json(['code' => 401, 'message' => '参数错误']);
            }

            $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
                $_SERVER['HTTP_X_REAL_IP'] ??
                $_SERVER['HTTP_CF_CONNECTING_IP'] ??
                Request::ip();

            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $realIp = trim($ipList[0]);
            }
            $SECRET_KEY = Config::get('apiinfo.cloudflareTurnstile.non-interactive.secret');
            $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
            $vdata = [
                'secret' => $SECRET_KEY,
                'response' => $cfToken,
                'remoteip' => $realIp
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $vdata);
            $output = curl_exec($ch);
            curl_close($ch);
            $output = json_decode($output, true);
            if ($output['success'] == true) {
                $userId = Cache::get('post_signkey_' . $signkey);
                if ($userId == '') {
                    return json(['code' => 400, 'message' => '用户信息不存在，请重新核对']);
                }
                $userModel = new UserModel();
                $user = $userModel->where('id', $userId)->find();
                $userInfoArray = json_decode(json_encode($user['userInfo']), true);
                if (isset($userInfoArray['loginIps']) && ((isset($userInfoArray['lastSignTime']) && in_array($realIp, $userInfoArray['loginIps']) && $userInfoArray['lastSignTime'] != date('Y-m-d')) || (!isset($userInfoArray['lastSignTime']) && in_array($realIp, $userInfoArray['loginIps'])))){
                    $userInfoArray['lastSignTime'] = date('Y-m-d');
                    $user->userInfo = json_encode($userInfoArray);
                    $score = mt_rand(10, 30) / 100;
                    $user->rCoin = $user->rCoin + $score;
                    $user->save();

                    $financeRecordModel = new FinanceRecordModel();
                    $financeRecordModel->save([
                        'userId' => $userId,
                        'action' => 4,
                        'count' => $score,
                        'recordInfo' => [
                            'message' => '签到获取' . $score . 'R币',
                        ]
                    ]);
                    sendTGMessage($userId, "签到成功！今日签到获取" . $score . "R币");
                    return json(['code' => 200, 'message' => '签到成功！今日签到获取' . $score . 'R币']);
                } else {
                    return json(['code' => 400, 'message' => '您今日已签到或者您的网络环境异常，请核对后再试']);
                }
            } else {
                return json(['code' => 401, 'message' => '签到失败']);
            }
        }
    }
}
