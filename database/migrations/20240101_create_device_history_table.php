<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDeviceHistoryTable extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('device_history');
        $table->addColumn('deviceId', 'string', ['limit' => 255, 'comment' => '设备ID'])
              ->addColumn('embyId', 'string', ['limit' => 255, 'comment' => 'Emby用户ID'])
              ->addColumn('statusType', 'string', ['limit' => 50, 'comment' => '状态变更类型'])
              ->addColumn('oldStatus', 'json', ['null' => true, 'comment' => '变更前的状态'])
              ->addColumn('newStatus', 'json', ['null' => true, 'comment' => '变更后的状态'])
              ->addColumn('sessionId', 'string', ['limit' => 255, 'null' => true, 'comment' => '会话ID'])
              ->addColumn('client', 'string', ['limit' => 255, 'null' => true, 'comment' => '客户端'])
              ->addColumn('deviceName', 'string', ['limit' => 255, 'null' => true, 'comment' => '设备名称'])
              ->addColumn('ip', 'string', ['limit' => 255, 'null' => true, 'comment' => 'IP地址'])
              ->addColumn('changeTime', 'datetime', ['comment' => '变更时间'])
              ->addColumn('additionalInfo', 'json', ['null' => true, 'comment' => '附加信息'])
              ->addColumn('created_at', 'datetime', ['null' => true])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addIndex(['deviceId'])
              ->addIndex(['embyId'])
              ->addIndex(['statusType'])
              ->addIndex(['changeTime'])
              ->create();
    }
}