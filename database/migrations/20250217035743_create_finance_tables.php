<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateFinanceTables extends Migrator
{
    public function change()
    {
        // rc_exchange_code 表
        $this->table('exchange_code')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('code', 'string', ['limit' => 64, 'comment' => '激活码'])
            ->addColumn('type', 'integer', ['default' => 0, 'comment' => '0未使用，1已使用，-1已禁用'])
            ->addColumn('exchangeType', 'integer', ['default' => 1, 'comment' => '可兑换类型（1激活，2按天续期，3按月续期，4充值余额）'])
            ->addColumn('exchangeCount', 'integer', ['default' => 1, 'comment' => '兑换数量'])
            ->addColumn('exchangeDate', 'timestamp', ['null' => true, 'comment' => '兑换日期'])
            ->addColumn('usedByUserId', 'integer', ['null' => true, 'comment' => '被用户（ID）使用'])
            ->addColumn('codeInfo', 'text', ['null' => true])
            ->create();

        // rc_finance_record 表
        $this->table('finance_record')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('userId', 'integer', ['null' => true, 'comment' => '对应用户id'])
            ->addColumn('action', 'integer', ['null' => true, 'comment' => '1充值，2兑换兑换码，3使用余额'])
            ->addColumn('count', 'string', ['limit' => 64, 'null' => true, 'comment' => '充值消费则显示数量，兑换激活码填入对应激活码'])
            ->addColumn('recordInfo', 'text', ['null' => true])
            ->create();

        // rc_pay_record 表
        $this->table('pay_record')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('payCompleteKey', 'string')
            ->addColumn('type', 'integer', ['default' => 0])
            ->addColumn('userId', 'integer')
            ->addColumn('tradeNo', 'string', ['null' => true])
            ->addColumn('name', 'string', ['null' => true])
            ->addColumn('money', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('clientip', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('payRecordInfo', 'text', ['null' => true])
            ->create();
    }
}