<?php

namespace app\command;

use app\media\controller\MediaSyncController;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

class MediaSync extends Command
{
    protected function configure()
    {
        $this->setName('media:sync')
            ->setDescription('媒体播放状态同步命令')
            ->addArgument('action', Argument::OPTIONAL, '要执行的动作', 'sync')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, '指定用户ID')
            ->addOption('days', 'd', Option::VALUE_OPTIONAL, '清理天数', '90')
            ->addOption('force', 'f', Option::VALUE_NONE, '强制执行');
    }
    
    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $userId = $input->getOption('user');
        $days = $input->getOption('days');
        $force = $input->getOption('force');
        
        $syncController = new MediaSyncController();
        
        try {
            switch ($action) {
                case 'sync':
                    $this->syncAction($syncController, $userId, $output);
                    break;
                    
                case 'cleanup':
                    $this->cleanupAction($syncController, $days, $force, $output);
                    break;
                    
                case 'stats':
                    $this->statsAction($syncController, $output);
                    break;
                    
                case 'manual':
                    $this->manualAction($syncController, $output);
                    break;
                    
                default:
                    $output->writeln('<error>未知的动作: ' . $action . '</error>');
                    $output->writeln('可用动作: sync, cleanup, stats, manual');
                    return 1;
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $output->writeln('<error>执行失败: ' . $e->getMessage() . '</error>');
            Log::error('媒体同步命令执行失败: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * 同步动作
     */
    private function syncAction($syncController, $userId, $output)
    {
        $output->writeln('开始同步播放状态...');
        
        if ($userId) {
            // 同步指定用户
            $output->writeln('同步用户: ' . $userId);
            $result = $syncController->syncUser($userId);
        } else {
            // 同步所有用户
            $output->writeln('同步所有在线用户...');
            $result = $syncController->syncAllUsers();
        }
        
        if ($result['code'] === 200) {
            $output->writeln('<info>同步成功!</info>');
            $output->writeln('结果: ' . json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $output->writeln('<error>同步失败: ' . $result['message'] . '</error>');
        }
    }
    
    /**
     * 清理动作
     */
    private function cleanupAction($syncController, $days, $force, $output)
    {
        $output->writeln('开始清理过期记录...');
        $output->writeln('保留天数: ' . $days);
        
        if (!$force) {
            $output->writeln('<comment>使用 --force 选项来实际执行清理</comment>');
            $output->writeln('当前为预览模式');
            return;
        }
        
        $result = $syncController->cleanupOldRecords($days);
        
        if ($result['code'] === 200) {
            $output->writeln('<info>清理成功!</info>');
            $output->writeln('删除记录数: ' . $result['data']['deletedCount']);
            $output->writeln('截止日期: ' . $result['data']['cutoffDate']);
        } else {
            $output->writeln('<error>清理失败: ' . $result['message'] . '</error>');
        }
    }
    
    /**
     * 统计动作
     */
    private function statsAction($syncController, $output)
    {
        $output->writeln('获取同步统计信息...');
        
        $result = $syncController->getSyncStats();
        
        if ($result['code'] === 200) {
            $output->writeln('<info>统计信息:</info>');
            $data = $result['data'];
            $output->writeln('总记录数: ' . $data['totalRecords']);
            $output->writeln('今日新增: ' . $data['todayRecords']);
            $output->writeln('活跃用户: ' . $data['activeUsers']);
            $output->writeln('最近同步: ' . ($data['latestSyncTime'] ?: '无'));
        } else {
            $output->writeln('<error>获取统计信息失败: ' . $result['message'] . '</error>');
        }
    }
    
    /**
     * 手动同步动作
     */
    private function manualAction($syncController, $output)
    {
        $output->writeln('开始手动同步...');
        
        $result = $syncController->manualSync();
        
        if ($result['code'] === 200) {
            $output->writeln('<info>手动同步成功!</info>');
            $output->writeln('同步结果: ' . json_encode($result['data']['syncResult'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $output->writeln('统计信息: ' . json_encode($result['data']['stats'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $output->writeln('<error>手动同步失败: ' . $result['message'] . '</error>');
        }
    }
}