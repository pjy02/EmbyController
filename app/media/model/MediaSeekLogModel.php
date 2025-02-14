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

    // 获取格式化的日志内容
    public function getFormattedContent()
    {
        $content = json_decode($this->content, true);
        
        switch ($this->type) {
            case 1: // 创建求片
                if (isset($content['action']) && $content['action'] === 'create') {
                    return getUserName($content['userId']) . " 发起了求片《{$content['title']}》";
                }
                break;
                
            case 2: // 同求
                return getUserName($content['userId']) . " 同求了这部影片";
                
            case 3: // 状态变更
                if (isset($content['action']) && $content['action'] === 'status_change') {
                    $operator = $content['operator'] === 'system' ? '系统' : '管理员';
                    $statusText = (new MediaSeekModel())->getStatusTextAttr(null, ['status' => $content['status']]);
                    return "{$operator}将状态更新为：{$statusText}，备注：{$content['remark']}";
                }
                break;
        }
        
        return '';
    }
} 