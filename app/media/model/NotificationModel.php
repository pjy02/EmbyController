<?php

namespace app\media\model;

use think\Model;

class NotificationModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'notification';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'type' => 'int',  // 0系统通知 1用户消息
        'readStatus' => 'int',  // 0未读 1已读
        'fromUserId' => 'int',
        'toUserId' => 'int',
        'message' => 'text',
        'notificationInfo' => 'text',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['notificationInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

}