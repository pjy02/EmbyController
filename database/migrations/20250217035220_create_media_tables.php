<?php
use think\migration\Migrator;
use think\migration\db\Column;

class CreateMediaTables extends Migrator
{
    public function change()
    {
        // rc_media_comment 表
        $this->table('media_comment')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('userId', 'integer', ['comment' => '用户id'])
            ->addColumn('mediaId', 'string', ['limit' => 64, 'comment' => '媒体id'])
            ->addColumn('rating', 'double', ['default' => 5])
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('mentions', 'text', ['null' => true])
            ->addColumn('quotedComment', 'integer', ['null' => true, 'comment' => '引用的评论id'])
            ->create();

        // rc_media_history 表
        $this->table('media_history')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '1播放中 2暂停 3完成播放'])
            ->addColumn('userId', 'integer')
            ->addColumn('mediaId', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('mediaName', 'string', ['null' => true])
            ->addColumn('mediaYear', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('historyInfo', 'text', ['null' => true])
            ->create();

        // rc_media_info 表
        $this->table('media_info')
            ->addColumn('createdAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('mediaName', 'string')
            ->addColumn('mediaYear', 'string', ['limit' => 8])
            ->addColumn('mediaType', 'integer', ['default' => 1, 'comment' => '1电影 2剧集'])
            ->addColumn('mediaMainId', 'string', ['limit' => 64, 'null' => true, 'comment' => 'Emby中对应的主要id，用于图片获取'])
            ->addColumn('mediaInfo', 'text', ['null' => true])
            ->create();

        // rc_media_seek 表
        $this->table('media_seek')
            ->addColumn('userId', 'integer', ['comment' => '请求用户ID'])
            ->addColumn('title', 'string', ['comment' => '影片名称'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '备注信息'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态:0=已请求,1=管理员已确认,2=正在收集资源,3=已入库,-1=暂不收录'])
            ->addColumn('statusRemark', 'string', ['null' => true, 'comment' => '状态备注'])
            ->addColumn('seekCount', 'integer', ['default' => 1, 'comment' => '同求人数'])
            ->addColumn('createdAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updatedAt', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('downloadId', 'string', ['null' => true, 'comment' => 'MoviePilot下载任务ID'])
            ->addIndex('userId')
            ->create();
    }
}