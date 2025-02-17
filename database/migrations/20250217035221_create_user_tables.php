<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserTables extends Migrator
{
    public function change()
    {
        // rc_user 表
        $this->table('user')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => 'createdAt'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => 'updatedAt'])
            ->addColumn('userName', 'string', ['comment' => '用户名（登陆名称）'])
            ->addColumn('nickName', 'string', ['null' => true])
            ->addColumn('password', 'string')
            ->addColumn('authority', 'integer', ['default' => 1, 'comment' => '权限（1:注册用户，之后数字为等级，0为管理员）'])
            ->addColumn('email', 'string', ['null' => true])
            ->addColumn('rCoin', 'double', ['default' => 0, 'comment' => '余额'])
            ->addColumn('userInfo', 'text', ['null' => true])
            ->create();

        // rc_telegram_user 表
        $this->table('telegram_user')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('userId', 'integer')
            ->addColumn('telegramId', 'string', ['limit' => 64])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '1正常绑定，2已经解绑'])
            ->addColumn('userInfo', 'text', ['null' => true])
            ->create();
    }
}