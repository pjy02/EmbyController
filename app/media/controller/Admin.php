<?php

namespace app\media\controller;

use app\BaseController;
use app\media\model\FinanceRecordModel;
use app\media\model\RequestModel as RequestModel;
use app\media\model\SysConfigModel;
use think\facade\Request;
use think\facade\Session;
use app\media\model\UserModel as UserModel;
use think\facade\View;
use think\facade\Config;
use think\facade\Cache;

class Admin extends BaseController
{
    public function index()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        return view();
    }

    public function admin()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        return redirect((string) url('/media/admin/index'));
    }

    public function request()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        $page = input('page', 1, 'intval');
        $pagesize = input('pagesize', 10, 'intval');
        $requestModel = new RequestModel();
        $requestModel = $requestModel
            ->order('rc_request.updatedAt', 'desc')
            ->order('type', 'asc')
            ->field('rc_request.*, u1.nickName as requestNickName, u1.userName as requestUserName, u2.nickName as replyNickName, u2.userName as replyUserName')
            ->join('rc_user u1', 'rc_request.requestUserId = u1.id', 'LEFT')
            ->join('rc_user u2', 'rc_request.replyUserId = u2.id', 'LEFT');
        $pageCount = ceil($requestModel->count() / $pagesize);
        $requestsList = $requestModel
            ->page($page, $pagesize)
            ->select();
        View::assign('page', $page);
        View::assign('pageCount', $pageCount);
        View::assign('requestsList', $requestsList);
        return view();
    }

    public function requestDetail()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        $data = Request::get();
        $requestModel = new RequestModel();
        $request = $requestModel->where('id', $data['id'])->find();

        $request['message'] = json_decode($request['message'], true);

        $userModel = new UserModel();
        $requestUser = $userModel->where('id', $request['requestUserId'])->find();
        $requestUser->password = '';
        $replyUser = $userModel->where('id', $request['replyUserId'])->find();
        if ($replyUser) {
            $replyUser->password = '';
        }
        View::assign('requestUser', $requestUser);
        View::assign('replyUser', $replyUser);
        View::assign('request', $request);
        return view();
    }

    public function requestAddReply()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        if (Request::isPost()) {
            $data = Request::post();
            if ($data['content'] == '') {
                return json(['code' => 400, 'message' => '回复内容不能为空']);
            }
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();

            if (!$request) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            if ($request->replyUserId != null && $request->replyUserId != Session::get('r_user')->id) {
                return json(['code' => 400, 'message' => '您无权回复该工单']);
            }

            $message = json_decode($request['message'], true);

            if ($request->replyUserId == null) {
                $request->replyUserId = Session::get('r_user')->id;
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '管理员(#' . Session::get('r_user')->id . ')已加入对话'
                ];
            }

            $message[] = [
                'role' => 'admin',
                'userId' => Session::get('r_user')->id,
                'time' => date('Y-m-d H:i:s'),
                'content' => $data['content'],
            ];
            $request->message = json_encode($message);
            $request->type = 2;
            $request->save();
            $title = json_decode(json_encode($request['requestInfo']), true)['title'];
            sendTGMessage($request->requestUserId, '您标题为 <strong>' . $title . '</strong> 工单已经回复，回复内容如下：' . $data['content']);
            // 发送邮件
            $userModel = new UserModel();
            $user = $userModel->where('id', $request->requestUserId)->find();
            if ($user && $user->email) {

                $Message = $data['content'];
                $Email = $user->email;
                $SiteUrl = "https://randallanjie.com/media";

                $sysConfigModel = new SysConfigModel();
                $requestAlreadyReply = $sysConfigModel->where('key', 'requestAlreadyReply')->find();
                if ($requestAlreadyReply) {
                    $requestAlreadyReply = $requestAlreadyReply['value'];
                } else {
                    $requestAlreadyReply = '您的工单已经回复，回复内容如下：<br>{Message}<br>请登录系统查看：<a href="{SiteUrl}">{SiteUrl}</a>';
                }

                $requestAlreadyReply = str_replace('{Message}', $Message, $requestAlreadyReply);
                $requestAlreadyReply = str_replace('{Email}', $Email, $requestAlreadyReply);
                $requestAlreadyReply = str_replace('{SiteUrl}', $SiteUrl, $requestAlreadyReply);

                sendEmail($user->email, '您的工单已经回复', $requestAlreadyReply);
            }

            return json(['code' => 200, 'message' => '回复已提交', 'messageRecord' => json_encode($message)]);
        }
    }

    public function getThisReply()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        if (Request::isPost()) {
            $data = Request::post();
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();

            if (!$request) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            if ($request->replyUserId != null && $request->replyUserId != Session::get('r_user')->id) {
                return json(['code' => 400, 'message' => '您无权操作该工单']);
            }

            $message = json_decode($request['message'], true);

            if ($request->replyUserId == null) {
                $request->replyUserId = Session::get('r_user')->id;
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '管理员(#' . Session::get('r_user')->id . ')已加入对话'
                ];

                $request->message = json_encode($message);
                $request->save();
                return json(['code' => 200, 'message' => '已加入对话', 'messageRecord' => json_encode($message)]);
            } else {
                return json(['code' => 400, 'message' => '您无权操作该工单']);
            }

        }
    }

    public function requestClose()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        if (Request::isPost()) {
            $data = Request::post();
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();

            if (!$request) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            if ($request->replyUserId != Session::get('r_user')->id) {
                return json(['code' => 400, 'message' => '您无权对该工单进行关闭操作']);
            }

            if ($request->type > 0) {
                $message = json_decode($request['message'], true);
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '管理员(#' . Session::get('r_user')->id . ')关闭该工单',
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

    public function requestLeave()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        if (Request::isPost()) {
            $data = Request::post();
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();

            if (!$request) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            if ($request->replyUserId != Session::get('r_user')->id) {
                return json(['code' => 400, 'message' => '您无权对该工单进行操作']);
            }

            if ($request->type > 0) {
                $message = json_decode($request['message'], true);
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '管理员(#' . Session::get('r_user')->id . ')已离开对话',
                ];
                $request->message = json_encode($message);
                $request->replyUserId = null;
                $request->save();
                return json(['code' => 200, 'message' => '已离开对话', 'messageRecord' => json_encode($message)]);
            } else {
                return json(['code' => 400, 'message' => '工单已关闭，无法操作']);
            }
        }
    }

    public function requestReward()
    {
        if (session('r_user') == null || session('r_user')['authority'] != 0) {
            return redirect((string) url('/media/user/index'));
        }
        if (Request::isPost()) {
            $data = Request::post();
            $requestModel = new RequestModel();
            $request = $requestModel->where('id', $data['requestId'])->find();
            $reward = $data['reward'];
            if (!$request) {
                return json(['code' => 400, 'message' => '工单不存在']);
            }

            if ($request->type > 0) {
                $message = json_decode($request['message'], true);
                $message[] = [
                    'role' => 'system',
                    'time' => date('Y-m-d H:i:s'),
                    'content' => '管理员(#' . Session::get('r_user')->id . ')奖励给您了' . $reward . 'R币',
                ];
                $request->message = json_encode($message);
                $request->save();


                $financeRecordModel = new FinanceRecordModel();
                $financeRecordModel->save([
                    'userId' => $request->requestUserId,
                    'action' => 8,
                    'count' => $reward,
                    'recordInfo' => [
                        'message' => '管理员(#' . Session::get('r_user')->id . ')已在您的工单(#' . $data['requestId'] . ')奖励给您了' . $reward . 'R币',
                    ]
                ]);


                $userModel = new UserModel();
                $user = $userModel->where('id', $request->requestUserId)->find();
                $user->rCoin = $user->rCoin + $reward;
                $user->save();
                return json(['code' => 200, 'message' => '奖励成功', 'messageRecord' => json_encode($message)]);
            } else {
                return json(['code' => 400, 'message' => '工单已关闭，无法操作']);
            }
        }
    }
}
