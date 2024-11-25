<?php

namespace app\media\validate;

use think\Validate;
use app\media\model\UserModel as UserModel;

class Register extends Validate
{
    protected $rule = [
        'username|用户名' => 'require|checkUsername|checkLength:3,20|alreadyExist:userName',
        'password|密码' => 'require|alphaDash|checkLength:6,20',
        'email|邮箱' => 'require|checkEmail|alreadyExist:email',
        'verify|邮箱验证码' => 'require|length:6,6',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.checkUsername' => '用户名必须是字母、数字、下划线、破折号',
        'username.checkLength' => '用户名长度必须在3到20个字符之间',
        'username.alreadyExist' => '用户名已存在，请更换用户名',
        'password.require' => '密码不能为空',
        'password.alphaDash' => '密码只能是字母、数字、下划线和破折号',
        'password.checkLength' => '密码长度必须在6到20个字符之间',
        'email.require' => '邮箱不能为空',
        'email.checkEmail' => '用户名必须是有效的电子邮件地址',
        'email.alreadyExist' => '邮箱已存在，请找回密码或者联系管理员',
        'verify.require' => '邮箱验证码不能为空',
        'verify.length' => '邮箱验证码长度必须是6个字符',
    ];

    protected $scene = [
        'register' => ['username', 'password', 'email', 'verify'],
    ];

    // Username or email 检查
    protected function checkUsername($value, $rule, $data = [])
    {

        // 如果是字母、数字、下划线和破折号，返回true
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
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

    // Email 检查
    protected function checkEmail($value, $rule, $data = [])
    {
        // 如果是合法的电子邮件地址，返回true
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return false;
    }

    // 邮箱是否已存在
    protected function alreadyExist($value, $rule, $data = [])
    {
        $userModel = new UserModel();
        $user = $userModel->where($rule, $value)->find();
        if ($user) {
            return false;
        }
        return true;
    }
}
