<?php

namespace app\media\controller;

use app\BaseController;
use app\media\model\EmbyUserModel as EmbyUserModel;
use app\media\model\ExchangeCodeModel;
use app\media\model\FinanceRecordModel;
use app\media\model\PayRecordModel;
use app\media\model\SysConfigModel as SysConfigModel;
use mailer\Mailer;
use Symfony\Component\VarDumper\Cloner\Data;
use think\facade\Request;
use think\facade\Session;
use app\media\model\UserModel as UserModel;
use app\media\validate\Login as LoginValidate;
use app\media\validate\Register as RegisterValidate;
use think\facade\View;
use think\facade\Config;
use think\facade\Cache;


class Server extends BaseController
{
    public function index()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        return view();
    }

    public function changeTo()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Session::get('r_user')->id == 1) {
            $data = Request::get();
            if (isset($data['userId'])) {
                $userModel = new UserModel();
                $user = $userModel->where('id', $data['userId'])->find();
                if ($user) {
                    Session::set('r_user', $user);
                    return redirect('/media/user/index');
                }
            }
        }
    }

    public function account()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        View::assign('lifetimecost', 365);
        View::assign('lifetimeauthority', 2);
        $userModel = new UserModel();
        $userFromDatabase = $userModel->where('id', Session::get('r_user')->id)->find();
        $userFromDatabase['password'] = null;
        $embyUserModel = new EmbyUserModel();
        $embyUserFromDatabase = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        $userInfoArray = json_decode(json_encode($embyUserFromDatabase->userInfo), true);
        if (isset($userInfoArray['autoRenew'])) {
            $autoRenew = $userInfoArray['autoRenew'];
        } else {
            $autoRenew = 0;
        }
        if ($embyUserFromDatabase && $embyUserFromDatabase['embyId'] != null) {
            $embyId = $embyUserFromDatabase['embyId'];
            $activateTo = $embyUserFromDatabase['activateTo'];
            $url = Config::get('media.urlBase') . 'Users/' . $embyId . '?api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json'
            ]);
            $embyUserFromEmby = json_decode(curl_exec($ch));
        } else {
            $embyUserFromEmby = null;
            $activateTo = null;
        }
        View::assign('userFromDatabase', $userFromDatabase);
        View::assign('embyUserFromDatabase', $embyUserFromDatabase);
        View::assign('embyUserFromEmby', $embyUserFromEmby);
        View::assign('autoRenew', $autoRenew);
        View::assign('activateTo', $activateTo);
        return view();
    }

    public function changePassword()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            if (isset($data['password']) && $data['password'] != '') {
                $embyUserModel = new EmbyUserModel();
                $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
                if (isset($user->embyId)) {
                    $url = Config::get('media.urlBase') . 'Users/' . $user->embyId . '/Password?api_key=' . Config::get('media.apiKey');
                    $data = [
                        'Id' => $user->embyId,
                        'NewPw' => $data['password'],
//                        'ResetPassword' => true
                    ];
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: application/json',
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204) {
                        return json(['code' => 200, 'message' => '修改成功']);
                    } else {
                        return json(['code' => 400, 'message' => $response]);
                    }
                } else {
                    return json(['code' => 400, 'message' => '请先创建Emby账号']);
                }
            } else {
                return json(['code' => 400, 'message' => '密码不能为空']);
            }
        } else if (Request::isGet()) {
            return view();
        }
    }

    public function create()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        $embyUserModel = new EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        if (isset($user->embyId)) {
            return redirect((string) url('/media/server/account'));
        }
        if (Request::isPost()) {
            $data = Request::post();
            $embyUserName = $data['embyUserName'];
            $url = Config::get('media.urlBase') . 'Users/New?api_key=' . Config::get('media.apiKey');
            $data = [
                'Name' => $embyUserName,
                'CopyFromUserId' => Config::get('media.UserTemplateId'),
                'UserCopyOptions' => [
                    'UserPolicy',
                    'UserConfiguration'
                ]
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            // 如果是400错误，说明用户名已存在
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 400) {
                return json(['code' => 400, 'message' => '用户名已存在']);
            } else {
                $embyUserId = json_decode($response, true)['Id'];
                $embyUserModel = new EmbyUserModel();
                $embyUserModel->save([
                    'userId' => Session::get('r_user')->id,
                    'embyId' => $embyUserId,
                ]);
                $embyUser = $embyUserId;
                Session::set('m_embyId', $embyUserId);

                return json(['code' => 200, 'message' => '创建成功']);
            }
        } else if (Request::isGet()) {
            return view();
        }
    }

    public function servers()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }


        if (Cache::get('serverList')) {
            View::assign('serverList', Cache::get('serverList'));
            return view();
        }

        $serverList = [];
        $lineList = Config::get('media.lineList');
        foreach ($lineList as $line) {
            $url = $line['url'] . '/emby/System/Ping?api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: */*'
            ]);
            $response = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
                $status = 1;
            } else {
                $status = 0;
            }
            $serverList[] = [
                'name' => $line['name'],
                'url' => $line['url'],
                'status' => $status
            ];
        }

        // 将serverList保存到缓存中
        Cache::set('serverList', $serverList, 600);

        View::assign('serverList', $serverList);

        return view();
    }

    public function session()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        $embyUserModel = new EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        if (isset($user->embyId)) {
//            $url = Config::get('media.urlBase') . 'Sessions?ControllableByUserId=' . $user->embyId . '&api_key=' . Config::get('media.apiKey');
            $url = Config::get('media.urlBase') . 'Sessions?api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json'
            ]);
            $response = curl_exec($ch);
            $allSessionList = json_decode($response, true);
