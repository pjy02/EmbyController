<?php

namespace app\api\controller;

use think\facade\Request;
use app\admin\model\AuthLicense;
use app\admin\model\AuthLog;
use app\admin\model\VersionUpdate;

class Index
{
    /**
     * 验证接口
     */
    public function auth()
    {
        $license_key = Request::param('license_key');
        $app_name = Request::param('app_name', 'ALL');
        $ip = getRealIp();
        
        // 获取最新版本信息
        $latest_version = VersionUpdate::getLatest($app_name);
        
        // 准备基础版本信息
        $version_data = [
            'version' => $latest_version->version ?? '',
            'description' => $latest_version->description ?? '',
            'app_name' => $latest_version->app_name ?? 'ALL'
        ];
            
        // 如果没有提供license_key，只返回版本信息
        if (empty($license_key)) {
            return json([
                'code' => 200,
                'msg' => '请提供授权密钥',
                'data' => $version_data
            ]);
        }
        
        // 验证授权
        $license = AuthLicense::where('license_key', $license_key)->find();
            
        if (!$license) {
            $this->logAuth($license_key, $ip, 0, '无效的授权密钥');
            return json([
                'code' => 401,
                'msg' => '无效的授权密钥',
                'data' => $version_data
            ]);
        }
        
        // 验证应用权限
        if ($license->app_name !== 'ALL' && $license->app_name !== $app_name) {
            $this->logAuth($license_key, $ip, 0, "应用未授权 [请求应用: {$app_name}] [已授权应用: {$license->app_name}]");
            return json([
                'code' => 401,
                'msg' => '应用未授权',
                'data' => $version_data
            ]);
        }
        
        // 检查IP是否在允许范围内
        if (!$license->checkIp($ip)) {
            // 记录具体的IP信息以便调试
            $ip_type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 
                     (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'IPv6' : '未知');
            
            $message = sprintf(
                'IP地址不匹配 [%s: %s] [允许的IPv4: %s] [允许的IPv6: %s]',
                $ip_type,
                $ip,
                $license->ipv4 ?: '无',
                $license->ipv6 ?: '无'
            );
            
            $this->logAuth($license_key, $ip, 0, $message);
            return json([
                'code' => 401,
                'msg' => 'IP地址不匹配',
                'data' => $version_data
            ]);
        }
        
        // 验证通过，返回完整信息
        $this->logAuth($license_key, $ip, 1, '验证成功');
        return json([
            'code' => 200,
            'msg' => '验证成功',
            'data' => array_merge($version_data, [
                'download_url' => $latest_version->download_url ?? '',
                'license' => [
                    'expire_time' => $license->expire_time ? date('Y-m-d H:i:s', strtotime($license->expire_time)) : 'Lifetime',
                    'status' => $license->status,
                    'app_name' => $license->app_name
                ]
            ])
        ]);
    }
    
    /**
     * 记录验证日志
     */
    private function logAuth($license_key, $ip, $status, $message)
    {
        AuthLog::create([
            'license_key' => $license_key,
            'ip_address' => $ip,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
