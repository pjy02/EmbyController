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
            $newStatus = $event->getNewStatus();
            
            // 只处理播放相关的事件
            if (!in_array($statusType, ['playing', 'paused', 'stopped'])) {
                return false;
            }
            
            // 检查是否有播放内容
            if (!isset($session['NowPlayingItem']) || empty($session['NowPlayingItem'])) {
                return false;
            }
            
            Log::info('会话观看历史记录监听器触发', [
                'session_id' => $session['Id'] ?? '',
                'status_type' => $statusType,
                'media_name' => $session['NowPlayingItem']['Name'] ?? ''
            ]);
            
            // 获取用户ID
            $userId = $this->getUserIdFromSession($session);
            if (!$userId) {
                Log::warning('无法从会话中获取用户ID', [
                    'session_id' => $session['Id'] ?? ''
                ]);
                return false;
            }
            
            // 获取播放信息
            $sessionRepository = new SessionRepository();
            $playbackInfo = $sessionRepository->getSessionPlaybackInfo($session);
            
            // 构建观看历史数据
            $historyData = [
                'userId' => $userId,
                'mediaId' => $session['NowPlayingItem']['Id'],
                'mediaName' => $session['NowPlayingItem']['Name'],
                'mediaYear' => $session['NowPlayingItem']['ProductionYear'] ?? '',
                'type' => $playbackInfo['is_playing'] ? 1 : 0,
                'historyInfo' => [
                    'session' => $session,
                    'playback' => $playbackInfo,
                    'device' => $session['DeviceName'] ?? '未知设备',
                    'status_type' => $statusType,
                    'recorded_at' => date('Y-m-d H:i:s')
                ]
            ];
            
            // 检查是否已存在相同的观看记录（避免重复）
            $existingRecord = $this->findExistingRecord($userId, $session['NowPlayingItem']['Id']);
            
            if ($existingRecord) {
                // 更新现有记录
                $result = $this->updateExistingRecord($existingRecord, $historyData);
            } else {
                // 创建新记录
                $result = MediaHistoryModel::create($historyData);
            }
            
            if ($result) {
                Log::info('观看历史记录保存成功', [
                    'user_id' => $userId,
                    'media_id' => $session['NowPlayingItem']['Id'],
                    'media_name' => $session['NowPlayingItem']['Name'],
                    'status_type' => $statusType
                ]);
                return true;
            } else {
                Log::warning('观看历史记录保存失败', [
                    'user_id' => $userId,
                    'media_id' => $session['NowPlayingItem']['Id']
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('会话观看历史记录监听器处理失败: ' . $e->getMessage(), [
                'session_id' => $session['Id'] ?? '',
                'status_type' => $statusType
            ]);
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