//            echo $response;
//            die();
            $sessionList = [];
            foreach ($allSessionList as $session) {
                if (isset($session['UserId']) && $session['UserId'] == $user->embyId) {
                    $sessionList[] = $session;
                }
            }

            View::assign('sessionList', $sessionList);
        } else {
            $sessionList = null;
        }

        View::assign('sessionList', $sessionList);
        return view();
    }

    public function getItemsByIds()
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        if (Request::isPost()) {
            $data = Request::post();
            $ids = $data['ids'];
            $url = Config::get('media.urlBase') . 'Items?Ids=' . join(',', $ids) . '&EnableImages=true&&api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json'
            ]);
            $response = curl_exec($ch);
            return json(['code' => 200, 'message' => '获取成功', 'data' => json_decode($response, true)]);
        }
    }

    public function viewList()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        $embyUserModel = new EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        if (isset($user->embyId)) {
            $url = Config::get('media.urlBase') . 'Users/' . $user->embyId . '/Views?IncludeExternalContent=true&api_key=' . Config::get('media.apiKey');
//            $url = Config::get('media.urlBase') . 'Shows/NextUp?UserId=' . $user->embyId . '&api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json'
            ]);
            $response = curl_exec($ch);
            $viewList = json_decode($response, true);
            View::assign('viewList', $viewList);
        } else {
            $viewList = null;
        }
        echo $response;
