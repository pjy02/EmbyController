<?php

declare(strict_types=1);

namespace app\command;

use app\media\event\DeviceStatusChangedEvent;
use app\media\event\EventDispatcher;
use app\media\model\EmbyDeviceModel;
use app\media\service\DeviceManagementService;
use app\media\service\SessionRepository;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Log;

/**
 * 设备状态同步命令
 * 定时同步设备状态，确保设备信息与Emby服务器保持一致
 */
class SyncDeviceStatus extends Command
{
    /**
     * 配置命令
     */
    protected function configure()
    {
        $this->setName('sync:device-status')
            ->setDescription('同步设备状态，确保设备信息与Emby服务器保持一致');
    }
    
    /**
     * 执行命令
     * 
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        try {
            $output->writeln('开始同步设备状态...');
            
            $deviceService = new DeviceManagementService();
            $sessionRepository = new SessionRepository();
            $eventDispatcher = new EventDispatcher();
            
            // 注册事件监听器
            $eventDispatcher->listen(DeviceStatusChangedEvent::class, 
                \app\media\listener\UpdateDeviceDisplayListener::class
            );
            $eventDispatcher->listen(DeviceStatusChangedEvent::class, 
                \app\media\listener\DeviceHistoryListener::class
            );
            
            // 获取所有活跃设备
            $activeDevices = EmbyDeviceModel::where('deactivate', 0)
                ->where('lastUsedTime', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
                ->select()
                ->toArray();
            
            $output->writeln('找到 ' . count($activeDevices) . ' 个活跃设备');
            
            $syncCount = 0;
            $updateCount = 0;
            
            foreach ($activeDevices as $device) {
                try {
                    $output->writeln("正在同步设备: {$device['deviceName']} ({$device['deviceId']})");
                    
                    // 获取设备对应的会话
                    $session = $sessionRepository->getSessionByDeviceId($device['deviceId']);
                    
                    if ($session) {
                        // 设备有活跃会话
                        $oldStatus = $this->getCurrentDeviceStatus($device);
                        $newStatus = $this->getSessionStatus($session);
                        
                        // 检查状态是否发生变化
                        if ($this->hasStatusChanged($oldStatus, $newStatus)) {
                            $statusType = $this->determineStatusType($oldStatus, $newStatus);
                            
                            // 触发状态变更事件
                            $event = new DeviceStatusChangedEvent(
                                $device,
                                $statusType,
                                $oldStatus,
                                $newStatus,
                                $device['embyId'],
                                $session
                            );
                            
                            $eventDispatcher->dispatch($event);
                            $updateCount++;
                            
                            $output->writeln("设备状态已更新: {$statusType}");
                        } else {
                            $output->writeln("设备状态未发生变化");
                        }
                        
                        $syncCount++;
                    } else {
                        // 设备没有活跃会话，检查是否需要设置为离线
                        $lastActivity = strtotime($device['lastUsedTime']);
                        $fiveMinutesAgo = time() - 300; // 5分钟前
                        
                        if ($lastActivity < $fiveMinutesAgo) {
                            $oldStatus = $this->getCurrentDeviceStatus($device);
                            $newStatus = ['status' => 'offline', 'lastUpdate' => date('Y-m-d H:i:s')];
                            
                            if ($oldStatus['status'] !== 'offline') {
                                // 触发离线事件
                                $event = new DeviceStatusChangedEvent(
                                    $device,
                                    'offline',
                                    $oldStatus,
                                    $newStatus,
                                    $device['embyId']
                                );
                                
                                $eventDispatcher->dispatch($event);
                                $updateCount++;
                                
                                $output->writeln("设备已设置为离线状态");
                            }
                        }
                    }
                    
                } catch (\Exception $e) {
                    $output->writeln("同步设备失败: {$e->getMessage()}");
                    Log::error('同步设备状态失败', [
                        'device_id' => $device['deviceId'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            // 清理缓存
            Cache::clear();
            
            $output->writeln("同步完成! 共同步 {$syncCount} 个设备，更新 {$updateCount} 个设备状态");
            
            Log::info('设备状态同步完成', [
                'sync_count' => $syncCount,
                'update_count' => $updateCount
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            $output->writeln("同步失败: {$e->getMessage()}");
            Log::error('设备状态同步命令执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * 获取设备当前状态
     * 
     * @param array $device 设备信息
     * @return array
     */
    private function getCurrentDeviceStatus(array $device): array
    {
        $deviceInfo = $device['deviceInfo'] ?? [];
        
        return [
            'status' => $deviceInfo['status']['status'] ?? 'unknown',
            'lastUpdate' => $deviceInfo['status']['lastUpdate'] ?? $device['lastUsedTime'],
            'sessionId' => $deviceInfo['session']['id'] ?? null,
            'client' => $deviceInfo['session']['client'] ?? $device['client'],
            'deviceName' => $deviceInfo['session']['device_name'] ?? $device['deviceName']
        ];
    }
    
