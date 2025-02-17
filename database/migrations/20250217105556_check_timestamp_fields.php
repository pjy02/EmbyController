<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CheckTimestampFields extends Migrator
{
    public function up()
    {
        // 检查并修正时间字段
        $tables = [
            'bet' => ['createTime', 'endTime'],
            'lottery' => ['createTime', 'drawTime'],
            'lottery_participant' => ['createTime'],
            'media_seek' => ['createdAt', 'updatedAt'],
            'media_seek_log' => ['createdAt'],
            'media_seek_user' => ['createdAt'],
            // ... 其他表的时间字段检查
        ];

        foreach ($tables as $table => $fields) {
            if ($this->hasTable($table)) {
                $this->table($table)->save();
            }
        }
    }

    public function down()
    {
        // 回滚不需要操作
    }
}