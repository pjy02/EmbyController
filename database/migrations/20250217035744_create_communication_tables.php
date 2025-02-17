<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateCommunicationTables extends Migrator
{
    public function change()
    {
        // rc_notification 表
        $this->table('notification')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('type', 'integer', ['default' => 0, 'comment' => '0系统通知 1用户消息'])
            ->addColumn('readStatus', 'integer', ['default' => 0, 'comment' => '0未读 1已读'])
            ->addColumn('fromUserId', 'integer', ['default' => 0, 'comment' => '0系统 >0用户'])
            ->addColumn('toUserId', 'integer')
            ->addColumn('message', 'text')
            ->addColumn('notificationInfo', 'text', ['null' => true])
            ->create();

        // rc_request 表
        $this->table('request')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '0暂不请求，1请求未回复，2已经回复，-1已关闭'])
            ->addColumn('requestUserId', 'integer')
            ->addColumn('replyUserId', 'integer', ['null' => true, 'comment' => '回复的管理员id'])
            ->addColumn('message', 'text', ['null' => true, 'comment' => '对话记录'])
            ->addColumn('requestInfo', 'text', ['null' => true])
            ->create();
    }
}