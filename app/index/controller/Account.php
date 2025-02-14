<?php

namespace app\index\controller;

use app\BaseController;
use app\index\model\FinanceRecordModel;
use app\index\model\UserModel;
use think\facade\Cache;
use think\facade\Request;
use think\facade\View;
use think\facade\Config;

class Account extends BaseController
{
    public function sign()
    {
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
            View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.noninteractive.sitekey'));
            return view();
        } else if (request()->isPost()) {
            $data = request()->post();
            $signkey = $data['signkey']??'';

            if ($signkey == '') {
                return json(['code' => 401, 'message' => '参数错误']);
            }

            if (!judgeCloudFlare('noninteractive', $data['token']??'')) {
                return json(['code' => 400, 'message' => '您今日已签到或者您的网络环境异常，请核对后再试']);
            }

            $userId = Cache::get('post_signkey_' . $signkey);
            if ($userId == '') {
                return json(['code' => 400, 'message' => '用户信息不存在，请重新核对']);
            }
            $userModel = new UserModel();
            $user = $userModel->where('id', $userId)->find();
            $userInfoArray = json_decode(json_encode($user['userInfo']), true);

            $flag = false;
            if (isset($userInfoArray['loginIps']) && ((isset($userInfoArray['lastSignTime']) && in_array(getRealIp(), $userInfoArray['loginIps']) && $userInfoArray['lastSignTime'] != date('Y-m-d')) || (!isset($userInfoArray['lastSignTime']) && in_array(getRealIp(), $userInfoArray['loginIps'])))){
                $flag = true;
            } else {
                if (config('map.enable') && isset($userInfoArray['lastLoginLocation'])) {
                    $lastloginLocation = json_decode(json_encode($userInfoArray['lastLoginLocation']), true);
                    $thinLocation = getLocation();
                    if ($lastloginLocation == $thinLocation) {
                        $flag = true;
                    } else if ($lastloginLocation['nation'] == $thinLocation['nation'] && $lastloginLocation['city'] == $thinLocation['city']) {
                        $flag = true;
                    }
                }
            }

            if ($flag) {
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
                return json(['code' => 401, 'message' => '签到失败']);
            }
        }
    }
}
