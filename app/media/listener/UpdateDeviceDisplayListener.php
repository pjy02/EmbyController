<?php

namespace app\media\listener;

use app\media\event\DeviceStatusChangedEvent;
use app\media\model\EmbyDeviceModel;
use think\facade\Log;

/**
 * 设备显示更新监听器
 * 监听设备状态变更事件，更新设备显示信息
 */
class UpdateDeviceDisplayListener
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
            $newStatus = $event->getNewStatus();
            $session = $event->getSession();
            
            Log::info('设备显示更新监听器触发', $event->getEventData());
            
            // 更新设备显示信息
            $updateData = [
                'lastUsedTime' => date('Y-m-d H:i:s'),
                'deviceInfo' => $this->buildDeviceInfo($device, $newStatus, $session)
            ];
            
            // 根据状态类型更新特定字段
            switch ($statusType) {
                case 'online':
                    $updateData['status'] = 'online';
                    break;
                case 'offline':
                    $updateData['status'] = 'offline';
                    break;
                case 'playing':
                    $updateData['status'] = 'playing';
                    $updateData['nowPlaying'] = $this->buildNowPlayingInfo($session);
                    break;
                case 'paused':
                    $updateData['status'] = 'paused';
                    $updateData['nowPlaying'] = $this->buildNowPlayingInfo($session);
                    break;
                case 'stopped':
                    $updateData['status'] = 'online';
                    $updateData['nowPlaying'] = null;
                    break;
            }
            
            // 更新设备信息
            $result = EmbyDeviceModel::where('deviceId', $device['deviceId'])
                ->where('embyId', $event->getUserId())
                ->update($updateData);
            
            if ($result) {
                Log::info('设备显示信息更新成功', [
                    'device_id' => $device['deviceId'],
                    'status_type' => $statusType,
                    'update_data' => $updateData
                ]);
                return true;
            } else {
                Log::warning('设备显示信息更新失败', [
                    'device_id' => $device['deviceId'],
                    'status_type' => $statusType
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('设备显示更新监听器处理失败: ' . $e->getMessage(), [
                'event_data' => $event->getEventData()
            ]);
            return false;
        }
    }
    
    /**
     * �建设备信息
     * 
     * @param array $device 设备信息
     * @param array $status 状态信息
     * @param array|null $session 会话信息
     * @return array
     */
    private function buildDeviceInfo(array $device, array $status, ?array $session): array
    {
        $deviceInfo = $device['deviceInfo'] ?? [];
        
        // 更新状态信息
        $deviceInfo['status'] = $status;
        $deviceInfo['lastUpdate'] = date('Y-m-d H:i:s');
        
        // 添加会话信息
        if ($session) {
            $deviceInfo['session'] = [
                'id' => $session['Id'] ?? '',
                'client' => $session['Client'] ?? '',
                'device_name' => $session['DeviceName'] ?? '',
                'application' => $session['ApplicationVersion'] ?? '',
                'remote_address' => $session['RemoteEndPoint'] ?? '',
                'last_activity' => $session['LastActivityDate'] ?? ''
            ];
        }
        
        return $deviceInfo;
    }
    
    /**
     * 构建正在播放信息
     * 
     * @param array|null $session 会话信息
     * @return array|null
     */
    private function buildNowPlayingInfo(?array $session): ?array
    {
        if (!$session || !isset($session['NowPlayingItem']) || empty($session['NowPlayingItem'])) {
            return null;
        }
        
        $item = $session['NowPlayingItem'];
        $playState = $session['PlayState'] ?? [];
        
        return [
            'item_id' => $item['Id'] ?? '',
            'item_name' => $item['Name'] ?? '',
            'item_type' => $item['Type'] ?? '',
            'series_name' => $item['SeriesName'] ?? '',
            'season_number' => $item['ParentIndexNumber'] ?? null,
            'episode_number' => $item['IndexNumber'] ?? null,
            'play_state' => $playState['State'] ?? 'stopped',
            'position_ticks' => $playState['PositionTicks'] ?? 0,
            'duration_ticks' => $item['RunTimeTicks'] ?? 0,
            'is_paused' => $playState['IsPaused'] ?? false,
            'is_muted' => $playState['IsMuted'] ?? false,
            'volume_level' => $playState['VolumeLevel'] ?? 100,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}