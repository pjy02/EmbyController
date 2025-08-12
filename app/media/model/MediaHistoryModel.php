<?php

namespace app\media\model;

use think\Model;
use think\facade\Db;

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
     * 获取用户观看历史
     * 
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getUserWatchHistory($userId, $page = 1, $pageSize = 10)
    {
        try {
            return $this->where('userId', $userId)
                ->order('updatedAt', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('获取用户观看历史失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取用户正在观看的内容
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getNowWatching($userId)
    {
        try {
            // 获取最近5分钟内有活动的播放记录
            $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            
            return $this->where('userId', $userId)
                ->where('type', 1) // 正在播放
                ->where('updatedAt', '>=', $fiveMinutesAgo)
                ->order('updatedAt', 'desc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('获取用户正在观看内容失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取用户观看历史总数
     * 
     * @param int $userId 用户ID
     * @return int
     */
    public function getUserWatchHistoryCount($userId)
    {
        try {
            return $this->where('userId', $userId)->count();
        } catch (\Exception $e) {
            Log::error('获取用户观看历史总数失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取用户最近观看的媒体
     * 
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array
     */
    public function getRecentMedia($userId, $limit = 10)
    {
        try {
            return $this->where('userId', $userId)
                ->field('mediaId, mediaName, mediaYear, MAX(updatedAt) as lastWatched')
                ->group('mediaId')
                ->order('lastWatched', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('获取用户最近观看媒体失败: ' . $e->getMessage());
            return [];
        }
    }

}