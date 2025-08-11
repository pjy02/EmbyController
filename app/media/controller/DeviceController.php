<?php

namespace app\media\controller;

use app\media\service\DeviceManagementService;
use app\media\service\SessionRepository;
use app\media\model\DeviceHistoryModel;
use think\facade\Session;
use think\facade\Request;
use think\facade\View;

class DeviceController
{
    /**
     * 设备管理服务
     * @var DeviceManagementService
     */
    private $deviceService;
    
    /**
     * 会话仓库
     * @var SessionRepository
     */
    private $sessionRepository;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->deviceService = new DeviceManagementService();
        $this->sessionRepository = new SessionRepository();
    }
    
    /**
     * 获取用户设备列表
     */
    public function index()
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        $embyUserModel = new \app\media\model\EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        
        if (!$user) {
            return json(['code' => 400, 'message' => '用户不存在']);
        }
        
        $devices = $this->deviceService->getUserDevices($user->embyId);
        return json(['code' => 200, 'message' => '获取成功', 'data' => $devices]);
    }
    
    /**
     * 获取设备详情
     */
    public function show($deviceId)
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        $embyUserModel = new \app\media\model\EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        
        if (!$user) {
            return json(['code' => 400, 'message' => '用户不存在']);
        }
        
        $device = $this->deviceService->getDevice($deviceId, $user->embyId);
        if (!$device) {
            return json(['code' => 404, 'message' => '设备不存在']);
        }
        
        return json(['code' => 200, 'message' => '获取成功', 'data' => $device]);
    }
    
    /**
     * 更新设备信息
     */
    public function update()
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        if (Request::isPost()) {
            $data = Request::post();
            
            $embyUserModel = new \app\media\model\EmbyUserModel();
            $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            
            if (!$user) {
                return json(['code' => 400, 'message' => '用户不存在']);
            }
            
            $deviceData = [
                'deviceId' => $data['deviceId'],
                'embyId' => $user->embyId,
                'lastUsedTime' => date('Y-m-d H:i:s'),
                'lastUsedIp' => $data['lastUsedIp'] ?? '',
                'client' => $data['client'] ?? '',
                'deviceName' => $data['deviceName'] ?? '',
                'deviceInfo' => $data['deviceInfo'] ?? [],
            ];
            
            $device = $this->deviceService->updateDevice($deviceData);
            if ($device) {
                return json(['code' => 200, 'message' => '更新成功', 'data' => $device]);
            } else {
                return json(['code' => 400, 'message' => '更新失败']);
            }
        }
    }
    
    /**
     * 停用设备
     */
    public function deactivate()
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        if (Request::isPost()) {
            $data = Request::post();
            $deviceId = $data['deviceId'];
            
            $embyUserModel = new \app\media\model\EmbyUserModel();
            $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
            
            if (!$user) {
                return json(['code' => 400, 'message' => '用户不存在']);
            }
            
            $result = $this->deviceService->deactivateDevice($deviceId, $user->embyId);
            if ($result) {
                return json(['code' => 200, 'message' => '停用成功']);
            } else {
                return json(['code' => 400, 'message' => '停用失败']);
            }
        }
    }
    
    /**
     * 获取用户会话列表
     */
    public function sessions()
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        $embyUserModel = new \app\media\model\EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        
        if (!$user) {
            return json(['code' => 400, 'message' => '用户不存在']);
        }
        
        $sessions = $this->sessionRepository->getUserSessions($user->embyId);
        return json(['code' => 200, 'message' => '获取成功', 'data' => $sessions]);
    }
    
    /**
     * 获取设备历史记录
     */
    public function history($deviceId)
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        $embyUserModel = new \app\media\model\EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        
        if (!$user) {
            return json(['code' => 400, 'message' => '用户不存在']);
        }
        
        $deviceHistoryModel = new DeviceHistoryModel();
        $history = $deviceHistoryModel->getDeviceHistory($deviceId);
        
        return json(['code' => 200, 'message' => '获取成功', 'data' => $history]);
    }
    
    /**
     * 获取设备统计信息
     */
    public function statistics()
    {
        if (!Session::has('r_user')) {
            return json(['code' => 400, 'message' => '请先登录']);
        }
        
        $embyUserModel = new \app\media\model\EmbyUserModel();
        $user = $embyUserModel->where('userId', Session::get('r_user')->id)->find();
        
        if (!$user) {
            return json(['code' => 400, 'message' => '用户不存在']);
        }
        
        $deviceHistoryModel = new DeviceHistoryModel();
        $statistics = $deviceHistoryModel->getDeviceStatusStatistics($user->embyId);
        
        return json(['code' => 200, 'message' => '获取成功', 'data' => $statistics]);
    }
}