    /**
     * 获取会话状态
     * 
     * @param array $session 会话信息
     * @return array
     */
    private function getSessionStatus(array $session): array
    {
        $playbackInfo = $this->getPlaybackInfo($session);
        
        return [
            'status' => $playbackInfo['is_playing'] ? 'playing' : 'online',
            'lastUpdate' => date('Y-m-d H:i:s'),
            'sessionId' => $session['Id'],
            'client' => $session['Client'] ?? '',
            'deviceName' => $session['DeviceName'] ?? '',
            'playbackInfo' => $playbackInfo
        ];
    }
    
    /**
     * 获取播放信息
     * 
     * @param array $session 会话信息
     * @return array
     */
    private function getPlaybackInfo(array $session): array
    {
        $playbackInfo = [
            'is_playing' => false,
            'item_name' => '',
            'item_type' => '',
            'position' => 0,
            'duration' => 0,
            'percentage' => 0,
            'state' => 'stopped'
        ];
        
        if (isset($session['NowPlayingItem']) && !empty($session['NowPlayingItem'])) {
            $item = $session['NowPlayingItem'];
            $playbackInfo['is_playing'] = true;
            $playbackInfo['item_name'] = $item['Name'] ?? '';
            $playbackInfo['item_type'] = $item['Type'] ?? '';
            
            if (isset($session['PlayState'])) {
                $playState = $session['PlayState'];
                $positionTicks = $playState['PositionTicks'] ?? 0;
                $durationTicks = $item['RunTimeTicks'] ?? 0;
                
                $playbackInfo['position'] = $positionTicks / 10000000;
                $playbackInfo['duration'] = $durationTicks / 10000000;
                
                if ($durationTicks > 0) {
                    $playbackInfo['percentage'] = $positionTicks / $durationTicks;
                }
                
                $playbackInfo['state'] = $playState['State'] ?? 'stopped';
            }
        }
        
        return $playbackInfo;
    }
    
    /**
     * 检查状态是否发生变化
     * 
     * @param array $oldStatus 旧状态
     * @param array $newStatus 新状态
     * @return bool
     */
    private function hasStatusChanged(array $oldStatus, array $newStatus): bool
    {
        return $oldStatus['status'] !== $newStatus['status'] ||
               $oldStatus['sessionId'] !== $newStatus['sessionId'] ||
               $oldStatus['client'] !== $newStatus['client'] ||
               $oldStatus['deviceName'] !== $newStatus['deviceName'];
    }
    
    /**
     * 确定状态变更类型
     * 
     * @param array $oldStatus 旧状态
     * @param array $newStatus 新状态
     * @return string
     */
    private function determineStatusType(array $oldStatus, array $newStatus): string
    {
        $oldStatusName = $oldStatus['status'];
        $newStatusName = $newStatus['status'];
        
        if ($oldStatusName === $newStatusName) {
            return 'update'; // 信息更新但状态未变
        }
        
        // 状态变更映射
        $statusMap = [
            'unknown' => ['online' => 'online', 'playing' => 'playing', 'offline' => 'offline'],
            'offline' => ['online' => 'online', 'playing' => 'playing'],
            'online' => ['playing' => 'playing', 'offline' => 'offline'],
            'playing' => ['online' => 'stopped', 'offline' => 'offline']
        ];
        
        return $statusMap[$oldStatusName][$newStatusName] ?? 'update';
    }
}