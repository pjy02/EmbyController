<?php

namespace app\api\model;

use think\Model;

class BetModel extends Model
{
    protected $name = 'bet';
    protected $pk = 'id';

    protected $schema = [
        'id' => 'int',
        'chatId' => 'varchar',
        'creatorId' => 'varchar',
        'status' => 'int',
        'randomType' => 'varchar',
        'result' => 'varchar',
        'createTime' => 'datetime',
        'endTime' => 'datetime',
    ];

    // 自动转换数据类型
    protected $type = [
        'createTime' => 'datetime'
    ];

    // 自动完成
    protected $auto = ['createTime'];
}