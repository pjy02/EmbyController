<?php

namespace app\api\model;

use think\Model;

class EmbyUserModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'emby_user';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'activateTo' => 'timestamp',
        'userId' => 'int',
        'embyId' => 'varchar',
        'userInfo' => 'text',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['userInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名
    protected $dateFormat = 'Y-m-d H:i:s'; // 时间字段取出后的默认时间格式

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

    public function updateUserInfo($id, $userInfo)
    {
        $user = $this->where([
            'userId' => $id,
        ])->find();
        if ($user) {
            $user->userInfo = $userInfo;
            $user->save();
        }
    }

    public function getEmbyId($id)
    {
        $user = $this->where([
            'userId' => $id,
        ])->find();
        if ($user) {
            return $user->embyId;
        } else {
            return null;
        }
    }
}