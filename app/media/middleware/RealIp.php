<?php
namespace app\media\middleware;

use think\facade\Session;
use think\facade\Request;
use think\Response;
use think\exception\HttpResponseException;

class RealIp{
    public function handle($request, \Closure $next)
    {
        $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
            $_SERVER['HTTP_X_REAL_IP'] ??
            $_SERVER['HTTP_CF_CONNECTING_IP'] ??
            Request::ip();

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $realIp = trim($ipList[0]);
        }

        $request->realIp = $realIp;

        return $next($request);
    }
}