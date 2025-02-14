<?php
namespace app\media\middleware;

use think\facade\Session;
use think\facade\Request;
use think\Response;
use think\exception\HttpResponseException;
use think\facade\View;
use think\facade\Config;

class SystemCheck{
    public function handle($request, \Closure $next)
    {
        View::assign('enableMoviepilot', Config::get('media.moviepilot.enabled'));

        return $next($request);
    }
}