<?php
namespace app\api\model;

use think\Model;

class LotteryParticipantModel extends Model
{
    protected $name = 'lottery_participant';
    protected $pk = 'id';
    
    // 自动转换数据类型
    protected $type = [
        'createTime' => 'timestamp'
    ];

    protected $schema = [
        'id' => 'int',
        'lotteryId' => 'int',
        'telegramId' => 'varchar',
        'status' => 'int',
        'prize' => 'text',
        'createTime' => 'timestamp',
    ];

    // 自动完成
    protected $auto = ['createTime'];
    
    protected function setCreateTimeAttr()
    {
        return time();
    }

    // 获取器 - 状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            0 => '已参与',
            1 => '已中奖',
            2 => '未中奖',
        ];
        return $status[$data['status']] ?? '未知';
    }

    // 关联抽奖
    public function lottery()
    {
        return $this->belongsTo(LotteryModel::class, 'lotteryId', 'id');
    }
} 