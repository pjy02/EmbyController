<?php

namespace app\media\validate;

use think\Validate;

class Register extends Validate
{
    protected $rule = [
        'username' => 'require|alphaNum|unique:user|length:4,20',
        'password' => 'require|checkPassword|checkLength:6,20',
        'email'    => 'require|email|unique:user',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.alphaNum' => '用户名只能是字母和数字',
        'username.unique' => '用户名已存在',
        'username.length' => '用户名长度必须在4-20个字符之间',
        'password.require' => '密码不能为空',
        'password.checkPassword' => '密码只能是字母、数字、下划线和破折号',
        'password.checkLength' => '密码长度必须在6到40个字符之间',
        'email.require' => '邮箱不能为空',
        'email.email' => '邮箱格式不正确',
        'email.unique' => '该邮箱已被注册',
    ];

    protected function checkPassword($value, $rule, $data = [])
    {

        // 如果是字母、数字、下划线和破折号，返回true
        if (preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            return true;
        }

        // 否则，返回false
        return false;
    }

    // 长度检查
    protected function checkLength($value, $rule, $data = [])
    {
        $length = explode(',', $rule);
        $min = $length[0];
        $max = $length[1];
        $len = strlen($value);
        if ($len >= $min && $len <= $max) {
            return true;
        }
        return false;
    }
}
