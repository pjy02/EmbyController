<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateSystemTables extends Migrator
{
    public function change()
    {
        // rc_config 表
        $this->table('config')
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'comment' => '创建时间'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => '更新时间'])
            ->addColumn('appName', 'string', ['null' => true, 'comment' => '所属应用'])
            ->addColumn('key', 'string', ['null' => true, 'comment' => '键'])
            ->addColumn('value', 'text', ['null' => true, 'comment' => '值'])
            ->addColumn('type', 'integer', ['default' => 0, 'comment' => '此键值对的所属安全状态，0仅管理员可见，1登陆可见，2公开'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '使用状态，1开启，0关闭'])
            ->create();

        // rc_update_logs 表
        $this->table('update_logs')
            ->addColumn('app_name', 'string', ['limit' => 50])
            ->addColumn('update_date', 'date')
            ->addColumn('version', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('content', 'text')
            ->create();

        // rc_version_updates 表
        $this->table('version_updates')
            ->addColumn('app_name', 'string', ['limit' => 50, 'default' => 'ALL', 'comment' => '应用名称,ALL表示全部应用'])
            ->addColumn('version', 'string', ['limit' => 20, 'null' => true, 'comment' => '版本号'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '更新说明'])
            ->addColumn('download_url', 'string', ['null' => true, 'comment' => '下载地址'])
            ->addColumn('is_release', 'boolean', ['default' => 0, 'comment' => '是否发布:0=未发布,1=已发布'])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['app_name', 'version'])
            ->create();
    }
}