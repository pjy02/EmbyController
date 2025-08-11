<?php

namespace app\media\listener;

use app\media\event\DeviceStatusChangedEvent;
use app\media\model\MediaHistoryModel;
use app\media\service\SessionRepository;
use app\media\model\EmbyUserModel;
use think\facade\Log;

/**
 * 会话观看历史记录监听器
 * 监听设备状态变更事件，记录媒体观看历史
 */
class SessionHistoryListener
{
    /**
     * 处理设备状态变更事件，记录观看历史
     * 
     * @param DeviceStatusChangedEvent $event
     * @return bool
     */
    public function handle(DeviceStatusChangedEvent $event): bool
    {
        try {
            $session = $event->getSession();
            $statusType = $event->getStatusType();
            
            // 检查是否有播放内容
            $item = $session['NowPlayingItem'] ?? null;
            if (!$item) {
                return false;
            }
            
            // 获取用户ID
            $userId = $this->getUserIdFromSession($session);
            if (!$userId) {
                return false;
            }
            
            // 简化逻辑：只要有播放记录就直接添加到最近观看
            $mediaHistoryModel = new MediaHistoryModel();
            
            // 查找是否已存在该用户的该媒体记录
            $existingRecord = $mediaHistoryModel->where([
                'userId' => $userId,
                'mediaId' => $item['Id'],
            ])->find();
            
            // 构建观看历史数据
            $historyData = [
                'userId' => $userId,
                'mediaId' => $item['Id'],
                'mediaName' => $item['Name'],
                'mediaYear' => $item['ProductionYear'] ?? '',
                'type' => 1, // 直接设为播放中
                'historyInfo' => json_encode([
                    'session' => $session,
                    'device' => $session['DeviceName'] ?? '未知设备',
                    'status_type' => $statusType,
                    'recorded_at' => date('Y-m-d H:i:s')
                ])
            ];
            
            if ($existingRecord) {
                // 更新现有记录
                $existingRecord->type = 1;
                $existingRecord->historyInfo = $historyData['historyInfo'];
                $result = $existingRecord->save();
            } else {
                // 创建新记录
                $result = $mediaHistoryModel->save($historyData);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('会话观看历史记录监听器处理失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 从会话中获取用户ID
     * 
     * @param array $session 会话信息
     * @return int|null
     */
    private function getUserIdFromSession(array $session): ?int
    {
        try {
            if (!isset($session['UserId'])) {
                return null;
            }
            
            $embyId = $session['UserId'];
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('embyId', $embyId)->find();
            
            return $embyUser ? $embyUser->userId : null;
            
        } catch (\Exception $e) {
            Log::error('从会话获取用户ID失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 查找已存在的观看记录
     * 
     * @param int $userId 用户ID
     * @param string $mediaId 媒体ID
     * @return MediaHistoryModel|null
     */
    private function findExistingRecord(int $userId, string $mediaId): ?MediaHistoryModel
    {
        try {
            // 查找最近24小时内的相同记录
            $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
            
            return MediaHistoryModel::where('userId', $userId)
                ->where('mediaId', $mediaId)
                ->where('createdAt', '>=', $twentyFourHoursAgo)
                ->order('createdAt', 'desc')
                ->find();
                
        } catch (\Exception $e) {
            Log::error('查找已存在观看记录失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新现有记录
     * 
     * @param MediaHistoryModel $record 现有记录
     * @param array $newData 新数据
     * @return bool
     */
    private function updateExistingRecord(MediaHistoryModel $record, array $newData): bool
    {
        try {
            // 更新播放状态和时间
            $record->type = $newData['type'];
            $record->updatedAt = date('Y-m-d H:i:s');
            
            // 合并historyInfo，保留原有信息但更新播放状态
            $existingHistoryInfo = $record->historyInfo ?? [];
            $newHistoryInfo = $newData['historyInfo'];
            
            $record->historyInfo = array_merge($existingHistoryInfo, [
                'playback' => $newHistoryInfo['playback'],
                'status_type' => $newHistoryInfo['status_type'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return $record->save();
            
        } catch (\Exception $e) {
            Log::error('更新现有观看记录失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 清理过期观看历史（保留最近90天）
     * 
     * @return bool
     */
    public function cleanExpiredHistory(): bool
    {
        try {
            $ninetyDaysAgo = date('Y-m-d H:i:s', strtotime('-90 days'));
            
            $result = MediaHistoryModel::where('createdAt', '<', $ninetyDaysAgo)
                ->delete();
            
            Log::info('清理过期观看历史记录完成', [
                'deleted_count' => $result,
                'cutoff_time' => $ninetyDaysAgo
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('清理过期观看历史记录失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取用户观看统计
     * 
     * @param int $userId 用户ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getUserWatchStatistics(int $userId, string $startDate, string $endDate): array
    {
        try {
            $statistics = MediaHistoryModel::where('userId', $userId)
                ->whereBetween('createdAt', [$startDate, $endDate])
                ->field('type, COUNT(*) as count')
                ->group('type')
                ->select()
                ->toArray();
            
            $result = [];
            foreach ($statistics as $stat) {
                $result[$stat['type'] == 1 ? 'playing' : 'paused'] = $stat['count'];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('获取用户观看统计失败: ' . $e->getMessage());
            return [];
        }
    }
}