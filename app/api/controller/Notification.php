<?php

namespace app\api\controller;


use app\BaseController;

class Notification extends BaseController
{
    public function index()
    {
        $time = time();
        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'time' => $time
            ]
        ]);
    }

    public function baota()
    {
        // Post请求
        if ($this->request->isPost()) {
            // {"title": "SSH登录告警", "msg": "#### SSH登录告警\n>服务器：Server1\n>IP地址：2604:6600::36(外) 208.87.240.61(内)\n>发送时间：2025-01-25 05:16:26\n>发送内容：208.87.240.61服务器存在异常登陆登陆IP为166.88.164.181登陆用户为root", "type": "SSH登录告警"}
            $data = $this->request->post();
            $url = 'https://bark.randallanjie.com/STmBsaztWCX87CyRgCDCyR';
            $post_data = [
                'title' => $data['title'],
                'subtitle' => $data['type'],
                'body' => $data['msg'],
                'sound' => 'shake.caf',
                'group' => 'bt'
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $output = curl_exec($ch);
            curl_close($ch);
            return json([
                'code' => 200,
                'msg' => 'success',
                'data' => $output
            ]);
        }
    }
}