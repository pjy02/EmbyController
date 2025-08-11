<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateLogConfigTable extends Migrator
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
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        // 检查config表是否存在，如果不存在则创建
        if (!$this->hasTable('config')) {
            $table = $this->table('config', ['engine' => 'InnoDB']);
            $table
                ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('appName', 'string', ['limit' => 50, 'null' => true, 'comment' => '应用名称'])
                ->addColumn('key', 'string', ['limit' => 100, 'comment' => '配置键名'])
                ->addColumn('value', 'text', ['null' => true, 'comment' => '配置值'])
                ->addColumn('type', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '配置类型'])
                ->addColumn('status', 'integer', ['limit' => 11, 'default' => 1, 'comment' => '状态：0禁用，1启用'])
                ->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'comment' => '配置描述'])
                ->addIndex(['key'], ['unique' => true])
                ->addIndex(['appName'])
                ->addIndex(['status'])
                ->create();
            
            // 插入默认的日志配置
            $this->insertDefaultLogConfig();
        } else {
            // 如果表已存在，检查是否有必要的字段
            $table = $this->table('config');
            
            // 检查并添加description字段
            if (!$table->hasColumn('description')) {
                $table->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'comment' => '配置描述']);
            }
            
            $table->update();
            
            // 检查是否有默认配置，如果没有则插入
            $this->insertDefaultLogConfig();
        }
    }

    /**
     * 插入默认的日志配置
     */
    private function insertDefaultLogConfig()
    {
        $rows = [
            [
                'key' => 'log_retention_days',
                'value' => '7',
                'description' => '日志文件保留天数',
                'status' => 1,
                'type' => 1
            ],
            [
                'key' => 'log_auto_clean',
                'value' => '0',
                'description' => '是否启用自动清理过期日志',
                'status' => 1,
                'type' => 1
            ],
            [
                'key' => 'log_max_file_size',
                'value' => '10',
                'description' => '日志预览最大文件大小(MB)',
                'status' => 1,
                'type' => 1
            ]
        ];

        foreach ($rows as $row) {
            // 检查是否已存在
            $exists = $this->fetchRow("SELECT COUNT(*) as count FROM config WHERE `key` = '{$row['key']}'");
            
            if ($exists['count'] == 0) {
                $this->insert('config', $row);
            }
        }
    }

    /**
     * Down Method.
     *
     * More information on writing down methods is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-down-method
     * @return void
     */
    public function down()
    {
        // 删除日志配置记录
        $this->execute("DELETE FROM config WHERE `key` IN ('log_retention_days', 'log_auto_clean', 'log_max_file_size')");
        
        // 如果需要，可以删除整个表
        // $this->dropTable('config');
    }
}