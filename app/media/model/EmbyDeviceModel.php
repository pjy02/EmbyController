<?php

namespace app\media\model;

use think\Model;

class EmbyDeviceModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'emby_device';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'lastUsedTime' => 'timestamp',
        'lastUsedIp' => 'varchar',
        'embyId' => 'varchar',
        'deviceId' => 'varchar',
        'client' => 'varchar',
        'deviceName' => 'varchar',
        'deviceInfo' => 'varchar',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['deviceInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名
    protected $dateFormat = 'Y-m-d H:i:s'; // 时间字段取出后的默认时间格式

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

}