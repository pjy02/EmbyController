<?php

namespace app\middleware;

use think\facade\Cache;
use think\facade\Log;

class LogMiddleware
{
    /**
     * 处理请求
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 检查是否为日志管理相关的请求
        if ($this->isLogRequest($request)) {
            // 检查管理员权限
            if (!$this->checkAdminPermission($request)) {
                return json(['code' => 403, 'message' => '无访问权限']);
            }
            
            // 记录访问日志
            $this->logAccess($request);
        }
        
        // 检查自动清理（随机触发，避免每次请求都检查）
        if (rand(1, 100) <= 10) { // 10%的概率触发检查
            $this->checkAutoClean();
        }
        
        return $next($request);
    }

    /**
     * 请求结束后执行
     * @param \think\Response $response
     * @return void
     */
    public function end($response)
    {
        // 可以在这里添加请求结束后的处理逻辑
    }

    /**
     * 检查是否为日志管理相关的请求
     * @param \think\Request $request
     * @return bool
     */
    private function isLogRequest($request)
    {
        $path = $request->pathinfo();
        return strpos($path, 'log') !== false || strpos($path, 'admin/logs') !== false;
    }

    /**
     * 检查管理员权限
     * @param \think\Request $request
     * @return bool
     */
    private function checkAdminPermission($request)
    {
        // 这里可以根据实际需求实现权限检查
        // 1. 检查用户是否登录
        // 2. 检查用户是否有管理员权限
        // 3. 检查IP白名单等
        
        // 暂时返回true，实际使用时需要根据项目权限系统实现
        // 可以从session、token或其他认证方式获取用户信息
        return true;
    }

    /**
     * 记录访问日志
     * @param \think\Request $request
     * @return void
     */
    private function logAccess($request)
    {
        $data = [
            'path' => $request->pathinfo(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('user-agent'),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        Log::info('日志管理访问记录', $data);
    }

    /**
     * 检查自动清理
     * @return void
     */
    private function checkAutoClean()
    {
        try {
            // 获取配置
            $configModel = new \app\media\model\SysConfigModel();
            $config = $configModel->getLogConfig();
            
            // 检查是否启用自动清理
            if (!empty($config['auto_clean']) && $config['auto_clean'] == 1) {
                // 检查上次清理时间
                $lastCleanTime = Cache::get('log_last_clean_time', 0);
                $currentTime = time();
                
                // 如果距离上次清理超过24小时，执行清理
                if ($currentTime - $lastCleanTime > 86400) {
                    $logService = new \app\service\LogService();
                    $deletedCount = $logService->cleanExpiredLogs();
                    Cache::set('log_last_clean_time', $currentTime);
                    
                    // 记录清理日志
                    Log::info('自动清理过期日志完成，删除了 ' . $deletedCount . ' 个文件');
                }
            }
        } catch (\Exception $e) {
            Log::error('自动清理检查失败：' . $e->getMessage());
        }
    }
}