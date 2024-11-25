<?php

return [
    // 媒体服务器地址 即本系统能访问到的emby服务器地址 内网地址优先 如：http://127.0.0.1:8096/emby/ 一定要以/emby/结尾
    'urlBase' => '',
    // 媒体服务器api key
    'apiKey' => '',
    // 模板用户id 需要一个模板用户，用于创建新用户
    'UserTemplateId' => '',
    // crontab密码 每天定时访问一次本管理站点以刷新用户的有效期，推荐4点
    'crontabKey' => '',
    // 线路
    'lineList' => [
        ['name' => '线路1', 'url' => 'http://aaa.randallanjie.com:8096'],
        ['name' => 'AWS-HK优化线路', 'url' => 'https://bbb.randallanjie.com:443'],
    ],
    // Emby中管理员用户id
    'adminUserId' => '',
];