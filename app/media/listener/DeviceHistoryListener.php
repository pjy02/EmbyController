<?php

namespace app\media\listener;

use app\media\event\DeviceStatusChangedEvent;
use app\media\model\DeviceHistoryModel;
use think\facade\Log;

/**
 * 设备状态历史记录监听器
 * 监听设备状态变更事件，记录状态变更历史
 */
class DeviceHistoryListener
{
    /**
     * 处理设备状态变更事件
     * 
     * @param DeviceStatusChangedEvent $event
     * @return bool
     */
    public function handle(DeviceStatusChangedEvent $event): bool
    {
        try {
            $device = $event->getDevice();
            $statusType = $event->getStatusType();
            $oldStatus = $event->getOldStatus();
            $newStatus = $event->getNewStatus();
            $session = $event->getSession();
            
            Log::info('设备状态历史记录监听器触发', $event->getEventData());
            
            // 创建历史记录
            $historyData = [
                'deviceId' => $device['deviceId'],
                'embyId' => $event->getUserId(),
                'statusType' => $statusType,
                'oldStatus' => json_encode($oldStatus),
                'newStatus' => json_encode($newStatus),
                'sessionId' => $session['Id'] ?? null,
                'client' => $session['Client'] ?? $device['client'] ?? '',
                'deviceName' => $session['DeviceName'] ?? $device['deviceName'] ?? '',
                'ip' => $session['RemoteEndPoint'] ?? $device['lastUsedIp'] ?? '',
                'changeTime' => $event->getTimestamp(),
                'additionalInfo' => json_encode([
                    'session_info' => $session,
                    'device_info' => $device
                ])
            ];
            
            // 保存历史记录
            $result = DeviceHistoryModel::create($historyData);
            
            if ($result) {
                Log::info('设备状态历史记录保存成功', [
                    'device_id' => $device['deviceId'],
                    'status_type' => $statusType,
                    'history_id' => $result->id
                ]);
                return true;
            } else {
                Log::warning('设备状态历史记录保存失败', [
                    'device_id' => $device['deviceId'],
                    'status_type' => $statusType
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('设备状态历史记录监听器处理失败: ' . $e->getMessage(), [
                'event_data' => $event->getEventData()
            ]);
            return false;
        }
    }
    
    /**
     * 清理过期历史记录（保留最近30天）
     * 
     * @return bool
     */
    public function cleanExpiredHistory(): bool
    {
        try {
            $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            $result = DeviceHistoryModel::where('changeTime', '<', $thirtyDaysAgo)
                ->delete();
            
            Log::info('清理过期设备状态历史记录完成', [
                'deleted_count' => $result,
                'cutoff_time' => $thirtyDaysAgo
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('清理过期设备状态历史记录失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取设备状态变更统计
     * 
     * @param string $deviceId 设备ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getDeviceStatusStatistics(string $deviceId, string $startDate, string $endDate): array
    {
        try {
            $statistics = DeviceHistoryModel::where('deviceId', $deviceId)
                ->whereBetween('changeTime', [$startDate, $endDate])
                ->field('statusType, COUNT(*) as count')
                ->group('statusType')
                ->select()
                ->toArray();
            
            $result = [];
            foreach ($statistics as $stat) {
                $result[$stat['statusType']] = $stat['count'];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('获取设备状态变更统计失败: ' . $e->getMessage());
            return [];
        }
    }
}