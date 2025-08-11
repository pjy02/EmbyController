<?php

namespace app\media\event;

/**
 * 设备状态变更事件
 * 当设备状态发生变化时触发此事件
 */
class DeviceStatusChangedEvent
{
    /**
     * @var array 设备信息
     */
    private $device;
    
    /**
     * @var string 状态变更类型 (online/offline/playing/paused/stopped)
     */
    private $statusType;
    
    /**
     * @var array 变更前的状态
     */
    private $oldStatus;
    
    /**
     * @var array 变更后的状态
     */
    private $newStatus;
    
    /**
     * @var string 用户ID
     */
    private $userId;
    
    /**
     * @var array 会话信息（如果有）
     */
    private $session;
    
    /**
     * @var string 事件时间戳
     */
    private $timestamp;
    
    /**
     * 构造函数
     * 
     * @param array $device 设备信息
     * @param string $statusType 状态变更类型
     * @param array $oldStatus 变更前的状态
     * @param array $newStatus 变更后的状态
     * @param string $userId 用户ID
     * @param array|null $session 会话信息
     */
    public function __construct(
        array $device,
        string $statusType,
        array $oldStatus,
        array $newStatus,
        string $userId,
        ?array $session = null
    ) {
        $this->device = $device;
        $this->statusType = $statusType;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->userId = $userId;
        $this->session = $session;
        $this->timestamp = date('Y-m-d H:i:s');
    }
    
    /**
     * 获取设备信息
     * 
     * @return array
     */
    public function getDevice(): array
    {
        return $this->device;
    }
    
    /**
     * 获取状态变更类型
     * 
     * @return string
     */
    public function getStatusType(): string
    {
        return $this->statusType;
    }
    
    /**
     * 获取变更前的状态
     * 
     * @return array
     */
    public function getOldStatus(): array
    {
        return $this->oldStatus;
    }
    
    /**
     * 获取变更后的状态
     * 
     * @return array
     */
    public function getNewStatus(): array
    {
        return $this->newStatus;
    }
    
    /**
     * 获取用户ID
     * 
     * @return string
     */
    public function getUserId(): string
    {
        return $this->userId;
    }
    
    /**
     * 获取会话信息
     * 
     * @return array|null
     */
    public function getSession(): ?array
    {
        return $this->session;
    }
    
    /**
     * 获取事件时间戳
     * 
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }
    
    /**
     * 获取事件数据（用于日志记录）
     * 
     * @return array
     */
    public function getEventData(): array
    {
        return [
            'device_id' => $this->device['deviceId'] ?? '',
            'device_name' => $this->device['deviceName'] ?? '',
            'status_type' => $this->statusType,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'user_id' => $this->userId,
            'session_id' => $this->session['Id'] ?? null,
            'timestamp' => $this->timestamp
        ];
    }
}