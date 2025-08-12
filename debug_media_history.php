<?php
// 调试脚本：检查media_history表状态
require_once 'app/BaseController.php';
require_once 'thinkphp/helper.php';

// 初始化ThinkPHP
define('APP_PATH', __DIR__ . '/app/');
require_once 'thinkphp/start.php';

try {
    echo "=== Media History 调试报告 ===\n\n";
    
    // 1. 检查数据库连接
    echo "1. 检查数据库连接...\n";
    $db = think\facade\Db::connect();
    $tables = $db->getTables();
    echo "数据库连接成功\n";
    echo "数据库中的表: " . implode(', ', $tables) . "\n\n";
    
    // 2. 检查media_history表是否存在
    echo "2. 检查media_history表...\n";
    if (in_array('media_history', $tables)) {
        echo "media_history表存在\n";
        
        // 3. 检查表结构
        echo "\n3. 检查表结构...\n";
        $columns = $db->query("SHOW COLUMNS FROM media_history");
        echo "表字段:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']}" . ($column['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
        }
        
        // 4. 检查数据量
        echo "\n4. 检查数据量...\n";
        $totalRecords = $db->table('media_history')->count();
        echo "总记录数: {$totalRecords}\n";
        
        if ($totalRecords > 0) {
            // 5. 检查最近10条记录
            echo "\n5. 最近10条记录...\n";
            $recentRecords = $db->table('media_history')
                ->order('updatedAt', 'desc')
                ->limit(10)
                ->select();
            
            foreach ($recentRecords as $record) {
                echo "  - ID: {$record['id']}, 用户ID: {$record['userId']}, 媒体ID: {$record['mediaId']}, 媒体名称: {$record['mediaName']}, 类型: {$record['type']}, 更新时间: {$record['updatedAt']}\n";
            }
            
            // 6. 按用户分组统计
            echo "\n6. 按用户分组统计...\n";
            $userStats = $db->table('media_history')
                ->field('userId, COUNT(*) as count')
                ->group('userId')
                ->select();
            
            foreach ($userStats as $stat) {
                echo "  - 用户ID {$stat['userId']}: {$stat['count']} 条记录\n";
            }
        }
        
    } else {
        echo "media_history表不存在！\n";
    }
    
    // 7. 检查device_history表
    echo "\n7. 检查device_history表...\n";
    if (in_array('device_history', $tables)) {
        echo "device_history表存在\n";
        $deviceCount = $db->table('device_history')->count();
        echo "device_history表记录数: {$deviceCount}\n";
    } else {
        echo "device_history表不存在\n";
    }
    
    // 8. 检查webhook日志
    echo "\n8. 检查webhook日志...\n";
    $logFile = __DIR__ . '/runtime/log/media_webhook.log';
    if (file_exists($logFile)) {
        echo "webhook日志文件存在\n";
        $logSize = filesize($logFile);
        echo "日志文件大小: {$logSize} bytes\n";
        
        // 读取最后10行
        $lines = array_slice(file($logFile), -10);
        echo "最后10行日志:\n";
        foreach ($lines as $line) {
            echo "  " . trim($line) . "\n";
        }
    } else {
        echo "webhook日志文件不存在\n";
    }
    
    // 9. 检查应用日志
    echo "\n9. 检查应用日志...\n";
    $appLogFile = __DIR__ . '/runtime/log/app.log';
    if (file_exists($appLogFile)) {
        echo "应用日志文件存在\n";
        $logSize = filesize($appLogFile);
        echo "日志文件大小: {$logSize} bytes\n";
        
        // 查找包含media_history的日志行
        $lines = file($appLogFile);
        echo "包含media_history的日志:\n";
        foreach ($lines as $line) {
            if (strpos($line, 'media_history') !== false || strpos($line, 'getLatestSeen') !== false || strpos($line, '播放记录') !== false) {
                echo "  " . trim($line) . "\n";
            }
        }
    } else {
        echo "应用日志文件不存在\n";
    }
    
    echo "\n=== 调试完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}