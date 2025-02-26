<?php
namespace app\api\job;

use think\facade\Config;
use think\queue\Job;
use mailer\Mailer;

class SendMailMessage
{
    public function fire(Job $job, $data)
    {
        try {
            // 如果任务已经删除则直接返回
            if ($job->isDeleted()) {
                return;
            }

            // 检查邮件配置是否启用
            if (!Config::get('mailer.enable')) {
                $job->delete();
                return;
            }

            // 获取邮件数据
            $to = $data['to'];
            $subject = $data['subject'];
            $content = $data['content'];
            $isHtml = $data['isHtml'] ?? true;
            
            // 发送邮件
            $mailer = new Mailer();

            // 检查是否启用socks5代理
            if (Config::get('mailer.use_socks5') && Config::get('proxy.socks5.enable')) {
                $socks5Config = Config::get('proxy.socks5');
                $streamContext = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                    'socks5' => [
                        'proxy' => "socks5://{$socks5Config['host']}:{$socks5Config['port']}",
                    ]
                ];
                
                // 如果设置了用户名密码
                if (!empty($socks5Config['username']) && !empty($socks5Config['password'])) {
                    $streamContext['socks5']['proxy'] = "socks5://{$socks5Config['username']}:{$socks5Config['password']}@{$socks5Config['host']}:{$socks5Config['port']}";
                }
                
                $mailer->setStreamOptions($streamContext);
            }

            if ($isHtml) {
                $mailer->html($content);
            } else {
                $mailer->text($content);
            }
            
            $mailer->to($to)
                ->subject($subject)
                ->send();

            // 任务完成后删除
            $job->delete();
            
        } catch (\Exception $e) {
            // 失败次数+1
            $attempts = $job->attempts();
            
            // 如果失败次数超过3次，则删除任务
            if ($attempts >= 3) {
                $job->delete();
                return;
            }
            
            // 否则重试，延迟60秒
            $job->release(60);
        }
    }
} 