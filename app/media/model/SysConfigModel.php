<?php

namespace app\media\model;

use think\Model;

class SysConfigModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'config';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'appName' => 'varchar',
        'key' => 'varchar',
        'value' => 'text',
        'type' => 'int',
        'status' => 'int',
    ];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

    /**
     * 获取日志配置
     * @return array
     */
    public function getLogConfig()
    {
        $config = [];
        
        // 获取保留天数配置
        $retentionConfig = $this->where('key', 'log_retention_days')->find();
        $config['retention_days'] = $retentionConfig ? intval($retentionConfig->value) : 7;
        
        // 获取自动清理配置
        $autoCleanConfig = $this->where('key', 'log_auto_clean')->find();
        $config['auto_clean'] = $autoCleanConfig ? intval($autoCleanConfig->value) : 0;
        
        // 获取最大文件大小配置
        $maxFileSizeConfig = $this->where('key', 'log_max_file_size')->find();
        $config['max_file_size'] = $maxFileSizeConfig ? intval($maxFileSizeConfig->value) : 10;
        
        return $config;
    }

    /**
     * 保存日志配置
     * @param array $data
     * @return bool
     */
    public function saveLogConfig($data)
    {
        try {
            // 保存保留天数
            $retentionDays = isset($data['retention_days']) ? intval($data['retention_days']) : 7;
            $this->saveConfigValue('log_retention_days', $retentionDays);
            
            // 保存自动清理配置
            $autoClean = isset($data['auto_clean']) ? (intval($data['auto_clean']) > 0 ? 1 : 0) : 0;
            $this->saveConfigValue('log_auto_clean', $autoClean);
            
            // 保存最大文件大小配置
            $maxFileSize = isset($data['max_file_size']) ? intval($data['max_file_size']) : 10;
            $this->saveConfigValue('log_max_file_size', $maxFileSize);
            
            return true;
        } catch (\Exception $e) {
            throw new \Exception('保存配置失败：' . $e->getMessage());
        }
    }

    /**
     * 保存单个配置值
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    private function saveConfigValue($key, $value)
    {
        $config = $this->where('key', $key)->find();
        
        if ($config) {
            // 更新现有配置
            $config->value = strval($value);
            return $config->save();
        } else {
            // 创建新配置
            return $this->create([
                'key' => $key,
                'value' => strval($value),
                'description' => $this->getConfigDescription($key)
            ]) ? true : false;
        }
    }

    /**
     * 获取配置描述
     * @param string $key
     * @return string
     */
    private function getConfigDescription($key)
    {
        $descriptions = [
            'log_retention_days' => '日志文件保留天数',
            'log_auto_clean' => '是否启用自动清理过期日志',
            'log_max_file_size' => '日志预览最大文件大小(MB)'
        ];
        
        return isset($descriptions[$key]) ? $descriptions[$key] : '';
    }

}