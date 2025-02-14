<?php
namespace app\api\controller;

use app\api\model\UpdateLog;
use app\BaseController;

class UpdateList extends BaseController
{
    public function media()
    {
        $updateLogs = new UpdateLog();
        $logs = $updateLogs->where('app_name', 'media')
            ->order('update_date', 'desc')
            ->select();

        return json([
            'status' => 'success',
            'data' => $logs
        ]);
    }
}