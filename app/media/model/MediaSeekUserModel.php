<?php
namespace app\media\model;

use think\Model;

class MediaSeekUserModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'media_seek_user';
    
    // 设置字段信息
    protected $schema = [
        'id'        => 'int',
        'seekId'    => 'int',
        'userId'    => 'int',
        'createdAt' => 'datetime'
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createdAt';
    protected $updateTime = false;

    // 关联求片表
    public function seek()
    {
        return $this->belongsTo(MediaSeekModel::class, 'seekId', 'id');
    }

    // 关联用户表
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'userId', 'id');
    }

    // 获取用户的同求列表
    public function getUserSeekList($userId, $page = 1, $pageSize = 10)
    {
        return $this->with(['seek'])
            ->where('userId', $userId)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();
    }

    // 获取求片的同求用户列表
    public function getSeekUserList($seekId, $page = 1, $pageSize = 10)
    {
        return $this->with(['user'])
            ->where('seekId', $seekId)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();
    }

    // 检查用户是否已同求
    public function checkUserSeek($userId, $seekId)
    {
        return $this->where([
            'userId' => $userId,
            'seekId' => $seekId
        ])->find();
    }

    // 获取求片的同求人数
    public function getSeekCount($seekId)
    {
        return $this->where('seekId', $seekId)->count();
    }

    // 批量获取求片的同求人数
    public function getSeekCounts($seekIds)
    {
        $counts = $this->where('seekId', 'in', $seekIds)
            ->group('seekId')
            ->column('count(*)', 'seekId');
            
        $result = [];
        foreach ($seekIds as $seekId) {
            $result[$seekId] = $counts[$seekId] ?? 0;
        }
        return $result;
    }

    // 添加同求记录并记录日志
    public function addSeekUser($seekId, $userId)
    {
        $this->startTrans();
        try {
            // 保存同求记录
            $this->save([
                'seekId' => $seekId,
                'userId' => $userId
            ]);

            // 更新求片记录的同求人数
            $seekModel = new MediaSeekModel();
            $seek = $seekModel->where('id', $seekId)->find();
            $seek->seekCount = $seek->seekCount + 1;
            $seek->save();

            // 记录同求日志
            $logModel = new MediaSeekLogModel();
            $logModel->save([
                'seekId' => $seekId,
                'type' => 2,
                'content' => json_encode([
                    'userId' => $userId
                ])
            ]);

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }
} 