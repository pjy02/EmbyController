<?php

namespace app\media\model;

use think\Model;

class MediaCommentModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'media_comment';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'userId' => 'int',
        'mediaId' => 'varchar',
        'rating' => 'double',
        'comment' => 'text',
        'mentions' => 'text',
        'quotedComment' => 'int',
    ];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

}