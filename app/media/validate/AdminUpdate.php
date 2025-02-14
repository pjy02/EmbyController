<?php

namespace app\media\validate;

use think\Validate;

class AdminUpdate extends Validate
{
    protected $rule = [
        'id'        => 'require|number',
        'userName'  => 'alphaNum|length:4,20',
        'nickName'  => 'length:2,20',
        'password'  => 'length:6,20',
        'email'     => 'email',
        'authority' => 'require|between:-1,100',
        'rCoin'     => 'require|float|egt:0'
    ];

    protected $message = [
        'id.require'        => '用户ID不能为空',
        'id.number'         => '用户ID必须是数字',
        'userName.alphaNum' => '用户名只能是字母和数字',
        'userName.length'   => '用户名长度必须在4-20个字符之间',
        'nickName.length'   => '昵称长度必须在2-20个字符之间',
        'password.length'   => '密码长度必须在6-20个字符之间',
        'email.email'       => '邮箱格式不正确',
        'authority.require' => '用户Exp不能为空',
        'authority.number'  => '用户Exp必须是数字',
        'authority.between' => '用户Exp必须在-1到100之间',
        'rCoin.require'     => '用户余额不能为空',
        'rCoin.float'       => '用户余额必须是数字',
        'rCoin.egt'         => '用户余额不能小于0'
    ];

    // 场景设置
    protected $scene = [
        'edit'  => ['id', 'userName', 'nickName', 'password', 'email', 'authority', 'rCoin'],
        'add'   => ['userName', 'nickName', 'password', 'email', 'authority', 'rCoin'],
    ];
} 