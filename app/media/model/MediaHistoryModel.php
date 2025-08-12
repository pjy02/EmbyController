<?php

namespace app\media\model;

use think\Model;
use think\facade\Log;

class MediaHistoryModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'media_history';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'type' => 'int',
        'userId' => 'int',
        'mediaId' => 'varchar',
        'mediaName' => 'varchar',
        'mediaYear' => 'varchar',
        'historyInfo' => 'text',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['historyInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';
    
    /**
     * 从会话信息记录播放历史
     * 
     * @param array $session 会话信息
     * @param int $userId 用户ID
     * @return bool|Model
     */
    public function recordFromSession($session, $userId)
    {
        try {
            $nowPlayingItem = $session['NowPlayingItem'] ?? null;
            if (!$nowPlayingItem) {
                Log::warning('会话中没有正在播放的媒体项目');
                return false;
            }
            
            $mediaId = $nowPlayingItem['Id'] ?? '';
            if (empty($mediaId)) {
                Log::warning('媒体ID为空');
                return false;
            }
            
            // 检查是否需要记录（避免重复记录）
            if (!$this->shouldRecord($userId, $mediaId)) {
                Log::info('5分钟内已有相同媒体的记录，跳过记录: 用户ID=' . $userId . ', 媒体ID=' . $mediaId);
                return false;
            }
            
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
            
            $result = $this->create($data);
            
            if ($result) {
                Log::info('成功从会话记录播放历史: 用户ID=' . $userId . ', 媒体ID=' . $mediaId);
                return $result;
            } else {
                Log::error('从会话记录播放历史失败: 用户ID=' . $userId . ', 媒体ID=' . $mediaId);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('从会话记录播放历史异常: ' . $e->getMessage());
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
            
            $recentRecord = $this
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
     * 获取用户的最近观看记录（带类型文本）
     * 
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getLatestSeenWithTypeText($userId, $page = 1, $pageSize = 10)
    {
        try {
            $records = $this
                ->where('userId', $userId)
                ->order('updatedAt', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();
            
            // 添加类型文本描述
            foreach ($records as &$record) {
                $record['typeText'] = $this->getTypeText($record['type']);
            }
            
            return $records;
            
        } catch (\Exception $e) {
            Log::error('获取用户最近观看记录失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取类型文本描述
     * 
     * @param int $type 类型
     * @return string
     */
    private function getTypeText($type)
    {
        switch ($type) {
            case 1:
                return '正在播放';
            case 2:
                return '已暂停';
            case 3:
                return '已停止';
            default:
                return '未知状态';
        }
    }
    
    /**
     * 获取用户最近观看记录总数
     * 
     * @param int $userId 用户ID
     * @return int
     */
    public function getUserLatestSeenCount($userId)
    {
        try {
            return $this->where('userId', $userId)->count();
        } catch (\Exception $e) {
            Log::error('获取用户最近观看记录总数失败: ' . $e->getMessage());
            return 0;
        }
    }

}