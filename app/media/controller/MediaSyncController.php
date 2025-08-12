<?php

namespace app\media\controller;

use app\media\service\PlaybackMonitoringService;
use think\facade\Log;

class MediaSyncController
{
    /**
     * 播放状态监控服务
     * 
     * @var PlaybackMonitoringService
     */
    protected $monitoringService;
    
    public function __construct()
    {
        $this->monitoringService = new PlaybackMonitoringService();
    }
    
    /**
     * 同步所有在线用户的播放状态
     * 
     * @return array
     */
    public function syncAllUsers()
    {
        try {
            Log::info('开始同步所有在线用户的播放状态');
            
            $result = $this->monitoringService->monitorAllOnlineUsers();
            
            Log::info('同步所有在线用户播放状态完成: ' . json_encode($result));
            
            return [
                'code' => 200,
                'message' => '同步完成',
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            Log::error('同步所有在线用户播放状态失败: ' . $e->getMessage());
            
            return [
                'code' => 500,
                'message' => '同步失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * 同步指定用户的播放状态
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function syncUser($userId)
    {
        try {
            Log::info('开始同步用户播放状态: 用户ID=' . $userId);
            
            $result = $this->monitoringService->monitorUserPlayback($userId);
            
            Log::info('同步用户播放状态完成: 用户ID=' . $userId . ', 结果=' . json_encode($result));
            
            return [
                'code' => 200,
                'message' => '同步完成',
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            Log::error('同步用户播放状态失败: 用户ID=' . $userId . ', 错误=' . $e->getMessage());
            
            return [
                'code' => 500,
                'message' => '同步失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * 清理过期的播放历史记录
     * 
     * @param int $days 保留天数，默认90天
     * @return array
     */
    public function cleanupOldRecords($days = 90)
    {
        try {
            Log::info('开始清理过期的播放历史记录: 保留' . $days . '天');
            
            $model = new \app\media\model\MediaHistoryModel();
            
            // 计算截止日期
            $cutoffDate = date('Y-m-d H:i:s', time() - ($days * 24 * 60 * 60));
            
            // 删除过期记录
            $deletedCount = $model
                ->where('createdAt', '<', $cutoffDate)
                ->delete();
            
            Log::info('清理过期播放历史记录完成: 删除' . $deletedCount . '条记录');
            
            return [
                'code' => 200,
                'message' => '清理完成',
                'data' => [
                    'deletedCount' => $deletedCount,
                    'cutoffDate' => $cutoffDate
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('清理过期播放历史记录失败: ' . $e->getMessage());
            
            return [
                'code' => 500,
                'message' => '清理失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * 获取同步状态统计
     * 
     * @return array
     */
    public function getSyncStats()
    {
        try {
            $model = new \app\media\model\MediaHistoryModel();
            
            // 获取总记录数
            $totalRecords = $model->count();
            
            // 获取今日新增记录数
            $todayStart = date('Y-m-d 00:00:00');
            $todayRecords = $model
                ->where('createdAt', '>=', $todayStart)
                ->count();
            
            // 获取活跃用户数（最近24小时有播放记录的用户）
            $yesterday = date('Y-m-d H:i:s', time() - 24 * 60 * 60);
            $activeUsers = $model
                ->where('createdAt', '>=', $yesterday)
                ->distinct(true)
                ->column('userId');
            
            // 获取最近同步时间
            $latestRecord = $model
                ->order('createdAt', 'desc')
                ->find();
            
            return [
                'code' => 200,
                'message' => '获取统计信息成功',
                'data' => [
                    'totalRecords' => $totalRecords,
                    'todayRecords' => $todayRecords,
                    'activeUsers' => count($activeUsers),
                    'latestSyncTime' => $latestRecord ? $latestRecord['createdAt'] : null
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('获取同步状态统计失败: ' . $e->getMessage());
            
            return [
                'code' => 500,
                'message' => '获取统计信息失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * 手动触发同步（用于测试）
     * 
     * @return array
     */
    public function manualSync()
    {
        try {
            Log::info('手动触发播放状态同步');
            
            // 同步所有在线用户
            $syncResult = $this->syncAllUsers();
            
            // 获取同步后的统计信息
            $statsResult = $this->getSyncStats();
            
            return [
                'code' => 200,
                'message' => '手动同步完成',
                'data' => [
                    'syncResult' => $syncResult['data'],
                    'stats' => $statsResult['data']
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('手动触发同步失败: ' . $e->getMessage());
            
            return [
                'code' => 500,
                'message' => '手动同步失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}