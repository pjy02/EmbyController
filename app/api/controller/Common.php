<?php

namespace app\api\controller;

use app\api\model\UserModel;
use app\BaseController;
use think\facade\Request;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;

class Common extends BaseController
{
    /**
     * 获取接口列表
     * @return \think\response\Json
     * @author Anjie
     * @date 2024-07-09
     */
    public function index()
    {
        $controllerPath = '../app/api/controller';
        $namespace = 'app\\api\\controller\\';
        $file = 'Common.php';
        $className = str_replace('.php', '', $file);
        $fullClassName = $namespace . $className;

        $apis = [];

        // 检查类是否存在，避免类名无效的问题
        if (class_exists($fullClassName)) {
            $reflectionClass = new \ReflectionClass($fullClassName);
            $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if ($method->name == '__construct') {
                    continue;
                }

                $docComment = $method->getDocComment();
                $description = $this->parseDescriptionFromDocComment($docComment);

                $apis[] = [
                    'url' => '/api/' . strtolower($className) . '/' . $method->name,
                    'method' => 'GET', // 默认GET，可以根据注释内容调整
                    'description' => $description
                ];
            }
        }

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => $apis
        ]);
    }


    /**
     * 心跳检测
     * @return \think\response\Json
     * @author Anjie
     * @date 2024-07-09
     */
    public function ping()
    {
        $time = time();
        return json([
            'code' => 200,
            'msg' => 'pong',
            'data' => [
                'time' => $time
            ]
        ]);
    }

    /**
     * 获取IP地址（ipv4/ipv6）
     * @return \think\response\Json
     * @author Anjie
     * @date 2024-07-09
     */
    public function getip()
    {
        $realIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
            $_SERVER['HTTP_X_REAL_IP'] ??
            $_SERVER['HTTP_CF_CONNECTING_IP'] ??
            Request::ip();

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $realIp = trim($ipList[0]);
        }
        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'ip' => $realIp
            ]
        ]);
    }

    /**
     * 根据id/邮箱获取用户的qq邮箱/gravatar头像，优先id，优先qq邮箱
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author Anjie
     * @date 2024-07-10
     */
    public function getHeadImg()
    {
        $id = input('id');
        $email = input('email');
        $size = input('size', 96);
        $default = input('default', 'mp');
        $rating = input('rating', 'g');

        if ($id) {
            // 根据id获取用户邮箱
            $user = UserModel::find($id);
            if ($user) {
                $email = $user->email;
            }
        }

        if (is_null($email)) {
            $email = '';
        } else {
            $email = strtolower(trim($email));
        }


        $url = getGravatar($email, $size, $default, $rating, false);

        // 获取Gravatar图片
        $img = file_get_contents($url);

        if ($img === false) {
            return response('Failed to retrieve image', 500);
        }

        return response($img, 200, ['Content-Type' => 'image/png']);
    }

    /**
     * 根据内容获取二维码
     * @author Anjie
     * @date 2024-10-31
     */
    public function getqrcode()
    {
        $data = input('data');
        $writer = new PngWriter();
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 500,
            margin: 20,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );
        $logo = new Logo(
            path: __DIR__.'/assets/symfony.png',
            resizeToWidth: 50,
            punchoutBackground: true
        );
        $label = new Label(
            text: 'Label',
            textColor: new Color(255, 0, 0)
        );
//        $result = $writer->write($qrCode, $logo, $label);
        $result = $writer->write($qrCode);
        header('Content-Type: '.$result->getMimeType());
        echo $result->getString();
        exit();

    }

    /**
     * 语音转文字接口
     * @author Anjie
     * @date 2024-11-21
     */
    public function speechToText()
    {
        // 获取上传的音频文件
        $file = Request::file('audio');
        if (!$file) {
            return json(['code' => 400, 'msg' => 'No audio file uploaded']);
        }

        // 保存上传的音频文件
        $filePath = $file->move('../uploads');
        if (!$filePath) {
            return json(['code' => 500, 'msg' => 'Failed to save audio file']);
        }

        // 调用Python脚本进行语音识别
        $output = [];
        $returnVar = 0;
        exec("python3 speech_to_text.py " . escapeshellarg($filePath->getPathname()), $output, $returnVar);

        // 删除临时文件
        unlink($filePath->getPathname());

        if ($returnVar !== 0) {
            return json(['code' => 500, 'msg' => 'Failed to process audio file']);
        }

        $text = implode("\n", $output);

        return json(['code' => 200, 'msg' => 'success', 'data' => ['text' => $text]]);
    }


    // 以下为private方法，不对外提供访问

    /**
     * 解析方法注释中的描述信息
     * @param string $docComment
     * @return string
     * @author Anjie
     * @date 2024-07-09
     */
    private function parseDescriptionFromDocComment($docComment)
    {
        $description = '暂无接口描述';
        if ($docComment) {
            // 使用正则表达式提取注释中的描述部分
            if (preg_match('/\*\s+(.+?)(?=\n\s*\*|\*\/)/s', $docComment, $matches)) {
                $description = trim($matches[1]);
                // 去除每行开头的 '*' 和多余的空格
                $description = preg_replace('/^\*\s*/m', '', $description);
                $description = preg_replace('/\n\s*/', '', $description);
            }
        }
        return $description;
    }

}