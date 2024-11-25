<?php

namespace app\media\model;

use think\Model;

class TelegramModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'telegram_user';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'userId' => 'int',
        'telegramId' => 'varchar',
        'type' => 'int',
        'userInfo' => 'text',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['userInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

    public function createLink(array $array)
    {
        $userId = $array['userId'];
        $telegramId = $array['telegramId'];
        $type = $array['type'];

        $user = $this->where('userId', $userId)->find();
        if ($user) {
            return ['code' => 400, 'msg' => '该用户已绑定过'];
        }

        $telegramId = $this->where('telegramId', $telegramId)->find();
        if ($telegramId) {
            return ['code' => 400, 'msg' => '该Telegram账号已绑定过'];
        }

        $data = [
            'userId' => $userId,
            'telegramId' => $telegramId,
            'type' => $type,
        ];

        $this->save($data);

        return ['code' => 200, 'msg' => '绑定成功'];
    }

}