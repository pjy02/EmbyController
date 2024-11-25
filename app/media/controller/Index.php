<?php

namespace app\media\controller;

use app\api\model\EmbyUserModel;
use app\BaseController;
use app\media\model\MediaCommentModel;
use think\facade\Request;
use think\facade\Session;
use app\media\model\UserModel as UserModel;
use think\facade\View;
use think\facade\Config;
use think\facade\Cache;

class Index extends BaseController
{

    public function index()
    {
        // 查询用户数
        $userModel = new UserModel();
        $allRegisterUserCount = $userModel->count();
        $activateRegisterUserCount = $userModel->where('authority', '>=', 0)->count();
        $deactivateRegisterUserCount = $allRegisterUserCount - $activateRegisterUserCount;
        // 24小时登录用户数
        $todayLoginUserCount = $userModel->where('updatedAt', '>=', date('Y-m-d H:i:s', strtotime('-1 day')))->count();
        // 查询最新的两条评论，comment需要大于100字
        $mediaCommentModel = new MediaCommentModel();
        $latestMediaComment = $mediaCommentModel
            ->where('comment', '>', 50)
            ->where('mentions', '=', '[]')
            ->order('createdAt', 'desc')
            ->limit(2)
            ->select();
        foreach ($latestMediaComment as $key => $comment) {
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
                $latestMediaComment[$key]['mentions'] = $mentionsUser;
            }
        }
        View::assign('allRegisterUserCount', $allRegisterUserCount);
        View::assign('activateRegisterUserCount', $activateRegisterUserCount);
        View::assign('deactivateRegisterUserCount', $deactivateRegisterUserCount);
        View::assign('todayLoginUserCount', $todayLoginUserCount);
        View::assign('latestMediaComment', $latestMediaComment);
        return view();
    }

    public function getLineStatus()
    {
        $islogin = false;
        if (Session::get('r_user') != null) {
            $islogin = true;
        }
        // 处理POST请求
        if (Request::isPost()) {
            if (Cache::get('serverList')) {
                $serverList = Cache::get('serverList');
            } else {
                $serverList = [];
                $lineList = Config::get('media.lineList');
                $i = 0;
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
                    $i++;
                    $serverList[] = [
                        'name' => $line['name'],
                        'url' => $line['url'],
                        'status' => $status
                    ];
                }
                // 将serverList保存到缓存中
                Cache::set('serverList', $serverList, 600);
            }

            if (!$islogin) {
                // 去除name和url
                $i = 0;
                foreach ($serverList as $key => $value) {
                    $i++;
                    $serverList[$key]['name'] = $i;
                    $serverList[$key]['url'] = '';
                }
            }

            return json(['code' => 200, 'serverList' => $serverList]);
        }
    }

    public function getLatestMedia() {
        if (request()->isPost()) {
            $embyUserModel = new EmbyUserModel();
            if (Session::get('r_user') != null) {
                $embyUser = $embyUserModel->where('userId', Session::get('r_user')['id'])->find();
            } else {
                $embyUser = null;
            }
            if ($embyUser) {
                $embyUserId = $embyUser['embyId'];
            } else {
                $embyUserId = Config::get('media.adminUserId');
            }
            if (Cache::get('latestMedia-'.$embyUserId)) {
                $latestMedia = Cache::get('latestMedia-'.$embyUserId);
            } else {
                $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Items/Latest?EnableImages=true&EnableUserData=false&api_key=' . Config::get('media.apiKey');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: application/json'
                ]);
                $latestMedia = curl_exec($ch);
                Cache::set('latestMedia-'.$embyUserId, $latestMedia, 600);
            }
            return json(['code' => 200, 'latestMedia' => json_decode($latestMedia, true)]);
        }
    }

    public function getMetaData() {
        if (request()->isPost()) {
            $data = request()->post();
            if (isset($data['mediaId']) && $data['mediaId'] != '') {
                $embyUserModel = new EmbyUserModel();
                $embyUser = $embyUserModel->where('userId', Session::get('r_user')['id'])->find();
                if ($embyUser) {
                    $embyUserId = $embyUser['embyId'];
                } else {
                    $embyUserId = Config::get('media.adminUserId');
                }
                $url = Config::get('media.urlBase') . 'Users/' . $embyUserId . '/Items/' . $data['mediaId'] . '?api_key=' . Config::get('media.apiKey');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: application/json'
                ]);
                $metaData = curl_exec($ch);
                $mediaCommentModel = new MediaCommentModel();
                $mediaComment = $mediaCommentModel->where('mediaId', $data['mediaId'])->find();
                if ($mediaComment) {
                    $mediaCommentCount = $mediaCommentModel->where('mediaId', $data['mediaId'])->count();
                    $averageRate = $mediaCommentModel->where('mediaId', $data['mediaId'])->avg('rating');
                } else {
                    $mediaCommentCount = 0;
                    $averageRate = 0;
                }
                $basicInfo = [
                    'mediaCommentCount' => $mediaCommentCount,
                    'averageRate' => $averageRate
                ];
                return json(['code' => 200, 'metaData' => json_decode($metaData, true), 'basicInfo' => $basicInfo]);
            } else {
                return json(['code' => 400, 'message' => 'mediaId不能为空']);
            }
        }
    }

    public function getPrimaryImg() {
        if (request()->isGet()) {
            $id = input('id');
            $url = Config::get('media.urlBase') . 'Items/' . $id . '/Images/Primary?quality=80&api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: */*'
            ]);
            return curl_exec($ch);
        }
    }

    public function admin()
    {
        return redirect((string) url('/admin'));
    }

    public function demo()
    {

        $url = Config::get('media.urlBase') . 'Users/4a3606375b5d4d94a1f495af228066b2/Items/393434?EnableTotalRecordCount=true&api_key=' . Config::get('media.apiKey');
//        $url = Config::get('media.urlBase') . 'Items/661720?UserId=4a3606375b5d4d94a1f495af228066b2&api_key=' . Config::get('media.apiKey');
//        $url = Config::get('media.urlBase') . 'Movies/Recommendations?&pi_key=4d2f4c146c3742adabc0b6ad1c6ff735';
//        $url = Config::get('media.urlBase') . 'Items?Ids=107786%2C107787&&pi_key=4d2f4c146c3742adabc0b6ad1c6ff735';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json'
        ]);
        $movieRecommendations = curl_exec($ch);
        echo $movieRecommendations;
        die();
        return view();
    }

    public function test()
    {
        // 查询用户数
        $userModel = new UserModel();
        $allRegisterUserCount = $userModel->count();
        $activateRegisterUserCount = $userModel->where('authority', '>=', 0)->count();
        $deactivateRegisterUserCount = $allRegisterUserCount - $activateRegisterUserCount;
        // 24小时登录用户数
        $todayLoginUserCount = $userModel->where('updatedAt', '>=', date('Y-m-d H:i:s', strtotime('-1 day')))->count();


        // 查询最新的两条评论，comment需要大于100字
        $mediaCommentModel = new MediaCommentModel();
        $latestMediaComment = $mediaCommentModel
            ->where('comment', '>', 50)
            ->order('createdAt', 'desc')
            ->limit(2)
            ->select();

        foreach ($latestMediaComment as $key => $comment) {
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
                $latestMediaComment[$key]['mentions'] = $mentionsUser;
            }
        }

        View::assign('allRegisterUserCount', $allRegisterUserCount);
        View::assign('activateRegisterUserCount', $activateRegisterUserCount);
        View::assign('deactivateRegisterUserCount', $deactivateRegisterUserCount);
        View::assign('todayLoginUserCount', $todayLoginUserCount);
        View::assign('latestMediaComment', $latestMediaComment);

        return view();
    }
}
