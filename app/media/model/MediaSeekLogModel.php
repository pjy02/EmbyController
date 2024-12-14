<?php
namespace app\media\model;

use think\Model;

class MediaSeekLogModel extends Model
{
    protected $name = 'media_seek_log';
    
    protected $schema = [
        'id'        => 'int',
        'seekId'    => 'int',
        'type'      => 'int', // 1=创建求片, 2=同求, 3=状态变更
        'content'   => 'string',
        'createdAt' => 'datetime'
    ];

    protected $autoWriteTimestamp = true;
    protected $createTime = 'createdAt';
    protected $updateTime = false;

    // 关联求片表
    public function seek()
    {
        return $this->belongsTo(MediaSeekModel::class, 'seekId', 'id');
    }
} 