//        echo json_encode($viewList);
        die();
        View::assign('viewList', $viewList);
        return view();
    }

    public function setAutoRenew()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            $userInfoArray = json_decode(json_encode($embyUser->userInfo), true);
            $userInfoArray['autoRenew'] = $data['autoRenew'];
            $embyUser->userInfo = $userInfoArray;
            $embyUser->save();

            $financeRecordModel = new FinanceRecordModel();
            $financeRecordModel->save([
                'userId' => Session::get('r_user')->id,
                'action' => 5,
                'count' => $data['autoRenew'],
                'recordInfo' => [
                    'message' => '设置自动续期Emby账号状态为' . ($data['autoRenew']==1?'开启':'关闭')
                ]
            ]);

            sendTGMessage(Session::get('r_user')->id, '您的Emby账号自动续期状态已设置为 <strong>' . ($data['autoRenew']==1?'开启':'关闭') . '</strong>');

            return json(['code' => 200, 'message' => '设置成功']);
        }
    }

    public function activateEmbyUserByBalance()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            $embyUserId = $embyUser->embyId;
            $userModel = new UserModel();
            $user = $userModel->where('id', Session::get('r_user')->id)->find();
            if ($user->rCoin >= 1) {
                $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Policy?api_key=' . Config::get('media.apiKey');
                $data = [
                    "IsAdministrator" => false,
                    "IsHidden" => true,
                    "IsHiddenRemotely" => true,
                    "IsHiddenFromUnusedDevices" => true,
                    "IsDisabled" => false,
                    "LockedOutDate" => 0,
                    "AllowTagOrRating" => false,
                    "BlockedTags" => [],
                    "IsTagBlockingModeInclusive" => false,
                    "IncludeTags" => [],
                    "EnableUserPreferenceAccess" => true,
                    "AccessSchedules" => [],
                    "BlockUnratedItems" => [],
                    "EnableRemoteControlOfOtherUsers" => false,
                    "EnableSharedDeviceControl" => false,
                    "EnableRemoteAccess" => true,
                    "EnableLiveTvManagement" => false,
                    "EnableLiveTvAccess" => true,
                    "EnableMediaPlayback" => true,
                    "EnableAudioPlaybackTranscoding" => false,
                    "EnableVideoPlaybackTranscoding" => false,
                    "EnablePlaybackRemuxing" => false,
                    "EnableContentDeletion" => false,
                    "RestrictedFeatures" => [
                        "notifications",
                        "trakt"
                    ],
                    "EnableContentDeletionFromFolders" => [],
                    "EnableContentDownloading" => false,
                    "EnableSubtitleDownloading" => false,
                    "EnableSubtitleManagement" => false,
                    "EnableSyncTranscoding" => false,
                    "EnableMediaConversion" => false,
                    "EnabledChannels" => [],
                    "EnableAllChannels" => true,
                    "EnabledFolders" => [],
                    "EnableAllFolders" => true,
                    "InvalidLoginAttemptCount" => 0,
                    "EnablePublicSharing" => false,
                    "RemoteClientBitrateLimit" => 0,
                    "AuthenticationProviderId" => "Emby.Server.Implementations.Library.DefaultAuthenticationProvider",
                    "ExcludedSubFolders" => [],
                    "SimultaneousStreamLimit" => 0,
                    "EnabledDevices" => [],
                    "EnableAllDevices" => true,
                    "AllowCameraUpload" => false,
                    "AllowSharingPersonalItems" => false
                ];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: */*',
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $response = curl_exec($ch);
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204) {
                    $activateTo = date('Y-m-d H:i:s', time() + 86400);
                    $embyUser->activateTo = $activateTo;
                    $embyUser->save();
                    $user->rCoin = $user->rCoin - 1;
                    $user->save();
                    $financeRecordModel = new FinanceRecordModel();
                    $financeRecordModel->save([
                        'userId' => Session::get('r_user')->id,
                        'action' => 3,
                        'count' => 1,
                        'recordInfo' => [
                            'message' => '使用余额激活Emby账号'
                        ]
                    ]);

                    sendTGMessage(Session::get('r_user')->id, '您的Emby账号已激活');

                    // 更新Session
                    $r_user = Session::get('r_user');
                    $r_user->rCoin = $user->rCoin;
                    Session::set('r_user', $r_user);
                    return json([
                        'code' => 200,
                        'message' => '激活成功'
                    ]);
                } else {
                    return json([
                        'code' => 400,
                        'message' => $response
                    ]);
                }
            } else {
                return json([
                    'code' => 400,
                    'message' => '余额不足'
                ]);
            }
        }
    }


    public function activateEmbyUserByCode()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $code = $data['code'];
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            $embyUserId = $embyUser->embyId;
            $exchangeCodeModel = new ExchangeCodeModel();
            $exchangeCode = $exchangeCodeModel->where('code', $code)->find();
            if ($exchangeCode && $exchangeCode['type'] == 0 && $exchangeCode['exchangeType'] == 1) {
                $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Policy?api_key=' . Config::get('media.apiKey');
                $data = [
                    "IsAdministrator" => false,
                    "IsHidden" => true,
                    "IsHiddenRemotely" => true,
                    "IsHiddenFromUnusedDevices" => true,
                    "IsDisabled" => false,
                    "LockedOutDate" => 0,
                    "AllowTagOrRating" => false,
                    "BlockedTags" => [],
                    "IsTagBlockingModeInclusive" => false,
                    "IncludeTags" => [],
                    "EnableUserPreferenceAccess" => true,
                    "AccessSchedules" => [],
                    "BlockUnratedItems" => [],
                    "EnableRemoteControlOfOtherUsers" => false,
                    "EnableSharedDeviceControl" => false,
                    "EnableRemoteAccess" => true,
                    "EnableLiveTvManagement" => false,
                    "EnableLiveTvAccess" => true,
                    "EnableMediaPlayback" => true,
                    "EnableAudioPlaybackTranscoding" => false,
                    "EnableVideoPlaybackTranscoding" => false,
                    "EnablePlaybackRemuxing" => false,
                    "EnableContentDeletion" => false,
                    "RestrictedFeatures" => [
                        "notifications",
                        "trakt"
                    ],
                    "EnableContentDeletionFromFolders" => [],
                    "EnableContentDownloading" => false,
                    "EnableSubtitleDownloading" => false,
                    "EnableSubtitleManagement" => false,
                    "EnableSyncTranscoding" => false,
                    "EnableMediaConversion" => false,
                    "EnabledChannels" => [],
                    "EnableAllChannels" => true,
                    "EnabledFolders" => [],
                    "EnableAllFolders" => true,
                    "InvalidLoginAttemptCount" => 0,
                    "EnablePublicSharing" => false,
                    "RemoteClientBitrateLimit" => 0,
                    "AuthenticationProviderId" => "Emby.Server.Implementations.Library.DefaultAuthenticationProvider",
                    "ExcludedSubFolders" => [],
                    "SimultaneousStreamLimit" => 0,
                    "EnabledDevices" => [],
                    "EnableAllDevices" => true,
                    "AllowCameraUpload" => false,
                    "AllowSharingPersonalItems" => false
                ];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: */*',
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $response = curl_exec($ch);
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204) {
                    $activateTo = date('Y-m-d H:i:s', time() + 86400);
                    $embyUser->activateTo = $activateTo;
                    $embyUser->save();
                    $exchangeCode->type = 1;
                    $exchangeCode->usedByUserId = Session::get('r_user')->id;
                    $exchangeCode['exchangeDate'] = date('Y-m-d H:i:s', time());
                    $exchangeCode->save();
                    $financeRecordModel = new FinanceRecordModel();
                    $financeRecordModel->save([
                        'userId' => Session::get('r_user')->id,
                        'action' => 2,
                        'count' => $code,
                        'recordInfo' => [
                            'message' => '使用兑换码' . $code . '激活Emby账号'
                        ]
                    ]);
                    sendTGMessage(Session::get('r_user')->id, '您的Emby账号已激活');
                    return json([
                        'code' => 200,
                        'message' => '激活成功'
                    ]);
                }
            } else {
                return json([
                    'code' => 400,
                    'message' => '无效的兑换码'
                ]);
            }
        }
    }

    public function continueSubscribeEmbyUserByBalance()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $userModel = new UserModel();
            $user = $userModel->where('id', Session::get('r_user')->id)->find();
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            // 如果用户余额大于等于10
            if ($user->rCoin >= 10) {
                $activateTo = $embyUser['activateTo'];
                if ($activateTo == null) {
                    return json([
                        'code' => 400,
                        'message' => 'LifeTime用户无需续期'
                    ]);
                }
                if (strtotime($activateTo) > time()) {
                    $activateTo = date('Y-m-d H:i:s', strtotime($activateTo) + 2592000);
                } else {
                    $activateTo = date('Y-m-d H:i:s', time() + 2592000);
                }
                $embyUser->activateTo = $activateTo;
                $embyUser->save();
                $user->rCoin = $user->rCoin - 10;
                $user->save();
                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => Session::get('r_user')->id,
                    'action' => 3,
                    'count' => 10,
                    'recordInfo' => [
                        'message' => '使用余额续期Emby账号'
                    ]
                ]);
                sendTGMessage(Session::get('r_user')->id, '您的Emby账号已续期至 <strong>' . $activateTo . '</strong>');
                // 更新Session
                $r_user = Session::get('r_user');
                $r_user->rCoin = $user->rCoin;
                Session::set('r_user', $r_user);
                return json([
                    'code' => 200,
                    'message' => '续期成功'
                ]);
            } else {
                return json([
                    'code' => 400,
                    'message' => '余额不足'
                ]);
            }
        }
    }

    public function continueSubscribeEmbyUserByCode()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $code = $data['code'];
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            $embyUserId = $embyUser->embyId;
            $exchangeCodeModel = new ExchangeCodeModel();
            $exchangeCode = $exchangeCodeModel->where('code', $code)->find();
            if ($exchangeCode && $exchangeCode['type'] == 0 && ($exchangeCode['exchangeType'] == 2 || $exchangeCode['exchangeType'] == 3)) {
                $activateTo = $embyUser['activateTo'];
                if ($activateTo == null) {
                    return json([
                        'code' => 400,
                        'message' => 'LifeTime用户无需续期'
                    ]);
                }
                $seconds = $exchangeCode['exchangeType']==2?(86400*$exchangeCode['exchangeCount']):(2592000*$exchangeCode['exchangeCount']);
                if (strtotime($activateTo) > time()) {
                    $activateTo = date('Y-m-d H:i:s', strtotime($activateTo) + $seconds);
                } else {
                    $activateTo = date('Y-m-d H:i:s', time() + $seconds);
                }
                $embyUser->activateTo = $activateTo;
                $embyUser->save();
                $exchangeCode->type = 1;
                $exchangeCode->usedByUserId = Session::get('r_user')->id;
                $exchangeCode['exchangeDate'] = date('Y-m-d H:i:s', time());
                $exchangeCode->save();
                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => Session::get('r_user')->id,
                    'action' => 2,
                    'count' => $code,
                    'recordInfo' => [
                        'message' => '使用兑换码' . $code . '续期Emby账号'
                    ]
                ]);
                sendTGMessage(Session::get('r_user')->id, '您的Emby账号已续期至 <strong>' . $activateTo . '</strong>');

                return json([
                    'code' => 200,
                    'message' => '续期成功'
                ]);
            } else {
                return json([
                    'code' => 400,
                    'message' => '无效的兑换码'
                ]);
            }
        }
    }

    public function continueSubscribeEmbyUserToLifetimeByRCoin()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $userModel = new UserModel();
            $user = $userModel->where('id', Session::get('r_user')->id)->find();
            if ($user->authority < 2) {
                return json([
                    'code' => 400,
                    'message' => '您没有权限'
                ]);
            }
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            if ($embyUser->activateTo == null) {
                return json([
                    'code' => 400,
                    'message' => 'LifeTime用户无需续期'
                ]);
            }
            if ($embyUser->activateTo < date('Y-m-d H:i:s', time())) {
                return json([
                    'code' => 400,
                    'message' => '用户已过期，请先激活至未过期'
                ]);
            }

            if ($user->rCoin >= 365) {
                $embyUser->activateTo = null;
                $embyUser->save();
                $user->rCoin = $user->rCoin - 365;
                $user->save();
                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => Session::get('r_user')->id,
                    'action' => 3,
                    'count' => 365,
                    'recordInfo' => [
                        'message' => '使用余额续期Emby账号至终身'
                    ]
                ]);
                sendTGMessage(Session::get('r_user')->id, '您的Emby账号已续期至终身');
                // 更新Session
                $r_user = Session::get('r_user');
                $r_user->rCoin = $user->rCoin;
                Session::set('r_user', $r_user);
                return json([
                    'code' => 200,
                    'message' => '续期成功'
                ]);
            } else {
                return json([
                    'code' => 400,
                    'message' => '余额不足'
                ]);
            }
        }
    }

    public function exchangeCode()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            $code = $data['code'];
            $exchangeCodeModel = new ExchangeCodeModel();
            $exchangeCode = $exchangeCodeModel->where('code', $code)->find();
            if ($exchangeCode && $exchangeCode['type'] == 0 && $exchangeCode['exchangeType'] == 4) {
                $exchangeCode->type = 1;
                $exchangeCode->usedByUserId = Session::get('r_user')->id;
                $exchangeCount = $exchangeCode['exchangeCount'];
                $exchangeCode['exchangeDate'] = date('Y-m-d H:i:s', time());
                $exchangeCode->save();

                $userModel = new UserModel();
                $user = $userModel->where('id', Session::get('r_user')->id)->find();
                $rCoin = $user->rCoin + $exchangeCount;
                // $rCoin转换为double类型数据存入数据库
                $rCoin = sprintf("%.2f", $rCoin);
                $user->rCoin = $rCoin;
                $user->save();

                // 添加充值记录
                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => Session::get('r_user')->id,
                    'action' => 2,
                    'count' => $code,
                    'recordInfo' => [
                        'message' => '使用兑换码' . $code . '充值' . $exchangeCount . 'R币'
                    ]
                ]);

                sendTGMessage(Session::get('r_user')->id, '您已经成功兑换了 <strong>' . $exchangeCount . '</strong> R币，当前余额为 <strong>' . $rCoin . '</strong>');

                // 更新Session
                $r_user = Session::get('r_user');
                $r_user->rCoin = $user->rCoin;
                Session::set('r_user', $r_user);

                return json([
                    'code' => 200,
                    'message' => '兑换成功',
                    'rCoin' => $rCoin
                ]);
            } else {
                return json([
                    'code' => 400,
                    'message' => '无效的兑换码，请检查兑换码和其类型是否正确，或者兑换码是否已被使用'
                ]);
            }
        }
    }


    public function crontab()
    {
        // 获取get参数
        $data = Request::get();
        // 判断是否有参数
        if (isset($data['key']) && $data['key'] == Config::get('media.crontabKey')) {
            $flag = true;
            // 获取所有用户
            $embyUserModel = new EmbyUserModel();
            $embyUserList = $embyUserModel
//                ->where('userId', '=', 37)
                ->select();
            // 找出$activateTo小于当前时间的用户
            foreach ($embyUserList as $embyUser) {
                if ($embyUser['activateTo'] != null && strtotime($embyUser['activateTo']) < time()) {
                    // 如果是24小时内到期的用户
                    if (strtotime($embyUser['activateTo']) + 86400 > time() && strtotime($embyUser['activateTo']) < time()) {
                        $userModel = new UserModel();
                        $user = $userModel->where('id', $embyUser['userId'])->find();
                        $email = $user['email'];
                        $userInfoArray = json_decode(json_encode($embyUser['userInfo']), true);
                        if (isset($userInfoArray['autoRenew']) && ($userInfoArray['autoRenew'] == "1" || $userInfoArray['autoRenew'] == 1) && $user['rCoin'] > 10) {
                            $user->rCoin = $user->rCoin - 10;
                            $user->save();

                            $activateTo = date('Y-m-d H:i:s', strtotime($embyUser['activateTo']) + 2592000);
                            $embyUser->activateTo = $activateTo;
                            $embyUser->save();

                            $financeRecordModel = new FinanceRecordModel();
                            $financeRecordModel->save([
                                'userId' => $embyUser['userId'],
                                'action' => 3,
                                'count' => 10,
                                'recordInfo' => [
                                    'message' => '使用余额续期Emby账号'
                                ]
                            ]);

                            // 发送邮件
                            $SiteUrl = "https://randallanjie.com/media";
                            $sysConfigModel = new SysConfigModel();
                            $mediaMaturityTemplate = $sysConfigModel->where('key', 'mediaMaturityTemplate')->find();
                            if ($mediaMaturityTemplate) {
                                $mediaMaturityTemplate = $mediaMaturityTemplate['value'];
                            } else {
                                $mediaMaturityTemplate = '您的Emby账号已自动续期。';
                            }
                            $mediaMaturityTemplate = str_replace('{Email}', $email, $mediaMaturityTemplate);
                            $mediaMaturityTemplate = str_replace('{SiteUrl}', $SiteUrl, $mediaMaturityTemplate);

                            sendTGMessage($embyUser['userId'], '您的Emby账号已自动续期，当前有效期至： <strong>' . $activateTo . '</strong>');
                            sendEmail($email, '影视站自动续期提醒 - 算艺轩', $mediaMaturityTemplate);

                        } else {
                            $embyUserId = $embyUser['embyId'];
                            $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Policy?api_key=' . Config::get('media.apiKey');
                            $data = [
                                'IsDisabled' => true
                            ];
                            $ch = curl_init($url);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'accept: */*',
                                'Content-Type: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                            $response = curl_exec($ch);
                            if (!(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204)) {
                                $flag = false;
                            }
                            // 发送邮件
                            $SiteUrl = "https://randallanjie.com/media";
                            $sysConfigModel = new SysConfigModel();
                            $mediaMaturityTemplate = $sysConfigModel->where('key', 'mediaMaturityTemplate')->find();
                            if ($mediaMaturityTemplate) {
                                $mediaMaturityTemplate = $mediaMaturityTemplate['value'];
                            } else {
                                $mediaMaturityTemplate = '您的Emby账号已到期，已经禁止使用。';
                            }
                            $mediaMaturityTemplate = str_replace('{Email}', $email, $mediaMaturityTemplate);
                            $mediaMaturityTemplate = str_replace('{SiteUrl}', $SiteUrl, $mediaMaturityTemplate);

                            sendTGMessage($embyUser['userId'], '您的Emby账号已到期，已经禁止使用。');
                            sendEmail($email, '影视站到期提醒 - 算艺轩', $mediaMaturityTemplate);
                        }
                    }
                } else if ($embyUser['activateTo'] != null && strtotime($embyUser['activateTo']) - 86400 < time() && strtotime($embyUser['activateTo']) > time()) {
                    if (isset($userInfoArray['autoRenew']) && ($userInfoArray['autoRenew'] == "1" || $userInfoArray['autoRenew'] == 1) && $user['rCoin'] > 10) {
                        $user->rCoin = $user->rCoin - 10;
                        $user->save();

                        $activateTo = date('Y-m-d H:i:s', strtotime($embyUser['activateTo']) + 2592000);
                        $embyUser->activateTo = $activateTo;
                        $embyUser->save();

                        $financeRecordModel = new FinanceRecordModel();
                        $financeRecordModel->save([
                            'userId' => $embyUser['userId'],
                            'action' => 3,
                            'count' => 10,
                            'recordInfo' => [
                                'message' => '使用余额续期Emby账号'
                            ]
                        ]);

                        // 发送邮件
                        $SiteUrl = "https://randallanjie.com/media";
                        $sysConfigModel = new SysConfigModel();
                        $mediaMaturityTemplate = $sysConfigModel->where('key', 'mediaMaturityTemplate')->find();
                        if ($mediaMaturityTemplate) {
                            $mediaMaturityTemplate = $mediaMaturityTemplate['value'];
                        } else {
                            $mediaMaturityTemplate = '您的Emby账号已自动续期。';
                        }
                        $mediaMaturityTemplate = str_replace('{Email}', $email, $mediaMaturityTemplate);
                        $mediaMaturityTemplate = str_replace('{SiteUrl}', $SiteUrl, $mediaMaturityTemplate);

                        sendTGMessage($embyUser['userId'], '您的Emby账号已自动续期，当前有效期至： <strong>' . $activateTo . '</strong>');
                        sendEmail($email, '影视站自动续期提醒 - 算艺轩', $mediaMaturityTemplate);

                    } else {
                        $userModel = new UserModel();
                        $user = $userModel->where('id', $embyUser['userId'])->find();
                        $email = $user['email'];

                        // 发送邮件
                        $SiteUrl = "https://randallanjie.com/media";

                        $sysConfigModel = new SysConfigModel();
                        $mediaSoonMaturityTemplate = $sysConfigModel->where('key', 'mediaSoonMaturityTemplate')->find();
                        if ($mediaSoonMaturityTemplate) {
                            $mediaSoonMaturityTemplate = $mediaSoonMaturityTemplate['value'];
                        } else {
                            $mediaSoonMaturityTemplate = '您的Emby账号即将到期，到期后禁用需要重新激活账号，如需继续使用请及时续费，以免影响您的使用。如果开通了自动续期且余额足够请忽略此邮件。';
                        }
                        $mediaSoonMaturityTemplate = str_replace('{Email}', $email, $mediaSoonMaturityTemplate);
                        $mediaSoonMaturityTemplate = str_replace('{SiteUrl}', $SiteUrl, $mediaSoonMaturityTemplate);

                        sendTGMessage($embyUser['userId'], '您的Emby账号即将到期，到期后禁用需要重新激活账号，如需继续使用请及时续费，以免影响您的使用。如果开通了自动续期且余额足够请忽略此消息。');
                        sendEmail($email, '影视站即将到期提醒 - 算艺轩', $mediaSoonMaturityTemplate);
                    }
                } else if ($embyUser['activateTo'] != null && strtotime($embyUser['activateTo']) < time()) {
                    $embyUserId = $embyUser['embyId'];
                    $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Policy?api_key=' . Config::get('media.apiKey');
                    $data = [
                        'IsDisabled' => true
                    ];
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'accept: */*',
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $response = curl_exec($ch);
                    if (!(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204)) {
                        $flag = false;
                    }
                }
            }

//            // 找出所有未支付账单进行查询
//            $payRecordModel = new PayRecordModel();
//            $payRecordList = $payRecordModel->where('type', 1)->select();
//            foreach ($payRecordList as $payRecord) {
//                // 如果是7天之前的账单，变为已取消
//                if (strtotime($payRecord['createDate']) + 604800 < time()) {
//                    $payRecord->type = 3;
//                    $payRecord->save();
//                }
//            }


            if ($flag) {
                return json([
                    'code' => 200,
                    'message' => '执行成功'
                ]);
            } else {
                return json([
                    'code' => 200,
                    'message' => '部分执行成功'
                ]);
            }
        } else {
            return json([
                'code' => 400,
                'message' => '无效的key'
            ]);
        }
    }
    public function resolvePayment()
    {
        $rate = 1;
        if (Request::isGet()) {
            $key = Request::get('key');
            $PayRecordModel = new PayRecordModel();
            $payRecord = $PayRecordModel
                ->where('payCompleteKey', $key)
                ->where('type', 1)
                ->find();
            if ($payRecord) {
                $tradeNo = $payRecord['tradeNo'];
                // api.php?act=order&pid={商户ID}&key={商户密钥}&out_trade_no={商户订单号}
                $url = Config::get('payment.epay.urlBase') . 'api.php?act=order&pid=' . Config::get('payment.epay.id') . '&key=' . Config::get('payment.epay.key') . '&out_trade_no=' . $tradeNo;
                $respond = getHttpResponse($url);
                $respond = json_decode($respond, true);
                if ($respond['code'] == 1 && $respond['status'] == 1) {
                    $payRecordInfo = json_decode(json_encode($payRecord['payRecordInfo']), true);
                    $commodity = $payRecordInfo['commodity'];
                    $unit = $payRecordInfo['unit'];
                    $count = $payRecordInfo['count'];
                    $payRecord->type = 2;
                    $payRecord->save();
                    if ($commodity == 'Emby账号续期') {
                        $embyUserModel = new EmbyUserModel();
                        $embyUser = $embyUserModel->where('userId', $payRecord['userId'])->find();
                        $embyUserId = $embyUser->embyId;
                        $activateTo = $embyUser['activateTo'];
                        if ($unit == 'year') {
                            $seconds = 31536000 * $count;
                        } else if ($unit == 'season') {
                            $seconds = 2592000 * $count;
                        } else if ($unit == 'month') {
                            $seconds = 2592000 * $count;
                        } else if ($unit == 'week') {
                            $seconds = 604800 * $count;
                        } else if ($unit == 'day') {
                            $seconds = 86400 * $count;
                        }
                        if ($activateTo == null) {
                            $activateTo = date('Y-m-d H:i:s', time() + $seconds);
                        } else {
                            $activateTo = date('Y-m-d H:i:s', strtotime($activateTo) + $seconds);
                        }
                        $embyUser->activateTo = $activateTo;
                        $embyUser->save();
                        $financeRecordModel = new FinanceRecordModel();
                        $financeRecordModel->save([
                            'userId' => $payRecord['userId'],
                            'action' => 1,
                            'count' => $count,
                            'recordInfo' => [
                                'message' => '使用支付宝支付充值续期Emby账号'
                            ]
                        ]);
                        sendTGMessage($payRecord['userId'], '您的Emby账号已续期至 <strong>' . $activateTo . '</strong>');
                        return json([
                            'code' => 200,
                            'message' => 'ok'
                        ]);
                    } else if ($commodity == 'Emby账号激活') {
                        $embyUserModel = new EmbyUserModel();
                        $embyUser = $embyUserModel->where('userId', $payRecord['userId'])->find();
                        $embyUserId = $embyUser->embyId;

                        $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Policy?api_key=' . Config::get('media.apiKey');
                        $data = [
                            "IsAdministrator" => false,
                            "IsHidden" => true,
                            "IsHiddenRemotely" => true,
                            "IsHiddenFromUnusedDevices" => true,
                            "IsDisabled" => false,
                            "LockedOutDate" => 0,
                            "AllowTagOrRating" => false,
                            "BlockedTags" => [],
                            "IsTagBlockingModeInclusive" => false,
                            "IncludeTags" => [],
                            "EnableUserPreferenceAccess" => true,
                            "AccessSchedules" => [],
                            "BlockUnratedItems" => [],
                            "EnableRemoteControlOfOtherUsers" => false,
                            "EnableSharedDeviceControl" => false,
                            "EnableRemoteAccess" => true,
                            "EnableLiveTvManagement" => false,
                            "EnableLiveTvAccess" => true,
                            "EnableMediaPlayback" => true,
                            "EnableAudioPlaybackTranscoding" => false,
                            "EnableVideoPlaybackTranscoding" => false,
                            "EnablePlaybackRemuxing" => false,
                            "EnableContentDeletion" => false,
                            "RestrictedFeatures" => [
                                "notifications",
                                "trakt"
                            ],
                            "EnableContentDeletionFromFolders" => [],
                            "EnableContentDownloading" => false,
                            "EnableSubtitleDownloading" => false,
                            "EnableSubtitleManagement" => false,
                            "EnableSyncTranscoding" => false,
                            "EnableMediaConversion" => false,
                            "EnabledChannels" => [],
                            "EnableAllChannels" => true,
                            "EnabledFolders" => [],
                            "EnableAllFolders" => true,
                            "InvalidLoginAttemptCount" => 0,
                            "EnablePublicSharing" => false,
                            "RemoteClientBitrateLimit" => 0,
                            "AuthenticationProviderId" => "Emby.Server.Implementations.Library.DefaultAuthenticationProvider",
                            "ExcludedSubFolders" => [],
                            "SimultaneousStreamLimit" => 0,
                            "EnabledDevices" => [],
                            "EnableAllDevices" => true,
                            "AllowCameraUpload" => false,
                            "AllowSharingPersonalItems" => false
                        ];
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'accept: */*',
                            'Content-Type: application/json'
                        ]);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        $response = curl_exec($ch);
                        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 || curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204) {
                            $activateTo = date('Y-m-d H:i:s', time() + 86400);
                            $embyUser->activateTo = $activateTo;
                            $embyUser->save();
                            $financeRecordModel = new FinanceRecordModel();
                            $financeRecordModel->save([
                                'userId' => $payRecord['userId'],
                                'action' => 1,
                                'count' => 1,
                                'recordInfo' => [
                                    'message' => '使用支付宝支付充值激活Emby账号'
                                ]
                            ]);
                            sendTGMessage($payRecord['userId'], '您的Emby账号已激活');
                            return json([
                                'code' => 200,
                                'message' => 'ok'
                            ]);
                        } else {
                            return json([
                                'code' => 400,
                                'message' => '支付失败'
                            ]);
                        }
                    } else if ($commodity == 'R币充值') {
                        $userModel = new UserModel();
                        $user = $userModel->where('id', $payRecord['userId'])->find();
//                        $increase是$count*$rate向上取整取2位小数
                        $increase = ceil($count*$rate*100)/100;
                        $rCoin = $user->rCoin + $increase;
                        // $rCoin转换为double类型数据存入数据库
                        $rCoin = sprintf("%.2f", $rCoin);
                        $user->rCoin = $rCoin;
                        $user->save();
                        $financeRecordModel = new FinanceRecordModel();
                        $financeRecordModel->save([
                            'userId' => $payRecord['userId'],
                            'action' => 1,
                            'count' => $count,
                            'recordInfo' => [
                                'message' => '使用支付宝支付' . $count . '元充值' . $increase . 'R币' . ($rate!=1?'(其中包含限时优惠赠送' . ($increase-$count) . 'R币)':'')
                            ]
                        ]);
                        sendTGMessage($payRecord['userId'], '您已经成功充值了 <strong>' . $count . '</strong> 元，获得 <strong>' . $increase . '</strong> R币，当前余额为 <strong>' . $rCoin . '</strong>');
                        return json([
                            'code' => 200,
                            'message' => 'ok'
                        ]);
                    }

                    $userModel = new UserModel();
                    $user = $userModel->where('id', $payRecord['userId'])->find();
                    $email = $user['email'];
                    $money = $payRecord['money'];
                    // 发送邮件
                    $SiteUrl = "https://randallanjie.com/media";
                    $sysConfigModel = new SysConfigModel();
                    $mediaMaturityTemplate = $sysConfigModel->where('key', 'mediaMaturityTemplate')->find();
                    if ($mediaMaturityTemplate) {
                        $mediaMaturityTemplate = $mediaMaturityTemplate['value'];
                    } else {
                        $mediaMaturityTemplate = '您的账单已经支付成功<br>您购买的商品为：{Commodity}<br>金额：¥ {Money}<br>感谢您的支持';
                    }
                    $mediaMaturityTemplate = str_replace('{Email}', $email, $mediaMaturityTemplate);
                    $mediaMaturityTemplate = str_replace('{SiteUrl}', $SiteUrl, $mediaMaturityTemplate);
                    $mediaMaturityTemplate = str_replace('{Commodity}', $commodity, $mediaMaturityTemplate);
                    $mediaMaturityTemplate = str_replace('{Money}', $money, $mediaMaturityTemplate);

                    sendEmail($email, '账单支付成功 - 算艺轩', $mediaMaturityTemplate);
                } else {
                    return json([
                        'code' => 400,
                        'message' => '支付失败'
                    ]);
                }
            } else {
                return json([
                    'code' => 400,
                    'message' => '支付失败'
                ]);
            }
        }
    }

