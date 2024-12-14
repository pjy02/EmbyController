<?php
/**
 * +----------------------------------------------------------------------
 * | 后台中间件
 * +----------------------------------------------------------------------
 */
namespace app\media\middleware;

use think\facade\Session;
use think\facade\Request;
use think\Response;
use think\exception\HttpResponseException;

class MediaAuth
{
    public function handle($request, \Closure $next)
    {
        // 获取当前用户
        $user = Session::get('r_user');
        // 获取当前请求的 URL 路径
        $url = $request->url(true);

        // url 去掉域名部分
        $url = str_replace('http://'.$_SERVER['HTTP_HOST'], '', $url);
        $url = str_replace('https://'.$_SERVER['HTTP_HOST'], '', $url);

        if ($url == '/media' || $url == '/media/') {
            $url = '/media/index/index';
        }

        // 如果未登录且不是访问登录页面，则重定向到登录页面
        $allowList = [
            '/media/user/login',
            '/media/user/register',
            '/media/user/forgot',
            '/media/user/sendVerifyCode',
            '/media/index/index',
            '/media/index/getPrimaryImg',
            '/media/index/getLineStatus',
            '/media/index/getLatestMedia',
            '/media/server/crontab',
            '/media/server/resolvePayment',
            ];

        $flag = false;
        foreach ($allowList as $allow) {
            if (strpos($url, $allow) !== false) {
                $flag = true;
                break;
            }
        }
        if ((empty($user)) && !$flag) {
            Session::set('jumpUrl', $request->url(true));
            return redirect((string)url('/media/user/login'));
        }
        if (isset($user['authority']) && $user['authority'] < 0) {
            Session::delete('r_user');
            Session::set('jumpUrl', $request->url(true));
            return redirect((string)url('/media/user/login'));
        }

        return $next($request);
    }

    /**
     * 操作错误跳转
     * @param mixed $msg 提示信息
     * @param string|null $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param integer $wait 跳转等待时间
     * @param array $header 发送的Header信息
     * @return Response
     */
    protected function error($msg = '', string $url = null, $data = '', int $wait = 3, array $header = []): Response
    {
        if (is_null($url)) {
            $url = request()->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : app('route')->buildUrl($url);
        }

        $result = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = (request()->isJson() || request()->isAjax()) ? 'json' : 'html';

        // 所有form返回的都必须是json，所有A链接返回的都必须是Html
        $type = request()->isGet() ? 'html' : $type;

        if ($type == 'html') {
            $response = view(app('config')->get('app.dispatch_error_tmpl'), $result);
        } else if ($type == 'json') {
            $response = json($result);
        }
        throw new HttpResponseException($response);
    }
}
