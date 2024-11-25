<?php

namespace app\media\controller;

use app\BaseController;
use app\media\model\FinanceRecordModel;
use app\media\model\MediaCommentModel;
use app\media\model\PayRecordModel;
use app\media\model\TelegramModel;
use think\facade\Request;
use think\facade\Session;
use app\media\model\UserModel as UserModel;
use app\media\model\SysConfigModel as SysConfigModel;
use app\media\model\EmbyUserModel as EmbyUserModel;
use app\media\model\RequestModel as RequestModel;
use app\media\validate\Login as LoginValidate;
use app\media\validate\Register as RegisterValidate;
use app\media\validate\Update as UpdateValidate;
use think\facade\View;
use think\facade\Config;
use mailer\Mailer;
use think\facade\Cache;
use Telegram\Bot\Api;
use WebSocket\Client;



class User extends BaseController
{
    public function index()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }

        // 获取emby用户信息
        $embyUserModel = new EmbyUserModel();
        $embyUserFromDatabase = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        if ($embyUserFromDatabase && $embyUserFromDatabase['embyId'] != null) {
            $embyId = $embyUserFromDatabase['embyId'];
            $activateTo = $embyUserFromDatabase['activateTo'];
        } else {
            $embyId = null;
            $activateTo = null;
        }

        $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
            $_SERVER['HTTP_X_REAL_IP'] ??
            $_SERVER['HTTP_CF_CONNECTING_IP'] ??
            Request::ip();

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $realIp = trim($ipList[0]);
        }

        $userModel = new UserModel();
        $rUser = $userModel->where('id', Session::get('r_user')->id)->find();
        $userInfoArray = json_decode(json_encode($rUser['userInfo']), true);

        if (isset($userInfoArray['lastSeenItem']) && $userInfoArray['lastSeenItem'] != null) {
            View::assign('lastSeenItem', $userInfoArray['lastSeenItem']);
        } else {
            View::assign('lastSeenItem', null);
        }

        if (isset($userInfoArray['loginIps']) && ((isset($userInfoArray['lastSignTime']) && in_array($realIp, $userInfoArray['loginIps']) && $userInfoArray['lastSignTime'] != date('Y-m-d')) || (!isset($userInfoArray['lastSignTime']) && in_array($realIp, $userInfoArray['loginIps'])))){
            View::assign('canSign', true);
        } else {
            View::assign('canSign', false);
        }
        View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.invisible.sitekey'));

        View::assign('rUser', $rUser);
        View::assign('embyId', $embyId);
        View::assign('activateTo', $activateTo);
        return view();
    }

    public function login()
    {
        // 已登录自动跳转
        if (Session::has('r_user')) {
            return redirect((string) url('media/user/index'));
        }
        // 初始返回参数
        $results = '';
        // 处理POST请求
        if (Request::isPost()) {
            // 验证输入数据
            $data = Request::post();
            $validate = new LoginValidate();
            if (!$validate->scene('login')->check($data)) {
                // 验证不通过
                $results = $validate->getError();
            } else {
                $cfToken = $data['cf-turnstile-response']??'';

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

                if (!$output['success']) {
                    $results = "环境异常，请重新验证后点击登录";
                } else {
                    $userModel = new UserModel();
                    $user = $userModel->judgeUser($data['username'], $data['password']);
                    if ($user) {
                        $embyUserModel = new EmbyUserModel();
                        $embyUserFromDatabase = $embyUserModel->getEmbyId($user->id);
                        if ($embyUserFromDatabase) {
                            $user->embyId = $embyUserFromDatabase;
                        } else {
                            $user->embyId = null;
                        }

                        $userInfoArray = json_decode(json_encode($user['userInfo']), true);


                        $userInfoArray['lastLoginIp'] = $realIp;
                        $userInfoArray['lastLoginTime'] = date('Y-m-d H:i:s');

                        if (!isset($userInfoArray['loginIps'])) {
                            $userInfoArray['loginIps'] = [];
                        }
                        if (!in_array($realIp, $userInfoArray['loginIps'])) {
                            $TGMessage = '检测到您的账户在新IP地址：' . $realIp . '登录，此地址您从未登录过，请检查您的账户安全。现在改地址已经被记录，可以用于签到/找回密码等操作。';
                            $userInfoArray['loginIps'][] = $realIp;
                        } else {
                            $TGMessage = '检测到您的账户在' . $realIp . '登录';
                        }
                        $TGMessage .= PHP_EOL . "浏览器：" . $_SERVER['HTTP_USER_AGENT'];
                        sendTGMessage($user->id, $TGMessage);

                        $userJson = json_encode($userInfoArray);
                        $userModel->updateUserInfo($user->id, $userJson);

                        // 跳转到之前访问的页面或默认页面
                        $jumpUrl = Session::get('jump_url');
                        if (empty($jumpUrl)) {
                            $jumpUrl = (string)url('media/user/index');
                        } else {
                            Session::delete('jump_url');
                        }

                        if (isset($data['remember']) && $data['remember'] == 'on') {
                            Session::set('expire', 30 * 24 * 60 * 60);
                        } else {
                            Session::set('expire', 1 * 60 * 60);
                        }
                        Session::set('m_embyId', $user->embyId);
                        Session::set('r_user', $user);
                        return redirect($jumpUrl);
                    } else {
                        $results = "登录名或密码错误或该用户被禁用";
                    }
                }
            }
        }
        // 渲染登录页面
        View::assign('result', $results);
        View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.non-interactive.sitekey'));
        return view();
    }


    public function register()
    {
        // 已登录自动跳转
        if (Session::has('r_user')) {
            return redirect((string) url('media/user/index'));
        }
        // 初始返回参数
        $results = '';
        $data = [];
        // 处理POST请求
        if (Request::isPost()) {
            // 验证输入数据
            $data = Request::post();
            $cfToken = $data['cf-turnstile-response']??'';

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

            if (!$output['success']) {
                $results = "环境异常，请重新验证后点击注册";
            } else {
                $validate = new RegisterValidate();
                if (!$validate->scene('register')->check($data)) {
                    // 验证不通过
                    $results = $validate->getError();
                } else {
                    // 验证通过，进行注册逻辑

                    // 验证邮箱验证码
                    $cacheKey = 'verifyCode_register_' . $data['email'];
                    $verifyCode = Cache::get($cacheKey);
                    if ($verifyCode != $data['verify']) {
                        $results = "邮箱验证码错误";
                    } else {
                        $userModel = new UserModel();
                        $result = $userModel->registerUser($data['username'], $data['password'], $data['email']);
                        if ($result['error']) {
                            $user = null;
                        } else {
                            $user = $result['user'];
                        }
                        if ($user) {
                            Session::set('r_user', $user);
                            $results = "注册成功";
                            if (is_string($user->userInfo)) {
                                $userInfoArray = json_decode(json_encode($user->userInfo), true);
                            } else {
                                $userInfoArray = [];
                            }

                            $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
                                $_SERVER['HTTP_X_REAL_IP'] ??
                                $_SERVER['HTTP_CF_CONNECTING_IP'] ??
                                Request::ip();

                            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                                $realIp = trim($ipList[0]);
                            }

                            $userInfoArray['lastLoginIp'] = $realIp;
                            $userInfoArray['lastLoginTime'] = date('Y-m-d H:i:s');
                            if (!isset($userInfoArray['loginIps'])) {
                                $userInfoArray['loginIps'] = [];
                            }
                            $userInfoArray['loginIps'][] = $realIp;
                            $userJson = json_encode($userInfoArray);
                            $userModel->updateUserInfo($user->id, $userJson);

                            // 跳转到之前访问的页面或默认页面
                            $jumpUrl = Session::get('jump_url');
                            if (empty($jumpUrl)) {
                                $jumpUrl = (string)url('media/user/index');
                            } else {
                                Session::delete('jump_url');
                            }
                            return redirect($jumpUrl);
                        } else {
                            $results = "注册失败：" . $result['error'];
                        }
                    }
                }
            }
        }
        // 渲染注册页面
        View::assign('data', $data);
        View::assign('result', $results);
        View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.non-interactive.sitekey'));
        return view();
    }

    public function update()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        // 处理POST请求
        if (Request::isPost()) {
            $data = Request::post();
            $userModel = new UserModel();
            $validate = new UpdateValidate();
            if (!$validate->scene('update')->check(['username' => $data['username'], 'email' => $data['email'], 'password' => $data['password']])) {
                return json(['code' => 400, 'message' => $validate->getError()]);
            }
            $user = $userModel->where('id', Session::get('r_user')->id)->find();
            if ($user->email != $data['email']) {
                $code = Cache::get('verifyCode_update_' . $data['email']);
                if ($code != $data['verify']) {
                    return json(['code' => 400, 'message' => '邮箱验证码错误']);
                }
            }
            $results = $userModel->updateUser(Session::get('r_user')->id, $data);
            if ($results['user']) {
                Session::set('r_user', $results['user']);
                return json(['code' => 200, 'message' => '更新成功']);
            } else {
                return json(['code' => 400, 'message' => '更新失败：' . $results['error']]);
            }
        }
    }

    public function forgot()
    {
        // 已登录自动跳转
        if (Session::has('r_user')) {
            return redirect((string) url('media/user/index'));
        }
        $results = '';
        $code = '';
        $email = '';
        if (Request::isGet()) {
            $data = Request::get();
            if (isset($data['code'])) {
                $code = $data['code'];
            }

            if (isset($data['email'])) {
                $email = $data['email'];
            }
            View::assign('email', $email);
            View::assign('result', $results);
            View::assign('code', $code);
            View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.non-interactive.sitekey'));
            return view();
        } elseif (Request::isPost()) {
            $data = Request::post();
            $cfToken = $data['cf-turnstile-response']??'';

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

            if (!$output['success']) {
                $results = "环境异常，请重新验证后重试";
            } else {
                if (isset($data['email']) && (!isset($data['code']))) {
                    $userModel = new UserModel();
                    $user = $userModel->where('email', $data['email'])->find();
                    if (!$user) {
                        $user = $userModel->where('userName', $data['email'])->find();
                    }
                    if ($user && $user->email) {
                        $code = rand(100000, 999999);
                        Cache::set('verifyCode_forgot_' . $user->email, $code, 300);

                        $Url = "https://randallanjie.com/media/user/forgot?email=" . $user->email . "&code=" . $code;
                        $Email = $user->email;
                        $SiteUrl = "https://randallanjie.com/media";

                        $sysConfigModel = new SysConfigModel();
                        $findPasswordTemplate = $sysConfigModel->where('key', 'findPasswordTemplate')->find();
                        if ($findPasswordTemplate) {
                            $findPasswordTemplate = $findPasswordTemplate['value'];
                        } else {
                            $findPasswordTemplate = '您的找回密码链接是：<a href="{Url}">{Url}</a>';
                        }

                        $findPasswordTemplate = str_replace('{Url}', $Url, $findPasswordTemplate);
                        $findPasswordTemplate = str_replace('{Email}', $Email, $findPasswordTemplate);
                        $findPasswordTemplate = str_replace('{SiteUrl}', $SiteUrl, $findPasswordTemplate);

                        sendEmailForce($user->email, '找回密码——算艺轩', $findPasswordTemplate);
                        sendTGMessage($user->id, "您正在尝试找回密码，如果不是您本人操作，请忽略此消息。");
                        $code = '';
                    }
                    $results = '如果该用户存在，重置密码链接已发送到您的邮箱';
                }
                if (isset($data['email']) && isset($data['code'])) {
                    $email = $data['email'];
                    $code = $data['code'];

                    $userModel = new UserModel();
                    $validate = new UpdateValidate();
                    if (!$validate->scene('update')->check(['username' => null, 'email' => $data['email'], 'password' => $data['password']])) {
                        $results = $validate->getError();
                    } else {
                        $verifyCode = Cache::get('verifyCode_forgot_' . $data['email']);
                        if ($verifyCode != $data['code']) {
                            $results = '验证码错误';
                        } else {
                            $user = $userModel->where('email', $data['email'])->find();
                            if ($user){
                                $results = $userModel->updateUser($user->id, ['password' => $data['password']]);
                            } else {
                                $results = ['error' => '用户不存在'];
                            }
                            if ($results['user']) {
                                sendTGMessage($user->id, "您的密码已修改，请注意账户安全");
                                $results = '密码重置成功，请重新登录';
                            } else {
                                $results = '密码重置失败：' . $results['error'];
                            }
                        }
                    }
                }
            }
            View::assign('email', $email);
            View::assign('result', $results);
            View::assign('code', $code);
            View::assign('sitekey', Config::get('apiinfo.cloudflareTurnstile.non-interactive.sitekey'));
            return view();
        }
    }

    public function logout()
    {
        // 退出登录
        Session::delete('r_user');
        Session::delete('m_embyId');
        return redirect('/media/index/index');
    }

    public function userconfig()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        $userModel = new UserModel();
        $user = $userModel->where('id', Session::get('r_user')->id)->find();
        if(!$user){
            return redirect('/media/user/login');
        } else {
            $user->password = '';
        }
        $userInfoArray = json_decode(json_encode($user['userInfo']), true);
        if (isset($userInfoArray['banEmail']) && ($userInfoArray['banEmail'] == 1 || $userInfoArray['banEmail'] == "1")) {
            View::assign('emailNotification', false);
        } else {
            View::assign('emailNotification', true);
        }
        View::assign('userInfo', $userInfoArray);


        $telegramModel = new TelegramModel();
        $telegramUser = $telegramModel->where('userId', Session::get('r_user')->id)->find();
        if ($telegramUser) {
            $tgUserInfoArray = json_decode(json_encode($telegramUser['userInfo']), true);

            if (isset($tgUserInfoArray['notification']) && ($tgUserInfoArray['notification'] == 1 || $tgUserInfoArray['notification'] == "1")) {
                View::assign('tgNotification', true);
            } else {
                View::assign('tgNotification', false);
            }

            // 获取tg用户信息
            $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
            $tgUser = $telegram->getChat(['chat_id' => $telegramUser['telegramId']]);
            if ($tgUser) {
                $tgUserInfoArray['tgUser'] = $tgUser;
            } else {
                $tgUserInfoArray['tgUser'] = null;
            }

            View::assign('tgUser', $tgUser);
        } else {
            $tgUserInfoArray = [];
            View::assign('tgNotification', false);
            View::assign('tgUser', null);
        }
        return view();
    }

    public function request()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        $page = input('page', 1, 'intval');
        $pagesize = input('pagesize', 10, 'intval');
        $requestModel = new RequestModel();
        $requestModel = $requestModel
            ->where('requestUserId', Session::get('r_user')->id)
            ->order('id', 'desc');
        $pageCount = ceil($requestModel->count() / $pagesize);
        $requestsList = $requestModel
            ->page($page, $pagesize)
            ->select();
        View::assign('page', $page);
        View::assign('pageCount', $pageCount);
        View::assign('requestsList', $requestsList);
        return view();
    }

    public function newRequest()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isGet()) {
            return view();
        } else if (Request::isPost()) {
            $data = Request::post();
            $requestModel = new RequestModel();
            $message[] = [
                'role' => 'user',
                'time' => date('Y-m-d H:i:s'),
                'content' => $data['content'],
            ];
            $requestModel->save([
                'type' => 1,
                'requestUserId' => Session::get('r_user')->id,
                'message' => json_encode($message),
                'requestInfo' => json_encode([
                    'title' => $data['title'],
                ]),
            ]);

            sendTGMessage(Session::get('r_user')->id, "您提交标题为 <strong>" . $data['title'] . "</strong> 的工单已经被记录，请耐心等待管理员处理");
            sendTGMessageToGroup("用户提交了标题为 <strong>" . $data['title'] . "</strong> 的工单，请及时处理");

            return json(['code' => 200, 'message' => '请求已提交']);
        }
    }

    public function requestDetail()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        $data = Request::get();
        $requestModel = new RequestModel();
        $request = $requestModel->where('id', $data['id'])->find();

        if (!$request || $request->requestUserId != Session::get('r_user')->id) {
            return redirect('/media/user/request');
        }

        $request['message'] = json_decode($request['message'], true);
        View::assign('request', $request);
        return view();
    }

    public function requestAddReply()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            if ($data['content'] == '') {
                return json(['code' => 400, 'message' => '回复内容不能为空']);
            }
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();

            if (!$request || $request->requestUserId != Session::get('r_user')->id) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            $message = json_decode($request['message'], true);
            if ($request->type == -1) {
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '用户重新开启工单',
                ];
            }
            $message[] = [
                'role' => 'user',
                'time' => date('Y-m-d H:i:s'),
                'content' => $data['content'],
            ];
            $request->message = json_encode($message);
            $request->type = 1;
            $request->save();
            if ($request->replyUserId) {
                $user = Session::get('r_user');
                $requestInfoArray = json_decode(json_encode($request['requestInfo']), true);
                sendTGMessage($request->replyUserId, "用户 <strong>". ($user->nickNamw??$user->userName)   . "(#" . $user->id . ")" ."</strong> 回复了标题为 <strong>" . $requestInfoArray['title'] . "</strong> 的工单，请及时处理");
            }
            return json(['code' => 200, 'message' => '回复已提交', 'messageRecord' => json_encode($message)]);
        }
    }

    public function requestClose()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();

            if (!$request || $request->requestUserId != Session::get('r_user')->id) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            if ($request->type != -1) {
                $message = json_decode($request['message'], true);
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '用户手动关闭工单',
                ];
                $request->message = json_encode($message);
                $request->type = -1;
                $request->save();
                return json(['code' => 200, 'message' => '工单已关闭', 'messageRecord' => json_encode($message)]);
            } else {
                return json(['code' => 400, 'message' => '工单已关闭']);
            }
        }
    }

    public function createNewEmbyUser()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $embyName = $data['userName'];
            $embyPassword = $data['password'];
        }
    }

    public function sendVerifyCode()
    {
        $data = Request::post();
        $email = $data['email'];
        $action = $data['action'];
        // 判断邮箱是否合法
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json(['code' => 400, 'message' => '邮箱格式不正确']);
        }
        $code = rand(100000, 999999);
        $cacheKey = 'verifyCode_' . $action . '_' . $email;
        if (Cache::get($cacheKey)) {
            return json(['code' => 400, 'message' => '验证码未过期，请勿重复发送']);
        }
        Cache::set($cacheKey, $code, 300);

        $SiteUrl = "https://randallanjie.com/media";

        $sysConfigModel = new SysConfigModel();
        $verifyCodeTemplate = $sysConfigModel->where('key', 'verifyCodeTemplate')->find();

        if ($verifyCodeTemplate) {
            $verifyCodeTemplate = $verifyCodeTemplate['value'];
        } else {
            $verifyCodeTemplate = '您的验证码是：{code}';
        }

        $verifyCodeTemplate = str_replace('{Code}', $code, $verifyCodeTemplate);
        $verifyCodeTemplate = str_replace('{Email}', $email, $verifyCodeTemplate);
        $verifyCodeTemplate = str_replace('{SiteUrl}', $SiteUrl, $verifyCodeTemplate);

        sendEmailForce($email, '【' . $code . '】算艺轩验证码', $verifyCodeTemplate);
        return json(['code' => 200, 'message' => '验证码已发送']);
    }

    public function sign()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $token = $data['token']??'';
            $userModel = new UserModel();
            $user = $userModel->where('id', Session::get('r_user')->id)->find();
            if ($user) {
                $userInfoArray = json_decode(json_encode($user['userInfo']), true);

                $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
                    $_SERVER['HTTP_X_REAL_IP'] ??
                    $_SERVER['HTTP_CF_CONNECTING_IP'] ??
                    Request::ip();

                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $realIp = trim($ipList[0]);
                }

                $SECRET_KEY = Config::get('apiinfo.cloudflareTurnstile.invisible.secret');

                $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

                $data = [
                    'secret' => $SECRET_KEY,
                    'response' => $token,
                    'remoteip' => $realIp
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                $output = curl_exec($ch);
                curl_close($ch);

                $output = json_decode($output, true);
                if (!$output['success']) {
                    return json(['code' => 400, 'message' => '签到失败，如果今天未签到请重新登录后重试']);
                }

                if (isset($userInfoArray['loginIps']) && ((isset($userInfoArray['lastSignTime']) && in_array($realIp, $userInfoArray['loginIps']) && $userInfoArray['lastSignTime'] != date('Y-m-d')) || (!isset($userInfoArray['lastSignTime']) && in_array($realIp, $userInfoArray['loginIps'])))){
                    $userInfoArray['lastSignTime'] = date('Y-m-d');
                    $user->userInfo = json_encode($userInfoArray);
                    $score = mt_rand(10, 30) / 100;
                    $user->rCoin = $user->rCoin + $score;
                    $user->save();

                    $user = $userModel->where('id', Session::get('r_user')->id)->find();
                    Session::set('r_user', $user);

                    $financeRecordModel = new FinanceRecordModel();
                    $financeRecordModel->save([
                        'userId' => Session::get('r_user')->id,
                        'action' => 4,
                        'count' => $score,
                        'recordInfo' => [
                            'message' => '签到获取' . $score . 'R币',
                        ]
                    ]);
                    sendTGMessage(Session::get('r_user')->id,"签到成功！今日签到获取" . $score . "R币");

                    return json(['code' => 200, 'message' => '签到成功']);
                } else {
                    return json(['code' => 400, 'message' => '签到失败，如果今天未签到请重新登录后重试']);
                }
            } else {
                return json(['code' => 400, 'message' => '签到失败，如果今天未签到请重新登录后重试']);
            }
        }
    }

    public function tgUnbind()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $telegramModel = new TelegramModel();
            $telegramUser = $telegramModel->where('userId', Session::get('r_user')->id)->find();
            if ($telegramUser) {
                $telegramUser->delete();
                return json(['code' => 200, 'message' => '解绑成功']);
            } else {
                return json(['code' => 400, 'message' => '解绑失败']);
            }
        }
    }

    public function getTGBindCode()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $telegramModel = new TelegramModel();
            $telegramUser = $telegramModel->where('userId', Session::get('r_user')->id)->find();
            if ($telegramUser) {
                return json(['code' => 400, 'message' => '您已经绑定了Telegram']);
            } else {
                $code = rand(100000, 999999);
                $cacheKey = 'tgBindKey_' . $code;
                Cache::set($cacheKey, Session::get('r_user')->id, 120);
                return json(['code' => 200, 'message' => '获取成功', 'data' => $code]);
            }
        }
    }

    public function setEmailNotification()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $userModel = new UserModel();
            $user = $userModel->where('id', Session::get('r_user')->id)->find();
            if ($user) {
                $userInfoArray = json_decode(json_encode($user['userInfo']), true);
                if ($data['emailNotification'] == 'true' || $data['emailNotification'] == 1 || $data['emailNotification'] == "1") {
                    $userInfoArray['banEmail'] = 0;
                } else {
                    $userInfoArray['banEmail'] = 1;
                }
                $user->userInfo = json_encode($userInfoArray);
                $user->save();
                return json(['code' => 200, 'message' => '设置成功']);
            } else {
                return json(['code' => 400, 'message' => '设置失败']);
            }
        }
    }

    public function setTGNotification()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $telegramModel = new TelegramModel();
            $telegramUser = $telegramModel->where('userId', Session::get('r_user')->id)->find();
            if ($telegramUser) {
                $userInfoArray = json_decode(json_encode($telegramUser['userInfo']), true);
                if ($data['tgNotification'] == 'true' || $data['tgNotification'] == 1 || $data['tgNotification'] == "1") {
                    $userInfoArray['notification'] = 1;
                } else {
                    $userInfoArray['notification'] = 0;
                }
                $telegramUser->userInfo = json_encode($userInfoArray);
                $telegramUser->save();
                return json(['code' => 200, 'message' => '设置成功']);
            } else {
                return json(['code' => 400, 'message' => '设置失败']);
            }
        }
    }

    public function seek()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        return view();
    }

    public function comment()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        return view();
    }

    public function getComments()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $mediaId = $data['mediaId']??0;
            $page = $data['page']??1;
            $pagesize = $data['pagesize']??10;
            if ($mediaId == 0) {
                return json(['code' => 400, 'message' => '参数错误']);
            }
            $commentModel = new MediaCommentModel();
            $commentList = $commentModel
                ->where('mediaId', $mediaId)
                ->order('id', 'desc')
                ->page($page, $pagesize)
                ->select();
            foreach ($commentList as $key => $comment) {
                if ($comment['userId'] && $comment['userId'] != 0) {
                    $userModel = new UserModel();
                    $user = $userModel->where('id', $comment['userId'])->find();
                    $commentList[$key]['username'] = $user->nickName??$user->userName;
                }
                if ($comment['mentions'] && $comment['mentions'] != '[]') {
                    $mentions = json_decode($comment['mentions'], true);
                    $mentionsUser = [];
                    foreach ($mentions as $mention) {
                        $userModel = new UserModel();
                        $user = $userModel->where('id', $mention)->find();
                        if ($user) {
                            $mentionsUser[] = [
                                'id' => $user->id,
                                'username' => $user->nickName??$user->userName,
                            ];

                        }
                    }
                    $commentList[$key]['mentions'] = $mentionsUser;
                }
                if ($comment['quotedComment'] && $comment['quotedComment'] != 0) {
                    $quotedComment = $commentModel->where('id', $comment['quotedComment'])->find();
                    if ($quotedComment) {
                        $user = $userModel->where('id', $quotedComment['userId'])->find();
                        $quotedComment['username'] = $user->nickName ?? $user->userName;
                        $commentList[$key]['quotedComment'] = json_decode($quotedComment, true);
                        if ($quotedComment['mentions'] && $quotedComment['mentions'] != '[]') {
                            $quotedComment['mentions'] = json_decode($quotedComment['mentions'], true);
                            $mentionsUser = [];
                            foreach ($quotedComment['mentions'] as $mention) {
                                $userModel = new UserModel();
                                $user = $userModel->where('id', $mention)->find();
                                if ($user) {
                                    $mentionsUser[] = [
                                        'id' => $user->id,
                                        'username' => $user->userName,
                                    ];
                                }
                            }
                            $quotedComment['mentions'] = $mentionsUser;
                        }
                        $comment['quotedComment'] = json_decode($quotedComment, true);
                    }
                }
            }
            return json(['code' => 200, 'message' => '获取成功', 'data' => $commentList]);
        }
    }

    public function getOneComment()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $id = $data['id'] ?? 0;
            if ($id == 0) {
                return json(['code' => 400, 'message' => '参数错误']);
            }
            $commentModel = new MediaCommentModel();
            $comment = $commentModel->where('id', $id)->find();
            if ($comment) {
                if ($comment['userId'] && $comment['userId'] != 0) {
                    $userModel = new UserModel();
                    $user = $userModel->where('id', $comment['userId'])->find();
                    $comment['username'] = $user->userName;
                }
                if ($comment['mentions'] && $comment['mentions'] != '[]') {
                    $mentions = json_decode($comment['mentions'], true);
                    $mentionsUser = [];
                    foreach ($mentions as $mention) {
                        $userModel = new UserModel();
                        $user = $userModel->where('id', $mention)->find();
                        if ($user) {
                            $mentionsUser[] = [
                                'id' => $user->id,
                                'username' => $user->userName,
                            ];

                        }
                    }
                    $comment['mentions'] = $mentionsUser;
                }
                return json(['code' => 200, 'message' => '获取成功', 'data' => $comment]);
            } else {
                return json(['code' => 400, 'message' => '获取失败']);
            }
        }
    }


    public function commentDetail()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isGet()) {
            $mediaId = input('id', 0, 'intval');
            View::assign('mediaId', $mediaId);
            View::assign('comment', true);
            return view();
        }
    }

    public function addComment()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            if (isset($data['mediaId']) && isset($data['comment']) && $data['mediaId'] != '' && $data['comment'] != '') {
                $commentModel = new MediaCommentModel();
                $userModel = new UserModel();
                $user = $userModel->where('id', Session::get('r_user')->id)->find();
                if (!$user) {
                    return json(['code' => 400, 'message' => '用户不存在']);
                } else if ($user->authority < 0) {
                    return json(['code' => 400, 'message' => '用户已被封禁']);
                } else if ($user->rCoin < 0.01) {
                    return json(['code' => 400, 'message' => 'R币不足']);
                }
                $data['comment'] .= ' ';
                $pattern = '/@([a-zA-Z0-9_]+)/';
                preg_match_all($pattern, $data['comment'], $matches);
                $mentions = [];
                if (count($matches[1]) > 0) {
                    foreach ($matches[1] as $match) {
                        $user = $userModel->where('nickName', $match)->find();
                        if ($user) {
                            $data['comment'] = str_replace('@' . $match, '@#' . $user->id . '# ', $data['comment']);
                            if (!in_array($user->id, $mentions)) {
                                $mentions[] = $user->id;
                            }
                        }
                    }
                }
                $commentModel->save([
                    'userId' => Session::get('r_user')->id,
                    'mediaId' => $data['mediaId'],
                    'rating' => $data['rate'],
                    'comment' => $data['comment'],
                    'mentions' => json_encode($mentions),
                    'quotedComment' => $data['replyTo']??0,
                ]);

                // 获取$data['comment']的字数
                $commentLength = mb_strlen($data['comment'], 'utf-8');

                // 每100个字0.01R币，不够100向上取整
                $rCoin = ceil($commentLength / 100) * 0.01;

                $user = $userModel->where('id', Session::get('r_user')->id)->find();
                $user->rCoin = $user->rCoin - 0.01 + $rCoin;
                $user->save();

                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => Session::get('r_user')->id,
                    'action' => 3,
                    'count' => 0.01,
                    'recordInfo' => [
                        'message' => '影视评论消耗0.01R币',
                    ]
                ]);
                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => Session::get('r_user')->id,
                    'action' => 8,
                    'count' => $rCoin,
                    'recordInfo' => [
                        'message' => '影视评论奖励' . $rCoin . 'R币',
                    ]
                ]);

                return json(['code' => 200, 'message' => '评论成功']);
            } else {
                return json(['code' => 400, 'message' => '评论失败']);
            }
        }
    }
}
