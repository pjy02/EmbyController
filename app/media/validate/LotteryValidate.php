<?php
namespace app\media\validate;

use think\Validate;

class LotteryValidate extends Validate
{
    protected $rule = [
        'title' => 'require|max:100',
        'description' => 'require|max:500',
        'drawTime' => 'require|date',
        'prizes' => 'require|checkPrizes',
    ];

    protected $message = [
        'title.require' => '标题不能为空',
        'title.max' => '标题最多不能超过100个字符',
        'description.require' => '描述不能为空', 
        'description.max' => '描述最多不能超过500个字符',
        'drawTime.require' => '开奖时间不能为空',
        'drawTime.date' => '开奖时间格式不正确',
        'prizes.require' => '奖品不能为空',
    ];

    protected $scene = [
        'add' => ['title', 'description', 'drawTime', 'prizes'],
        'edit' => ['id', 'title', 'description', 'drawTime', 'prizes']
    ];

    // 自定义验证规则
    protected function checkPrizes($value)
    {
        if (!is_string($value)) {
            return '奖品数据必须是JSON字符串';
        }

        $prizes = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '奖品数据JSON格式错误';
        }

        if (!is_array($prizes) || empty($prizes)) {
            return '奖品数据不能为空';
        }

        foreach ($prizes as $prize) {
            // 检查必要字段
            if (!isset($prize['name']) || !isset($prize['count']) || !isset($prize['contents'])) {
                return '奖品数据格式错误：缺少必要字段';
            }

            // 检查名称
            if (empty($prize['name'])) {
                return '奖品名称不能为空';
            }

            // 检查数量
            if (!is_numeric($prize['count']) || $prize['count'] < 1) {
                return '奖品数量必须大于0';
            }

            // 检查内容
            if (!is_array($prize['contents'])) {
                return '奖品内容必须是数组';
            }

            // 检查内容数量是否匹配
            if (count($prize['contents']) !== intval($prize['count'])) {
                return '奖品内容数量必须与奖品数量相匹配';
            }

            // 检查每个内容是否为空
            foreach ($prize['contents'] as $content) {
                if (empty($content)) {
                    return '奖品内容不能为空';
                }
            }
        }

        return true;
    }
} 