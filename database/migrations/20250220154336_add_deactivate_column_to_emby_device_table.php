<?php
use think\migration\Migrator;
use think\migration\db\Column;

class AddDeactivateColumnToEmbyDeviceTable extends Migrator
{
    public function change()
    {
        $table = $this->table('rc_emby_device');
        $table->addColumn('deactivate', 'integer', ['default' => 0, 'comment' => '是否停用'])
            ->update();
    }
}