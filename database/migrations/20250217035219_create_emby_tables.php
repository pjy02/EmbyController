<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateEmbyTables extends Migrator
{
    public function change()
    {
        // rc_emby_device 表
        $this->table('emby_device')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => 'createdAt'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => 'updatedAt'])
            ->addColumn('lastUsedTime', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => '上次使用时间'])
            ->addColumn('lastUsedIp', 'string', ['comment' => '上次使用ip'])
            ->addColumn('embyId', 'string', ['limit' => 64, 'comment' => 'emby注册用户id'])
            ->addColumn('deviceId', 'string', ['comment' => '设备id'])
            ->addColumn('client', 'string', ['comment' => '设备类型'])
            ->addColumn('deviceName', 'string', ['null' => true, 'comment' => '设备名称'])
            ->addColumn('deviceInfo', 'text', ['null' => true, 'comment' => '其他信息json'])
            ->create();

        // rc_emby_user 表
        $this->table('emby_user')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => 'createdAt'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => 'updatedAt'])
            ->addColumn('activateTo', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '激活到某一时刻'])
            ->addColumn('userId', 'integer', ['comment' => '本系统用户id'])
            ->addColumn('embyId', 'string', ['limit' => 64, 'comment' => 'emby注册用户id'])
            ->addColumn('userInfo', 'text', ['null' => true, 'comment' => '其他信息(json)'])
            ->create();
    }
}