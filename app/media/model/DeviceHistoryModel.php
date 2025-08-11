<?php

namespace app\media\model;

use think\Model;

/**
 * 设备状态历史记录模型
 * 用于存储设备状态变更历史数据
 */
class DeviceHistoryModel extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'device_history';
    
    /**
     * 主键
     * 
     * @var string
     */
    protected $pk = 'id';
    
    /**
     * 自动时间戳
     * 
     * @var bool
     */
    protected $autoWriteTimestamp = false;
    
    /**
     * 字段类型
     * 
     * @var array
     */
    protected $schema = [
        'id' => 'int',
        'deviceId' => 'string',
        'embyId' => 'string',
        'statusType' => 'string',
        'oldStatus' => 'json',
        'newStatus' => 'json',
        'sessionId' => 'string',
        'client' => 'string',
        'deviceName' => 'string',
        'ip' => 'string',
        'changeTime' => 'datetime',
        'additionalInfo' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * JSON字段
     * 
     * @var array
     */
    protected $json = [
        'oldStatus',
        'newStatus',
        'additionalInfo'
    ];
    
    /**
     * JSON字段返回数组
     * 
     * @var array
     */
    protected $jsonAssoc = true;
    
    /**
     * 获取设备的历史记录
     * 
     * @param string $deviceId 设备ID
     * @param int $limit 限制数量
     * @param string $order 排序方式
     * @return array
     */
    public function getDeviceHistory(string $deviceId, int $limit = 50, string $order = 'DESC'): array
    {
        return $this->where('deviceId', $deviceId)
            ->order('changeTime', $order)
            ->limit($limit)
            ->select()
            ->toArray();
    }
    
    /**
     * 获取用户的所有设备历史记录
     * 
     * @param string $embyId Emby用户ID
     * @param int $limit 限制数量
     * @param string $order 排序方式
     * @return array
     */
    public function getUserDeviceHistory(string $embyId, int $limit = 100, string $order = 'DESC'): array
    {
        return $this->where('embyId', $embyId)
            ->order('changeTime', $order)
            ->limit($limit)
            ->select()
            ->toArray();
    }
    
    /**
     * 获取指定时间段内的历史记录
     * 
     * @param string $deviceId 设备ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getHistoryByDateRange(string $deviceId, string $startDate, string $endDate): array
    {
        return $this->where('deviceId', $deviceId)
            ->whereBetween('changeTime', [$startDate, $endDate])
            ->order('changeTime', 'DESC')
            ->select()
            ->toArray();
    }
    
    /**
     * 获取设备的状态变更统计
     * 
     * @param string $deviceId 设备ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getStatusChangeStatistics(string $deviceId, string $startDate, string $endDate): array
    {
        return $this->where('deviceId', $deviceId)
            ->whereBetween('changeTime', [$startDate, $endDate])
            ->field('statusType, COUNT(*) as count')
            ->group('statusType')
            ->select()
            ->toArray();
    }
    
    /**
     * 获取设备在线时长统计
     * 
     * @param string $deviceId 设备ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getOnlineTimeStatistics(string $deviceId, string $startDate, string $endDate): array
    {
        $history = $this->where('deviceId', $deviceId)
            ->whereBetween('changeTime', [$startDate, $endDate])
            ->whereIn('statusType', ['online', 'offline'])
            ->order('changeTime', 'ASC')
            ->select()
            ->toArray();
        
        $totalOnlineTime = 0;
        $currentOnlineTime = 0;
        $lastOnlineTime = null;
        
        foreach ($history as $record) {
            if ($record['statusType'] === 'online') {
                $lastOnlineTime = strtotime($record['changeTime']);
            } elseif ($record['statusType'] === 'offline' && $lastOnlineTime) {
                $offlineTime = strtotime($record['changeTime']);
                $currentOnlineTime = $offlineTime - $lastOnlineTime;
                $totalOnlineTime += $currentOnlineTime;
                $lastOnlineTime = null;
            }
        }
        
        // 如果设备当前在线，计算到现在的时间
        if ($lastOnlineTime) {
            $totalOnlineTime += time() - $lastOnlineTime;
        }
        
        return [
            'total_online_seconds' => $totalOnlineTime,
            'total_online_hours' => round($totalOnlineTime / 3600, 2),
            'total_online_days' => round($totalOnlineTime / 86400, 2)
        ];
    }
    
    /**
     * 获取设备播放统计
     * 
     * @param string $deviceId 设备ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getPlaybackStatistics(string $deviceId, string $startDate, string $endDate): array
    {
        $history = $this->where('deviceId', $deviceId)
            ->whereBetween('changeTime', [$startDate, $endDate])
            ->whereIn('statusType', ['playing', 'paused', 'stopped'])
            ->order('changeTime', 'ASC')
            ->select()
            ->toArray();
        
        $totalPlayTime = 0;
        $currentPlayTime = 0;
        $lastPlayStartTime = null;
        $playCount = 0;
        
        foreach ($history as $record) {
            if ($record['statusType'] === 'playing') {
                if (!$lastPlayStartTime) {
                    $lastPlayStartTime = strtotime($record['changeTime']);
                    $playCount++;
                }
            } elseif ($record['statusType'] === 'stopped' && $lastPlayStartTime) {
                $stopTime = strtotime($record['changeTime']);
                $currentPlayTime = $stopTime - $lastPlayStartTime;
                $totalPlayTime += $currentPlayTime;
                $lastPlayStartTime = null;
            }
        }
        
        // 如果设备当前正在播放，计算到现在的时间
        if ($lastPlayStartTime) {
            $totalPlayTime += time() - $lastPlayStartTime;
        }
        
        return [
            'total_play_seconds' => $totalPlayTime,
            'total_play_hours' => round($totalPlayTime / 3600, 2),
            'total_play_days' => round($totalPlayTime / 86400, 2),
            'play_count' => $playCount,
            'average_play_time' => $playCount > 0 ? round($totalPlayTime / $playCount, 2) : 0
        ];
    }
    
    /**
     * 清理过期历史记录
     * 
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public function cleanExpiredHistory(int $days = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->where('changeTime', '<', $cutoffDate)->delete();
    }
}