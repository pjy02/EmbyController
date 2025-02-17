<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateMemoTables extends Migrator
{
    public function change()
    {
        // rc_memo 表
        $this->table('memo')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('userId', 'integer', ['comment' => '用户id'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '类型，1公开，0指定好友可见，-1删除'])
            ->addColumn('content', 'text', ['null' => true, 'comment' => '内容'])
            ->addColumn('memoInfo', 'text', ['null' => true, 'comment' => '存储json类型数据'])
            ->create();

        // rc_memo_comment 表
        $this->table('memo_comment')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('memoId', 'integer')
            ->addColumn('userId', 'integer', ['null' => true])
            ->addColumn('userName', 'string', ['null' => true])
            ->addColumn('replyTo', 'integer', ['null' => true])
            ->addColumn('type', 'integer', ['default' => 1])
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('commentInfo', 'text', ['null' => true])
            ->create();
    }
}