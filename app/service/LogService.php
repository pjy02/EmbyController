<?php

namespace app\service;

use think\facade\Config;
use think\facade\Cache;

class LogService
{
    /**
     * 获取日志文件列表
     * @param int $page
     * @param int $pageSize
     * @param string $sort
     * @param string $order
     * @return array
     */
    public function getLogFiles($page = 1, $pageSize = 20, $sort = 'mtime', $order = 'desc')
    {
        $logPath = $this->getLogPath();
        $files = [];
        
        // 获取所有日志文件
        $logFiles = glob($logPath . '/*.log');
        
        foreach ($logFiles as $file) {
            $fileInfo = $this->getLogFileInfo($file);
            if ($fileInfo) {
                $files[] = $fileInfo;
            }
        }
        
        // 排序
        usort($files, function($a, $b) use ($sort, $order) {
            $aValue = $a[$sort];
            $bValue = $b[$sort];
            
            if ($order === 'asc') {
                return $aValue <=> $bValue;
            } else {
                return $bValue <=> $aValue;
            }
        });
        
        // 分页
        $total = count($files);
        $start = ($page - 1) * $pageSize;
        $items = array_slice($files, $start, $pageSize);
        
        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'items' => $items
        ];
    }

    /**
     * 删除指定日志文件
     * @param string $fileName
     * @return bool
     * @throws \Exception
     */
    public function deleteLogFile($fileName)
    {
        $logPath = $this->getLogPath();
        $filePath = $logPath . '/' . $fileName;
        
        // 安全检查：确保文件在日志目录内
        if (strpos(realpath($filePath), realpath($logPath)) !== 0) {
            throw new \Exception('非法的文件路径');
        }
        
        if (!file_exists($filePath)) {
            throw new \Exception('文件不存在');
        }
        
        if (!is_file($filePath)) {
            throw new \Exception('不是有效的文件');
        }
        
        // 检查文件扩展名
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'log') {
            throw new \Exception('只能删除.log文件');
        }
        
        return unlink($filePath);
    }

    /**
     * 清理过期日志文件
     * @return int 删除的文件数量
     * @throws \Exception
     */
    public function cleanExpiredLogs()
    {
        $logPath = $this->getLogPath();
        $retentionDays = $this->getRetentionDays();
        $cutoffTime = time() - ($retentionDays * 86400);
        
        $logFiles = glob($logPath . '/*.log');
        $deletedCount = 0;
        
        foreach ($logFiles as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }

    /**
     * 预览日志文件内容
     * @param string $fileName
     * @param int $lines
     * @return array
     * @throws \Exception
     */
    public function previewLogFile($fileName, $lines = 100)
    {
        $logPath = $this->getLogPath();
        $filePath = $logPath . '/' . $fileName;
        
        // 安全检查
        if (strpos(realpath($filePath), realpath($logPath)) !== 0) {
            throw new \Exception('非法的文件路径');
        }
        
        if (!file_exists($filePath)) {
            throw new \Exception('文件不存在');
        }
        
        if (!is_file($filePath)) {
            throw new \Exception('不是有效的文件');
        }
        
        // 检查文件扩展名
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'log') {
            throw new \Exception('只能预览.log文件');
        }
        
        // 检查文件大小
        $maxFileSize = $this->getMaxFileSize();
        if (filesize($filePath) > $maxFileSize) {
            throw new \Exception('文件过大，无法预览');
        }
        
        // 读取文件内容
        $content = [];
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX); // 移动到文件末尾
        $totalLines = $file->key();
        
        // 计算起始行
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        while (!$file->eof() && count($content) < $lines) {
            $line = trim($file->current());
            if ($line !== '') {
                $content[] = $line;
            }
            $file->next();
        }
        
        return [
            'file_name' => $fileName,
            'total_lines' => $totalLines,
            'preview_lines' => count($content),
            'content' => $content
        ];
    }

    /**
     * 获取日志文件详细信息
     * @param string $filePath
     * @return array|null
     */
    private function getLogFileInfo($filePath)
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }
        
        $fileName = basename($filePath);
        
        return [
            'file_name' => $fileName,
            'size' => filesize($filePath),
            'size_formatted' => $this->formatFileSize(filesize($filePath)),
            'mtime' => filemtime($filePath),
            'mtime_formatted' => date('Y-m-d H:i:s', filemtime($filePath)),
            'ctime' => filectime($filePath),
            'ctime_formatted' => date('Y-m-d H:i:s', filectime($filePath))
        ];
    }

    /**
     * 获取日志文件存储路径
     * @return string
     */
    private function getLogPath()
    {
        // 优先从配置获取
        $logConfig = Config::get('log.channels.file');
        if (!empty($logConfig['path'])) {
            return $logConfig['path'];
        }
        
        // 默认路径
        return runtime_path() . 'log';
    }

    /**
     * 获取日志保留天数
     * @return int
     */
    private function getRetentionDays()
    {
        try {
            $configModel = new \app\media\model\SysConfigModel();
            $config = $configModel->getLogConfig();
            return isset($config['retention_days']) ? intval($config['retention_days']) : 7;
        } catch (\Exception $e) {
            return 7; // 默认7天
        }
    }

    /**
     * 获取最大文件大小
     * @return int
     */
    private function getMaxFileSize()
    {
        try {
            $configModel = new \app\media\model\SysConfigModel();
            $config = $configModel->getLogConfig();
            $sizeMB = isset($config['max_file_size']) ? intval($config['max_file_size']) : 10;
            return $sizeMB * 1024 * 1024; // 转换为字节
        } catch (\Exception $e) {
            return 10485760; // 默认10MB
        }
    }

    /**
     * 格式化文件大小
     * @param int $size
     * @return string
     */
    private function formatFileSize($size)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}