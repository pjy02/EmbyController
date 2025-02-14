<?php
namespace app\media\model;

use think\Model;

class MediaSeekModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'media_seek';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'userId'      => 'int',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'int',
        'statusRemark'=> 'string',
        'seekCount'   => 'int',
        'createdAt'   => 'datetime',
        'updatedAt'   => 'datetime',
        'downloadId'  => 'string'
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    // 关联用户表
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'userId', 'id');
    }

    // 关联同求用户表
    public function seekUsers()
    {
        return $this->hasMany(MediaSeekUserModel::class, 'seekId', 'id');
    }

    // 关联状态日志表
    public function statusLogs()
    {
        return $this->hasMany(MediaSeekLogModel::class, 'seekId', 'id')
            ->order('createdAt', 'asc');
    }

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            0  => '已请求',
            1  => '管理员已确认',
            2  => '正在收集资源',
            3  => '已入库',
            -1 => '暂不收录'
        ];
        return $status[$data['status']] ?? '未知状态';
    }

    // 检查用户是否已同求
    public function checkUserSeek($userId, $seekId)
    {
        return MediaSeekUserModel::where([
            'userId' => $userId,
            'seekId' => $seekId
        ])->find();
    }

    // 获取用户的求片列表
    public function getUserSeekList($userId, $page = 1, $pageSize = 10)
    {
        return $this->where('userId', $userId)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();
    }

    // 获取热门求片列表（同求人数最多的）
    public function getHotSeekList($limit = 10)
    {
        return $this->where('status', 'in', [0, 1, 2])
            ->order('seekCount', 'desc')
            ->limit($limit)
            ->select();
    }

    // 更新求片状态
    public function updateStatus($id, $status, $remark = '')
    {
        $this->startTrans();
        try {
            // 更新状态
            $result = $this->where('id', $id)->update([
                'status' => $status,
                'statusRemark' => $remark
            ]);
            
            if ($result) {
                // 记录状态变更日志
                $logModel = new MediaSeekLogModel();
                $logModel->save([
                    'seekId' => $id,
                    'type' => 3,
                    'content' => json_encode([
                        'status' => $status,
                        'remark' => $remark
                    ])
                ]);

                // 获取求片信息
                $seek = $this->where('id', $id)->find();
                
                // 获取所有同求用户
                $seekUserModel = new MediaSeekUserModel();
                $seekUsers = $seekUserModel->where('seekId', $id)->select();
                
                // 发送通知给发起人
                sendStationMessage($seek->userId, "您的求片《{$seek->title}》状态已更新为：" . $this->getStatusTextAttr(null, ['status' => $status]));
                
                // 发送通知给所有同求用户
                foreach ($seekUsers as $seekUser) {
                    if ($seekUser->userId != $seek->userId) { // 避免重复发送给发起人
                        sendStationMessage($seekUser->userId, "您同求的影片《{$seek->title}》状态已更新为：" . $this->getStatusTextAttr(null, ['status' => $status]));
                    }
                }
            }
            
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    // 检查用户是否有权限使用自动下载
    public function checkAutoDownloadPermission($userId)
    {
        $userModel = new \app\media\model\UserModel();
        $user = $userModel->where('id', $userId)->find();
        if (!$user) {
            return false;
        }
        
        // 权限为0或80以上可以使用自动下载
        return $user->authority === 0 || $user->authority > 80;
    }

    // 创建求片并记录日志
    public function createSeek($data)
    {
        $this->startTrans();
        try {
            // 检查是否有自动下载权限
            $canAutoDownload = $this->checkAutoDownloadPermission($data['userId']);
            
            // 保存求片记录
            $this->save([
                'userId' => $data['userId'],
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => $data['status'] ?? ($canAutoDownload ? 1 : 0),  // 有权限时默认状态为已确认
                'statusRemark' => $data['statusRemark'] ?? ($canAutoDownload ? '系统自动确认' : '等待管理员处理'),
                'seekCount' => 1,
                'downloadId' => $data['downloadId'] ?? null  // 添加下载ID字段
            ]);

            // 记录创建日志
            $logModel = new MediaSeekLogModel();
            
            // 1. 记录发起求片的日志
            $logModel->save([
                'seekId' => $this->id,
                'type' => 1,
                'content' => json_encode([
                    'userId' => $data['userId'],
                    'title' => $data['title'],
                    'action' => 'create'
                ])
            ]);

            // 2. 如果状态不是默认的0，添加状态变更日志
            if (($data['status'] ?? 0) !== 0) {
                $logModel->save([
                    'seekId' => $this->id,
                    'type' => 3,
                    'content' => json_encode([
                        'status' => $data['status'],
                        'remark' => $data['statusRemark'] ?? '',
                        'action' => 'status_change',
                        'operator' => $canAutoDownload ? 'system' : 'admin'
                    ])
                ]);

                // 发送通知
                $message = $canAutoDownload ? 
                    "您的求片《{$data['title']}》已自动确认并开始处理" : 
                    "您的求片《{$data['title']}》已提交，请等待管理员处理";
                sendStationMessage($data['userId'], $message);
            }

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            trace("创建求片记录失败: " . $e->getMessage(), 'error');
            return false;
        }
    }
} 