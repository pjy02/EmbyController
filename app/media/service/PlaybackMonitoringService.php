<?php

namespace app\media\service;

use app\media\model\MediaHistoryModel;
use app\media\model\EmbyUserModel;
use think\facade\Log;
use think\facade\Db;

/**
 * 播放状态监控服务
 * 负责监控用户设备的播放状态并记录到媒体历史表
 */
class PlaybackMonitoringService
{
    /**
     * 会话仓库
     * @var SessionRepository
     */
    private $sessionRepository;
    
    /**
     * 媒体历史模型
     * @var MediaHistoryModel
     */
    private $mediaHistoryModel;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->sessionRepository = new SessionRepository();
        $this->mediaHistoryModel = new MediaHistoryModel();
    }
    
    /**
     * 监控用户播放状态
     * 
     * @param string $embyId Emby用户ID
     * @return array
     */
    public function monitorUserPlayback($embyId)
    {
        try {
            Log::info('开始监控用户播放状态: ' . $embyId);
            
            // 获取用户活跃会话
            $activeSessions = $this->sessionRepository->getActiveSessions($embyId);
            
            $recordedCount = 0;
            foreach ($activeSessions as $session) {
                // 检查是否有正在播放的媒体
                if ($this->isCurrentlyPlaying($session)) {
                    // 记录到media_history
                    $result = $this->recordPlaybackToMediaHistory($session, $embyId);
                    if ($result) {
                        $recordedCount++;
                    }
                }
            }
            
            Log::info('用户播放状态监控完成: ' . $embyId . ', 记录数量: ' . $recordedCount);
            
            return [
                'success' => true,
                'recorded_count' => $recordedCount,
                'active_sessions' => count($activeSessions)
            ];
            
        } catch (\Exception $e) {
            Log::error('监控用户播放状态失败: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 检查会话是否正在播放
     * 
     * @param array $session 会话信息
     * @return bool
     */
    private function isCurrentlyPlaying($session)
    {
        // 检查是否有正在播放的媒体
        if (!isset($session['NowPlayingItem']) || empty($session['NowPlayingItem'])) {
            return false;
        }
        
        // 检查会话是否最近活跃（5分钟内）
        if (isset($session['LastActivityDate'])) {
            $lastActivity = strtotime($session['LastActivityDate']);
            $fiveMinutesAgo = time() - 300; // 5分钟前
            if ($lastActivity < $fiveMinutesAgo) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 记录播放状态到媒体历史表
     * 
     * @param array $session 会话信息
     * @param string $embyId Emby用户ID
     * @return bool
     */
    private function recordPlaybackToMediaHistory($session, $embyId)
    {
        try {
            $nowPlayingItem = $session['NowPlayingItem'] ?? null;
            if (!$nowPlayingItem) {
                return false;
            }
            
            // 获取本地用户ID
            $embyUserModel = new EmbyUserModel();
            $embyUser = $embyUserModel->where('embyId', $embyId)->find();
            if (!$embyUser) {
                Log::warning('未找到对应的本地用户: ' . $embyId);
                return false;
            }
            
            $userId = $embyUser->userId;
            $mediaId = $nowPlayingItem['Id'] ?? '';
            
            // 检查是否需要记录（避免重复记录）
            if (!$this->shouldRecord($userId, $mediaId)) {
                return false;
            }
            
            // 构建历史记录数据
            $data = [
                'userId' => $userId,
                'mediaId' => $mediaId,
                'mediaName' => $nowPlayingItem['Name'] ?? '未知媒体',
                'mediaYear' => $nowPlayingItem['ProductionYear'] ?? '',
                'type' => $this->getPlaybackType($session),
                'historyInfo' => [
                    'item' => $nowPlayingItem,
                    'playState' => $session['PlayState'] ?? [],
                    'sessionInfo' => [
                        'deviceId' => $session['DeviceId'] ?? '',
                        'client' => $session['Client'] ?? '',
                        'lastActivityDate' => $session['LastActivityDate'] ?? ''
                    ],
                    'percentage' => $this->calculateProgress($session)
                ]
            ];
            
            // 创建记录
            $result = $this->mediaHistoryModel->create($data);
            
            if ($result) {
                Log::info('成功记录播放历史: 用户ID=' . $userId . ', 媒体ID=' . $mediaId);
                return true;
            } else {
                Log::error('记录播放历史失败: 用户ID=' . $userId . ', 媒体ID=' . $mediaId);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('记录播放状态到媒体历史表失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取播放类型
     * 
     * @param array $session 会话信息
     * @return int
     */
    private function getPlaybackType($session)
    {
        $playState = $session['PlayState'] ?? [];
        
        // 检查是否暂停
        if (isset($playState['IsPaused']) && $playState['IsPaused']) {
            return 2; // 已暂停
        }
        
        // 检查是否有播放进度
        if (isset($playState['PositionTicks']) && $playState['PositionTicks'] > 0) {
            return 1; // 正在播放
        }
        
        return 3; // 已停止
    }
    
    /**
     * 计算播放进度
     * 
     * @param array $session 会话信息
     * @return float
     */
    private function calculateProgress($session)
    {
        try {
            $playState = $session['PlayState'] ?? [];
            $nowPlayingItem = $session['NowPlayingItem'] ?? [];
            
            if (!isset($playState['PositionTicks']) || !isset($nowPlayingItem['RunTimeTicks'])) {
                return 0;
            }
            
            $position = $playState['PositionTicks'];
            $runtime = $nowPlayingItem['RunTimeTicks'];
            
            if ($runtime <= 0) {
                return 0;
            }
            
            return round($position / $runtime, 4);
            
        } catch (\Exception $e) {
            Log::error('计算播放进度失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 检查是否需要记录（避免重复记录）
     * 
     * @param int $userId 用户ID
     * @param string $mediaId 媒体ID
     * @return bool
     */
    private function shouldRecord($userId, $mediaId)
    {
        try {
            // 检查5分钟内是否有相同媒体的记录
            $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);
            
            $recentRecord = $this->mediaHistoryModel
                ->where('userId', $userId)
                ->where('mediaId', $mediaId)
                ->where('createdAt', '>=', $fiveMinutesAgo)
                ->find();
                
            return !$recentRecord;
            
        } catch (\Exception $e) {
            Log::error('检查是否需要记录失败: ' . $e->getMessage());
            return true; // 出错时默认记录
        }
    }
    
    /**
     * 获取所有在线用户的播放状态
     * 
     * @return array
     */
    public function monitorAllOnlineUsers()
    {
        try {
            Log::info('开始监控所有在线用户的播放状态');
            
            // 获取所有有活跃会话的用户
            $allSessions = $this->sessionRepository->getAllSessions();
            $userSessions = [];
            
            foreach ($allSessions as $session) {
                if (isset($session['UserId']) && !empty($session['UserId'])) {
                    $userId = $session['UserId'];
                    if (!isset($userSessions[$userId])) {
                        $userSessions[$userId] = [];
                    }
                    $userSessions[$userId][] = $session;
                }
            }
            
            $results = [];
            foreach ($userSessions as $embyId => $sessions) {
                $result = $this->monitorUserPlayback($embyId);
                $results[$embyId] = $result;
            }
            
            Log::info('所有在线用户播放状态监控完成, 用户数量: ' . count($results));
            
            return [
                'success' => true,
                'results' => $results,
                'user_count' => count($results)
            ];
            
        } catch (\Exception $e) {
            Log::error('监控所有在线用户播放状态失败: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}