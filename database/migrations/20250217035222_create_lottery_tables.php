<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateLotteryTables extends Migrator
{
    public function change()
    {
        // rc_lottery 表
        $this->table('lottery')
            ->addColumn('title', 'string', ['limit' => 100, 'comment' => '标题'])
            ->addColumn('description', 'string', ['limit' => 500, 'comment' => '描述'])
            ->addColumn('prizes', 'text', ['null' => true, 'comment' => '奖品列表(JSON)'])
            ->addColumn('drawTime', 'timestamp', ['comment' => '开奖时间'])
            ->addColumn('keywords', 'string', ['null' => true])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态:-1禁用,1进行中,2已结束'])
            ->addColumn('createTime', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('chatId', 'string', ['null' => true, 'comment' => '群组ID'])
            ->addIndex('status')
            ->addIndex('drawTime')
            ->create();

        // rc_lottery_participant 表
        $this->table('lottery_participant')
            ->addColumn('lotteryId', 'integer', ['comment' => '抽奖ID'])
            ->addColumn('telegramId', 'string', ['comment' => '参与者TelegramID'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态:0已参与,1已中奖'])
            ->addColumn('prize', 'text', ['null' => true, 'comment' => '中奖奖品(json)'])
            ->addColumn('createTime', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => '参与时间'])
            ->addIndex(['lotteryId', 'telegramId'])
            ->addIndex('status')
            ->create();
    }
}