//    public function activateEmbyUserByPay()
//    {
//        if (Session::get('r_user') == null) {
//            $url = Request::url(true);
//            Session::set('jump_url', $url);
//            return redirect('/media/user/login');
//        }
//        if (Request::isPost()) {
//            $data = Request::post();
//            $embyUserModel = new EmbyUserModel();
//            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
//            $embyUserId = $embyUser->embyId;
//            $activateTo = $embyUser['activateTo'];
//            if ($activateTo == null) {
//                return json([
//                    'code' => 400,
//                    'message' => 'LifeTime用户无法激活，请联系客服'
//                ]);
//            }
//
//            $tradeNo = time() . random_int(1000, 9999);
//            $payCompleteKey = generateRandomString();
//
//            $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
//                $_SERVER['HTTP_X_REAL_IP'] ??
//                $_SERVER['HTTP_CF_CONNECTING_IP'] ??
//                Request::ip();
//
//            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
//                $realIp = trim($ipList[0]);
//            }
//
//            // 获取易支付生成的支付二维码
//            $url = Config::get('payment.urlBase') . 'mapi.php';
//            $data = [
//                'pid' => Config::get('payment.id'),
//                'type' => 'alipay',
//                'out_trade_no' => $tradeNo,
//                'notify_url' => 'https://randallanjie.com/media/server/resolvePayment?key=' . $payCompleteKey,
//                'return_url' => 'https://randallanjie.com/media/server/account',
//                'name' => 'Emby账号激活',
//                'money' => 1,
//                'clientip' => $realIp,
//                'sign' => '',
//                'sign_type' => 'MD5'
//            ];
//
//            $data['sign'] = getPaySign($data);
//
//            $respond = getHttpResponse($url, $data);
//            $payUrl = json_decode($respond, true)['qrcode']??json_decode($respond, true)['payurl'];
//
//            $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
//                $_SERVER['HTTP_X_REAL_IP'] ??
//                $_SERVER['HTTP_CF_CONNECTING_IP'] ??
//                Request::ip();
//
//            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
//                $realIp = trim($ipList[0]);
//            }
//
//            $PayRecordModel = new PayRecordModel();
//            $PayRecordModel->save([
//                'payCompleteKey' => $payCompleteKey,
//                'type' => 1,
//                'userId' => Session::get('r_user')->id,
//                'tradeNo' => $tradeNo,
//                'name' => 'Emby账号激活',
//                'money' => 1,
//                'clientip' => $realIp,
//                'payRecordInfo' => json_encode([
//                    'commodity' => 'Emby账号激活',
//                    'unit' => 'time',
//                    'count' => 1,
//                    'payUrl' => $payUrl
//                ])
//            ]);
//
//            return json([
//                'code' => 200,
//                'message' => '请求支付二维码成功，请扫码支付',
//                'qrcodeUrl' => $payUrl,
//            ]);
//
//        }
//    }
//
//    public function continueSubscribeEmbyUserByPay()
//    {
//        if (Session::get('r_user') == null) {
//            $url = Request::url(true);
//            Session::set('jump_url', $url);
//            return redirect('/media/user/login');
//        }
//        if (Request::isPost()) {
//            $data = Request::post();
//            $embyUserModel = new EmbyUserModel();
//            $embyUser = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
//            $embyUserId = $embyUser->embyId;
//            $activateTo = $embyUser['activateTo'];
//            if ($activateTo == null) {
//                return json([
//                    'code' => 400,
//                    'message' => 'LifeTime用户无需续期'
//                ]);
//            }
//
//            $tradeNo = time() . random_int(1000,9999);
//            $payCompleteKey = generateRandomString();
//
//            $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
//                $_SERVER['HTTP_X_REAL_IP'] ??
//                $_SERVER['HTTP_CF_CONNECTING_IP'] ??
//                Request::ip();
//
//            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
//                $realIp = trim($ipList[0]);
//            }
//
//            // 获取易支付生成的支付二维码
//            $url = Config::get('payment.urlBase') . 'mapi.php';
//            $data = [
//                'pid' => Config::get('payment.id'),
//                'type' => 'alipay',
//                'out_trade_no' => $tradeNo,
//                'notify_url' => 'https://randallanjie.com/media/server/resolvePayment?key=' . $payCompleteKey,
//                'return_url' => 'https://randallanjie.com/media/server/account',
//                'name' => 'Emby账号续期',
//                'money' => 10,
//                'clientip' => $realIp,
//                'sign' => '',
//                'sign_type' => 'MD5'
//            ];
//
//            $data['sign'] = getPaySign($data);
//
//            $respond = getHttpResponse($url, $data);
//            $payUrl = json_decode($respond, true)['qrcode']??json_decode($respond, true)['payurl'];
//
//            $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
//                $_SERVER['HTTP_X_REAL_IP'] ??
//                $_SERVER['HTTP_CF_CONNECTING_IP'] ??
//                Request::ip();
//
//            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
//                $realIp = trim($ipList[0]);
//            }
//
//            $PayRecordModel = new PayRecordModel();
//            $PayRecordModel->save([
//                'payCompleteKey' => $payCompleteKey,
//                'type' => 1,
//                'userId' => Session::get('r_user')->id,
//                'tradeNo' => $tradeNo,
//                'name' => 'Emby账号续期',
//                'money' => 10,
//                'clientip' => $realIp,
//                'payRecordInfo' => json_encode([
//                    'commodity' => 'Emby账号续期',
//                    'unit' => 'month',
//                    'count' => 1,
//                    'payUrl' => $payUrl
//                ])
//            ]);
//
//            return json([
//                'code' => 200,
//                'message' => '请求支付二维码成功，请扫码支付',
//                'qrcodeUrl' => $payUrl,
//            ]);
//        }
//    }

    public function pay()
    {
        if (Session::get('r_user') == null) {
            $url = Request::url(true);
            Session::set('jump_url', $url);
            return redirect('/media/user/login');
        }
        if (Request::isPost()) {
            $data = Request::post();
            // 检测$data['money']是否为数字，并且最多有两位小数
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $data['money']) || $data['money'] <= 0) {
                return json([
                    'code' => 400,
                    'message' => '请输入正确的金额'
                ]);
            }
            $payMethod = 'alipay';
            $chanel = 'epay';
            if (isset($data['method'])) {
                if ($data['method'] == 'usdt' || $data['method'] == 'trx') {
                    $chanel = 'usdt';
                } else {
                    $availablePayMethod = Config::get('payment.epay.availablePayment');
                    if (in_array($data['method'], $availablePayMethod)) {
                        $payMethod = $data['method'];
                    }
                }
            }
            $tradeNo = time() . random_int(1000, 9999);
            $payCompleteKey = generateRandomString();

            $realIp = getRealIp();

            $url = '';
            $sendData = [];
            if ($chanel == 'epay') {
                $url = Config::get('payment.epay.urlBase') . 'mapi.php';
                $sendData = [
                    'pid' => Config::get('payment.epay.id'),
                    'type' => $payMethod,
                    'out_trade_no' => $tradeNo,
                    'notify_url' => Config::get('app.app_host') . '/media/server/resolvePayment?key=' . $payCompleteKey,
                    'return_url' => Config::get('app.app_host') . '/media/server/account',
                    'name' => 'R币充值',
                    'money' => $data['money'],
                    'clientip' => $realIp,
                    'sign' => '',
                    'sign_type' => 'MD5'
                ];
                $sendData['sign'] = getPaySign($sendData);
            } else if ($chanel == 'usdt') {
                $url = Config::get('payment.usdt.urlBase') . 'api/v1/order/create-transaction';
                $sendData = [
                    'trade_type' => $data['method']=='usdt'?'usdt.trc20':'tron.trx',
                    'order_id' => $tradeNo,
                    'amount' => $data['money'],
                    'signature' => '',
                    'notify_url' => Config::get('app.app_host') . '/media/server/resolveUsdtPayment?key=' . $payCompleteKey,
                    'redirect_url' => Config::get('app.app_host') . '/media/server/account'
                ];
            }
            $respond = getHttpResponse($url, $sendData);

            if ($respond == '' || json_decode($respond, true)['code'] == -1) {
                return json([
                    'code' => 400,
                    'message' => json_decode($respond, true)['msg']??'请求支付二维码失败',
                    'original' => $respond
                ]);
            }
            $respond = json_decode($respond, true);
            if (isset($respond['qrcode']) || isset($respond['payurl'])) {
                $payUrl = $respond['qrcode']??$respond['payurl'];
            } else {
                return json([
                    'code' => 400,
                    'message' => '请求支付二维码失败',
                    'original' => $respond
                ]);
            }

            $PayRecordModel = new PayRecordModel();
            $PayRecordModel->save([
                'payCompleteKey' => $payCompleteKey,
                'type' => 1,
                'userId' => Session::get('r_user')->id,
                'tradeNo' => $tradeNo,
                'name' => 'R币充值',
                'money' => $data['money'],
                'clientip' => $realIp,
                'payRecordInfo' => json_encode([
                    'commodity' => 'R币充值',
                    'unit' => 'money',
                    'count' => $data['money'],
                    'payUrl' => $payUrl,
                    'payMethod' => $payMethod,
                ])
            ]);

            return json([
                'code' => 200,
                'message' => '请求支付二维码成功，请扫码支付',
                'qrcodeUrl' => $payUrl,
                'method' => $payMethod
            ]);
        }
    }
}
