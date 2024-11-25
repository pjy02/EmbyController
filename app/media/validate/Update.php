<?php

namespace app\media\validate;

use think\Validate;

class Update extends Validate
{
    protected $rule = [
        'username|用户名' => 'checkUsername|checkLength:3,40',
        'email|邮箱' => 'checkEmail',
        'password|密码' => 'alphaDash|checkLength:6,40',
    ];

    protected $message = [
        'username.checkUsername' => '用户名必须是字母、数字、下划线、破折号',
        'username.checkLength' => '用户名长度必须在3到40个字符之间',
        'email.checkEmail' => '用户名必须是有效的电子邮件地址',
        'password.alphaDash' => '密码只能是字母、数字、下划线和破折号',
        'password.checkLength' => '密码长度必须在6到40个字符之间',
    ];

    protected $scene = [
        'update' => ['username', 'email', 'password'],
    ];

    // Username 检查
    protected function checkUsername($value, $rule, $data = [])
    {

        // 如果是字母、数字、下划线和破折号，返回true
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return true;
        }

        // 否则，返回false
        return false;
    }

    // Email 检查
    protected function checkEmail($value, $rule, $data = [])
    {
        // 如果是合法的电子邮件地址，返回true
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
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
