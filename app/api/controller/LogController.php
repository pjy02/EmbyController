<?php

namespace app\api\controller;

use app\media\model\SysConfigModel;
use app\service\LogService;
use app\BaseController;
use think\facade\Request;
use think\facade\Cache;

class LogController extends BaseController
{
    protected $logService;

    public function __construct()
    {
        parent::__construct();
        $this->logService = new LogService();
    }

    /**
     * 日志管理页面
     * @return string
     */
    public function index()
    {
        // 检查管理员权限
        if (!$this->checkAdminPermission()) {
            return json(['code' => 403, 'message' => '无访问权限']);
        }
        
        // 返回日志管理页面
        return view('log/index');
    }

    /**
     * 获取日志文件列表
     * @return \think\response\Json
     */
    public function getLogList()
    {
        // 检查自动清理
        $this->checkAutoClean();
        
        $page = input('page', 1);
        $pageSize = input('page_size', 20);
        $sort = input('sort', 'mtime');
        $order = input('order', 'desc');
        
        try {
            $result = $this->logService->getLogFiles($page, $pageSize, $sort, $order);
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取日志文件列表失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 删除指定日志文件
     * @return \think\response\Json
     */
    public function delete()
    {
        $fileName = input('file_name');
        if (empty($fileName)) {
            return json([
                'code' => 400,
                'msg' => '文件名不能为空'
            ]);
        }
        
        try {
            $result = $this->logService->deleteLogFile($fileName);
            if ($result) {
                return json([
                    'code' => 200,
                    'msg' => '删除成功'
                ]);
            } else {
                return json([
                    'code' => 500,
                    'msg' => '删除失败'
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '删除日志文件失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 批量删除日志文件
     * @return \think\response\Json
     */
    public function batchDelete()
    {
        $fileNames = input('file_names/a', []);
        if (empty($fileNames)) {
            return json([
                'code' => 400,
                'msg' => '请选择要删除的文件'
            ]);
        }
        
        try {
            $successCount = 0;
            $failCount = 0;
            $errors = [];
            
            foreach ($fileNames as $fileName) {
                try {
                    $result = $this->logService->deleteLogFile($fileName);
                    if ($result) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $errors[] = $fileName . ' 删除失败';
                    }
                } catch (\Exception $e) {
                    $failCount++;
                    $errors[] = $fileName . ' 删除失败：' . $e->getMessage();
                }
            }
            
            return json([
                'code' => 200,
                'msg' => sprintf('批量删除完成：成功%d个，失败%d个', $successCount, $failCount),
                'data' => [
                    'success_count' => $successCount,
                    'fail_count' => $failCount,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '批量删除失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取日志管理配置
     * @return \think\response\Json
     */
    public function getConfig()
    {
        try {
            $config = [
                'log_retention_days' => $this->getConfigValue('log_retention_days', 7),
                'log_auto_clean' => $this->getConfigValue('log_auto_clean', true),
                'log_max_file_size' => $this->getConfigValue('log_max_file_size', 10485760), // 10MB
                'log_clean_last_run' => $this->getConfigValue('log_clean_last_run', 0)
            ];
            
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取配置失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 设置日志管理配置
     * @return \think\response\Json
     */
    public function setConfig()
    {
        $data = input();
        
        try {
            foreach ($data as $key => $value) {
                $this->setConfigValue($key, $value);
            }
            
            return json([
                'code' => 200,
                'msg' => '配置保存成功'
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '保存配置失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 清理过期日志文件
     * @return \think\response\Json
     */
    public function cleanExpired()
    {
        try {
            $result = $this->logService->cleanExpiredLogs();
            
            // 更新清理时间
            $this->setConfigValue('log_clean_last_run', date('Y-m-d H:i:s'));
            
            return json([
                'code' => 200,
                'msg' => sprintf('清理完成，共删除%d个过期日志文件', $result)
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '清理过期日志失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 预览日志文件内容
     * @return \think\response\Json
     */
    public function preview()
    {
        $fileName = input('file_name');
        $lines = input('lines', 100);
        
        if (empty($fileName)) {
            return json([
                'code' => 400,
                'msg' => '文件名不能为空'
            ]);
        }
        
        try {
            $content = $this->logService->previewLogFile($fileName, $lines);
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $content
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '预览日志文件失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 检查并执行自动清理
     */
    private function checkAutoClean()
    {
        $autoClean = $this->getConfigValue('log_auto_clean', true);
        if (!$autoClean) {
            return;
        }
        
        $lastRun = $this->getConfigValue('log_clean_last_run', 0);
        $lastRunTime = is_numeric($lastRun) ? $lastRun : strtotime($lastRun);
        
        // 如果距离上次清理超过24小时，执行清理
        if (time() - $lastRunTime > 86400) {
            try {
                $this->logService->cleanExpiredLogs();
                $this->setConfigValue('log_clean_last_run', date('Y-m-d H:i:s'));
            } catch (\Exception $e) {
                // 自动清理失败不影响正常功能
                error_log('自动清理日志失败：' . $e->getMessage());
            }
        }
    }

    /**
     * 获取配置值
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getConfigValue($key, $default = null)
    {
        $config = SysConfigModel::where('key', $key)->find();
        if ($config) {
            return is_numeric($config->value) ? (int)$config->value : $config->value;
        }
        return $default;
    }

    /**
     * 设置配置值
     * @param string $key
     * @param mixed $value
     */
    private function setConfigValue($key, $value)
    {
        $config = SysConfigModel::where('key', $key)->find();
        if ($config) {
            $config->value = $value;
            $config->save();
        } else {
            SysConfigModel::create([
                'key' => $key,
                'value' => $value,
                'appName' => 'log_management',
                'type' => 1,
                'status' => 1
            ]);
        }
    }
}