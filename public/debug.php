<?php
// Web调试页面：检查media_history表状态
header('Content-Type: text/html; charset=utf-8');

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "    <title>Media History 调试报告</title>\n";
echo "    <meta charset='utf-8'>\n";
echo "    <style>\n";
echo "        body { font-family: Arial, sans-serif; margin: 20px; }\n";
echo "        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }\n";
echo "        .success { background-color: #d4edda; border-color: #c3e6cb; }\n";
echo "        .error { background-color: #f8d7da; border-color: #f5c6cb; }\n";
echo "        .warning { background-color: #fff3cd; border-color: #ffeaa7; }\n";
echo "        pre { background-color: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }\n";
echo "        table { border-collapse: collapse; width: 100%; margin: 10px 0; }\n";
echo "        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\n";
echo "        th { background-color: #f2f2f2; }\n";
echo "    </style>\n";
echo "</head>\n";
echo "<body>\n";
echo "    <h1>Media History 调试报告</h1>\n";

try {
    // 1. 检查数据库连接
    echo "    <div class='section'>\n";
    echo "        <h2>1. 检查数据库连接</h2>\n";
    
    // 手动加载数据库配置
    $config = require __DIR__ . '/../config/database.php';
    $dbConfig = $config['connections']['mysql'];
    
    // 创建数据库连接
    $conn = new mysqli(
        $dbConfig['hostname'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database'],
        $dbConfig['hostport']
    );
    
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    
    echo "        <div class='success'>数据库连接成功</div>\n";
    echo "        <p>数据库信息:</p>\n";
    echo "        <ul>\n";
    echo "            <li>主机: {$dbConfig['hostname']}:{$dbConfig['hostport']}</li>\n";
    echo "            <li>数据库: {$dbConfig['database']}</li>\n";
    echo "            <li>用户: {$dbConfig['username']}</li>\n";
    echo "            <li>表前缀: {$dbConfig['prefix']}</li>\n";
    echo "        </ul>\n";
    
    // 2. 检查media_history表是否存在
    echo "        <h3>2. 检查media_history表</h3>\n";
    $tableName = $dbConfig['prefix'] . 'media_history';
    $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    
    if ($result->num_rows > 0) {
        echo "        <div class='success'>media_history表存在</div>\n";
        
        // 3. 检查表结构
        echo "        <h3>3. 检查表结构</h3>\n";
        $columns = $conn->query("SHOW COLUMNS FROM {$tableName}");
        echo "        <table>\n";
        echo "            <tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>\n";
        while ($column = $columns->fetch_assoc()) {
            echo "            <tr>\n";
            echo "                <td>{$column['Field']}</td>\n";
            echo "                <td>{$column['Type']}</td>\n";
            echo "                <td>{$column['Null']}</td>\n";
            echo "                <td>{$column['Key']}</td>\n";
            echo "                <td>{$column['Default']}</td>\n";
            echo "                <td>{$column['Extra']}</td>\n";
            echo "            </tr>\n";
        }
        echo "        </table>\n";
        
        // 4. 检查数据量
        echo "        <h3>4. 检查数据量</h3>\n";
        $countResult = $conn->query("SELECT COUNT(*) as total FROM {$tableName}");
        $count = $countResult->fetch_assoc()['total'];
        echo "        <p>总记录数: <strong>{$count}</strong></p>\n";
        
        if ($count > 0) {
            // 5. 检查最近10条记录
            echo "        <h3>5. 最近10条记录</h3>\n";
            $recentResult = $conn->query("SELECT * FROM {$tableName} ORDER BY updatedAt DESC LIMIT 10");
            echo "        <table>\n";
            echo "            <tr><th>ID</th><th>用户ID</th><th>媒体ID</th><th>媒体名称</th><th>类型</th><th>更新时间</th></tr>\n";
            while ($record = $recentResult->fetch_assoc()) {
                echo "            <tr>\n";
                echo "                <td>{$record['id']}</td>\n";
                echo "                <td>{$record['userId']}</td>\n";
                echo "                <td>{$record['mediaId']}</td>\n";
                echo "                <td>{$record['mediaName']}</td>\n";
                echo "                <td>{$record['type']}</td>\n";
                echo "                <td>{$record['updatedAt']}</td>\n";
                echo "            </tr>\n";
            }
            echo "        </table>\n";
            
            // 6. 按用户分组统计
            echo "        <h3>6. 按用户分组统计</h3>\n";
            $userStats = $conn->query("SELECT userId, COUNT(*) as count FROM {$tableName} GROUP BY userId");
            echo "        <table>\n";
            echo "            <tr><th>用户ID</th><th>记录数</th></tr>\n";
            while ($stat = $userStats->fetch_assoc()) {
                echo "            <tr>\n";
                echo "                <td>{$stat['userId']}</td>\n";
                echo "                <td>{$stat['count']}</td>\n";
                echo "            </tr>\n";
            }
            echo "        </table>\n";
        }
        
    } else {
        echo "        <div class='error'>media_history表不存在！</div>\n";
    }
    
    // 7. 检查device_history表
    echo "        <h3>7. 检查device_history表</h3>\n";
    $deviceTableName = $dbConfig['prefix'] . 'device_history';
    $deviceResult = $conn->query("SHOW TABLES LIKE '{$deviceTableName}'");
    
    if ($deviceResult->num_rows > 0) {
        echo "        <div class='success'>device_history表存在</div>\n";
        $deviceCount = $conn->query("SELECT COUNT(*) as total FROM {$deviceTableName}")->fetch_assoc()['total'];
        echo "        <p>device_history表记录数: <strong>{$deviceCount}</strong></p>\n";
    } else {
        echo "        <div class='error'>device_history表不存在</div>\n";
    }
    
    // 8. 检查所有表
    echo "        <h3>8. 数据库中的所有表</h3>\n";
    $tables = $conn->query("SHOW TABLES");
    echo "        <ul>\n";
    while ($table = $tables->fetch_array()) {
        echo "            <li>{$table[0]}</li>\n";
    }
    echo "        </ul>\n";
    
    $conn->close();
    echo "    </div>\n";
    
} catch (Exception $e) {
    echo "    <div class='section error'>\n";
    echo "        <h2>错误</h2>\n";
    echo "        <p><strong>错误信息:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "        <p><strong>错误文件:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>\n";
    echo "        <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
    echo "    </div>\n";
}

echo "    <div class='section'>\n";
echo "        <h2>测试建议</h2>\n";
echo "        <ol>\n";
echo "            <li>检查数据库连接配置是否正确</li>\n";
echo "            <li>确认media_history表是否已创建</li>\n";
echo "            <li>检查webhook是否正常工作</li>\n";
echo "            <li>查看应用日志文件</li>\n";
echo "            <li>测试播放事件是否正常触发</li>\n";
echo "        </ol>\n";
echo "    </div>\n";

echo "</body>\n";
echo "</html>\n";
?>