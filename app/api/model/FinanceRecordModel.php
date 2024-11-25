<?php

namespace app\api\model;

use think\Model;

class FinanceRecordModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'finance_record';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'userId' => 'int',  // 对应用户id
        'action' => 'int',  // 1充值，2兑换兑换码，3使用余额，4签到
        'count' => 'varchar',  // 充值消费则显示数量，兑换激活码填入对应激活码
        'recordInfo' => 'text',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['recordInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名
    protected $dateFormat = 'Y-m-d H:i:s'; // 时间字段取出后的默认时间格式

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

    public function updateRecordInfo($id, $recordInfo)
    {
        $record = $this->where([
            'id' => $id,
        ])->find();
        if ($record) {
            $record->recordInfo = $recordInfo;
            $record->save();
        }
    }

}