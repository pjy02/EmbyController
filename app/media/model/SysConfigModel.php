<?php

namespace app\media\model;

use think\Model;

class SysConfigModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'config';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'appName' => 'varchar',
        'key' => 'varchar',
        'value' => 'text',
        'type' => 'int',
        'status' => 'int',
    ];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

}