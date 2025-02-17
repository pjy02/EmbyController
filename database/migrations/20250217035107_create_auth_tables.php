<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateAuthTables extends Migrator
{
    public function change()
    {
        // rc_auth_licenses 表
        $this->table('auth_licenses')
            ->addColumn('license_key', 'string', ['limit' => 32, 'comment' => '授权密钥'])
            ->addColumn('app_name', 'string', ['limit' => 50, 'default' => 'ALL', 'comment' => '应用名称,ALL表示全部应用'])
            ->addColumn('ipv4', 'string', ['null' => true, 'comment' => 'IPv4地址/段列表，多个用逗号分隔'])
            ->addColumn('ipv6', 'string', ['null' => true, 'comment' => 'IPv6地址/段列表，多个用逗号分隔'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:0=禁用,1=启用'])
            ->addColumn('expire_time', 'datetime', ['null' => true, 'comment' => '过期时间'])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex('license_key', ['unique' => true])
            ->create();

        // rc_auth_logs 表
        $this->table('auth_logs')
            ->addColumn('license_key', 'string', ['limit' => 32, 'comment' => '授权密钥'])
            ->addColumn('ip_address', 'string', ['limit' => 15, 'comment' => '请求IP'])
            ->addColumn('status', 'boolean', ['default' => 0, 'comment' => '验证状态:0=失败,1=成功'])
            ->addColumn('message', 'string', ['null' => true, 'comment' => '验证消息'])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}