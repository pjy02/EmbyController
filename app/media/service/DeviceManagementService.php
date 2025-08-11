<?php

namespace app\media\service;

use app\media\model\EmbyDeviceModel;
use app\media\model\EmbyUserModel;
use app\media\model\SysConfigModel;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;

/**
 * 设备管理服务层
 * 负责设备信息的统一管理、状态同步和业务逻辑处理
 */
class DeviceManagementService
{
    /**
     * 获取用户设备列表
     * 
     * @param string $embyId Emby用户ID
     * @param array $options 查询选项
     * @return array
     */
    public function getUserDevices($embyId, $options = [])
    {
        try {
            if (empty($embyId)) {
                return [];
            }
            
            $embyDeviceModel = new EmbyDeviceModel();
            $query = $embyDeviceModel->where('embyId', $embyId);
            
            // 过滤已停用设备
            if (!isset($options['include_deactivated']) || !$options['include_deactivated']) {
                $query->where('deactivate', 'in', [0, null]);
            }
            
            // 时间范围过滤
            if (isset($options['active_since'])) {
                $query->where('lastUsedTime', '>=', $options['active_since']);
            }
            
            // 排序
            $orderBy = $options['order_by'] ?? 'lastUsedTime';
            $orderDirection = $options['order_direction'] ?? 'desc';
            $query->order($orderBy, $orderDirection);
            
            // 分页
            if (isset($options['limit'])) {
                $query->limit($options['limit']);
            }
            
            $devices = $query->select();
            return $devices ? $devices->toArray() : [];
            
        } catch (\Exception $e) {
            Log::error('获取用户设备列表失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 更新设备信息
     * 
     * @param array $deviceData 设备数据
     * @return bool
     */
    public function updateDevice($deviceData)
    {
        try {
            if (!isset($deviceData['embyId']) || !isset($deviceData['deviceId'])) {
                return false;
            }
            
            $embyDeviceModel = new EmbyDeviceModel();
            $device = $embyDeviceModel
                ->where('embyId', $deviceData['embyId'])
                ->where('deviceId', $deviceData['deviceId'])
                ->find();
            
            $updateData = [
                'lastUsedTime' => date('Y-m-d H:i:s'),
                'lastUsedIp' => $deviceData['lastUsedIp'] ?? '',
                'client' => $deviceData['client'] ?? '',
                'deviceName' => $deviceData['deviceName'] ?? '',
                'deactivate' => 0,
            ];
            
            // 更新设备信息JSON字段
            if (isset($deviceData['deviceInfo'])) {
                $updateData['deviceInfo'] = json_encode($deviceData['deviceInfo']);
            }
            
            if ($device) {
                // 更新现有设备
                $result = $embyDeviceModel
                    ->where('id', $device['id'])
                    ->update($updateData);
                
                // 触发设备更新事件
                event('DeviceUpdated', $deviceData);
                
                return $result !== false;
            } else {
                // 创建新设备
                $updateData['embyId'] = $deviceData['embyId'];
                $updateData['deviceId'] = $deviceData['deviceId'];
                
                $result = $embyDeviceModel->save($updateData);
                
                // 触发设备创建事件
                event('DeviceCreated', $deviceData);
                
                return $result !== false;
            }
            
        } catch (\Exception $e) {
            Log::error('更新设备信息失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 停用设备
     * 
     * @param string $deviceId 设备ID
     * @param string $embyId Emby用户ID
     * @return bool
     */
    public function deactivateDevice($deviceId, $embyId)
    {
        try {
            $embyDeviceModel = new EmbyDeviceModel();
            $device = $embyDeviceModel
                ->where('deviceId', $deviceId)
                ->where('embyId', $embyId)
                ->where('deactivate', 'in', [0, null])
                ->find();
            
            if (!$device) {
                return false;
            }
            
            $result = $embyDeviceModel
                ->where('id', $device['id'])
                ->update(['deactivate' => 1]);
            
            // 触发设备停用事件
            event('DeviceDeactivated', [
                'deviceId' => $deviceId,
                'embyId' => $embyId
            ]);
            
            return $result !== false;
            
        } catch (\Exception $e) {
            Log::error('停用设备失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取设备统计信息
     * 
     * @param string $embyId Emby用户ID
     * @return array
     */
    public function getDeviceStats($embyId)
    {
        try {
            $embyDeviceModel = new EmbyDeviceModel();
            
            // 总设备数
            $totalDevices = $embyDeviceModel
                ->where('embyId', $embyId)
                ->count();
            
            // 活跃设备数（7天内使用过）
            $activeDevices = $embyDeviceModel
                ->where('embyId', $embyId)
                ->where('lastUsedTime', '>', date('Y-m-d H:i:s', strtotime('-7 day')))
                ->where('deactivate', 0)
                ->count();
            
            // 已停用设备数
            $deactivatedDevices = $embyDeviceModel
                ->where('embyId', $embyId)
                ->where('deactivate', 1)
                ->count();
            
            return [
                'total' => $totalDevices,
                'active' => $activeDevices,
                'deactivated' => $deactivatedDevices,
            ];
            
        } catch (\Exception $e) {
            Log::error('获取设备统计信息失败: ' . $e->getMessage());
            return [
                'total' => 0,
                'active' => 0,
                'deactivated' => 0,
            ];
        }
    }
    
    /**
     * 检查设备数量限制
     * 
     * @param string $embyId Emby用户ID
     * @return array
     */
    public function checkDeviceLimit($embyId)
    {
        try {
            $sysConfigModel = new SysConfigModel();
            $maxActiveDeviceCount = $sysConfigModel->where('key', 'maxActiveDeviceCount')->find();
            $maxActiveDeviceCount = $maxActiveDeviceCount ? intval($maxActiveDeviceCount['value']) : 10;
            
            if ($maxActiveDeviceCount <= 0) {
                return ['exceeded' => false, 'max' => 0, 'current' => 0];
            }
            
            $embyDeviceModel = new EmbyDeviceModel();
            $currentCount = $embyDeviceModel
                ->where('embyId', $embyId)
                ->where('lastUsedTime', '>', date('Y-m-d H:i:s', strtotime('-7 day')))
                ->where('deactivate', 0)
                ->count();
            
            return [
                'exceeded' => $currentCount >= $maxActiveDeviceCount,
                'max' => $maxActiveDeviceCount,
                'current' => $currentCount,
                'warning_threshold' => floor($maxActiveDeviceCount * 0.8)
            ];
            
        } catch (\Exception $e) {
            Log::error('检查设备数量限制失败: ' . $e->getMessage());
            return ['exceeded' => false, 'max' => 0, 'current' => 0];
        }
    }
    
    /**
     * 获取设备与会话的关联信息
     * 
     * @param string $embyId Emby用户ID
     * @param array $sessions 会话列表
     * @return array
     */
    public function getDeviceSessionMapping($embyId, $sessions)
    {
        try {
            $devices = $this->getUserDevicesByEmbyId($embyId);
            $mapping = [];
            
            foreach ($devices as $device) {
                $deviceInfo = json_decode($device['deviceInfo'], true);
                $sessionId = $deviceInfo['sessionId'] ?? '';
                
                if ($sessionId) {
                    // 通过sessionId匹配
                    foreach ($sessions as $session) {
                        if (isset($session['Id']) && $session['Id'] === $sessionId) {
                            $mapping[$device['deviceId']] = [
                                'device' => $device,
                                'session' => $session,
                                'match_type' => 'session_id'
                            ];
                            break;
                        }
                    }
                }
                
                // 如果sessionId匹配失败，尝试通过deviceId匹配
                if (!isset($mapping[$device['deviceId']])) {
                    foreach ($sessions as $session) {
                        if (isset($session['DeviceId']) && $session['DeviceId'] === $device['deviceId']) {
                            $mapping[$device['deviceId']] = [
                                'device' => $device,
                                'session' => $session,
                                'match_type' => 'device_id'
                            ];
                            break;
                        }
                    }
                }
                
                // 如果仍然匹配失败，尝试通过客户端名称和设备名称匹配
                if (!isset($mapping[$device['deviceId']])) {
                    foreach ($sessions as $session) {
                        $clientMatch = isset($session['Client']) && $session['Client'] === $device['client'];
                        $deviceNameMatch = isset($session['DeviceName']) && $session['DeviceName'] === $device['deviceName'];
                        
                        if ($clientMatch && $deviceNameMatch) {
                            $mapping[$device['deviceId']] = [
                                'device' => $device,
                                'session' => $session,
                                'match_type' => 'client_device_name'
                            ];
                            break;
                        }
                    }
                }
            }
            
            return $mapping;
            
        } catch (\Exception $e) {
            Log::error('获取设备会话映射失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据Emby用户ID获取设备列表
     * 
     * @param string $embyId Emby用户ID
     * @return array
     */
    private function getUserDevicesByEmbyId($embyId)
    {
        try {
            $embyDeviceModel = new EmbyDeviceModel();
            $devices = $embyDeviceModel
                ->where('embyId', $embyId)
                ->where('deactivate', 'in', [0, null])
                ->select();
            
            return $devices ? $devices->toArray() : [];
            
        } catch (\Exception $e) {
            Log::error('根据Emby用户ID获取设备列表失败: ' . $e->getMessage());
            return [];
        }
    }
}