<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateBetTables extends Migrator
{
    public function change()
    {
        // rc_bet 表
        $this->table('bet')
            ->addColumn('chatId', 'string', ['limit' => 32, 'comment' => '群组ID'])
            ->addColumn('creatorId', 'string', ['limit' => 32, 'comment' => '创建者ID'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态：1进行中，2已开奖'])
            ->addColumn('randomType', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('result', 'string', ['limit' => 10, 'null' => true, 'comment' => '开奖结果'])
            ->addColumn('createTime', 'datetime', ['comment' => '创建时间'])
            ->addColumn('endTime', 'datetime', ['comment' => '结束时间'])
            ->addIndex('status')
            ->addIndex('chatId')
            ->create();

        // rc_bet_participant 表
        $this->table('bet_participant')
            ->addColumn('betId', 'integer', ['comment' => '赌博ID'])
            ->addColumn('telegramId', 'string', ['limit' => 32, 'comment' => '参与者TG ID'])
            ->addColumn('userId', 'integer', ['comment' => '用户ID'])
            ->addColumn('type', 'string', ['limit' => 10, 'comment' => '投注类型'])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'comment' => '投注金额'])
            ->addColumn('status', 'boolean', ['default' => 0, 'comment' => '状态：0未开奖，1赢，2输'])
            ->addColumn('winAmount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true, 'comment' => '赢得金额'])
            ->addIndex(['betId'])
            ->addIndex(['telegramId'])
            ->create();
    }
}