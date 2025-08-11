<?php

namespace app\media\event;

use think\facade\Log;

/**
 * 事件分发器
 * 负责事件的统一管理和分发
 */
class EventDispatcher
{
    /**
     * 监听器映射
     * 
     * @var array
     */
    private $listeners = [];
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->listeners = [];
        $this->loadListenersFromConfig();
    }
    
    /**
     * 从配置文件加载监听器
     */
    private function loadListenersFromConfig()
    {
        $config = config('event.listeners', []);
        foreach ($config as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }
    
    /**
     * 注册监听器
     * 
     * @param string $eventType 事件类型
     * @param mixed $listener 监听器（类名或对象）
     * @return void
     */
    public function listen(string $eventType, $listener): void
    {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }
        
        $this->listeners[$eventType][] = $listener;
    }
    
    /**
     * 分发事件
     * 
     * @param object $event 事件对象
     * @return array 执行结果
     */
    public function dispatch(object $event): array
    {
        $eventType = get_class($event);
        $results = [];
        
        if (!isset($this->listeners[$eventType])) {
            Log::warning('没有找到事件的监听器', [
                'event_type' => $eventType
            ]);
            return $results;
        }
        
        Log::info('开始分发事件', [
            'event_type' => $eventType,
            'listener_count' => count($this->listeners[$eventType])
        ]);
        
        foreach ($this->listeners[$eventType] as $listener) {
            try {
                if (is_string($listener)) {
                    // 类名字符串，创建实例
                    $listenerInstance = new $listener();
                    $result = $listenerInstance->handle($event);
                } elseif (is_object($listener) && method_exists($listener, 'handle')) {
                    // 对象，直接调用
                    $result = $listener->handle($event);
                } else {
                    Log::error('无效的监听器格式', [
                        'event_type' => $eventType,
                        'listener' => $listener
                    ]);
                    continue;
                }
                
                $results[] = [
                    'listener' => is_string($listener) ? $listener : get_class($listener),
                    'result' => $result
                ];
                
                Log::info('监听器执行完成', [
                    'event_type' => $eventType,
                    'listener' => is_string($listener) ? $listener : get_class($listener),
                    'result' => $result
                ]);
                
            } catch (\Exception $e) {
                Log::error('监听器执行失败', [
                    'event_type' => $eventType,
                    'listener' => is_string($listener) ? $listener : get_class($listener),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $results[] = [
                    'listener' => is_string($listener) ? $listener : get_class($listener),
                    'result' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 获取所有监听器
     * 
     * @return array
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }
    
    /**
     * 获取指定事件类型的监听器
     * 
     * @param string $eventType 事件类型
     * @return array
     */
    public function getEventListeners(string $eventType): array
    {
        return $this->listeners[$eventType] ?? [];
    }
    
    /**
     * 移除监听器
     * 
     * @param string $eventType 事件类型
     * @param mixed $listener 监听器
     * @return bool
     */
    public function removeListener(string $eventType, $listener): bool
    {
        if (!isset($this->listeners[$eventType])) {
            return false;
        }
        
        $key = array_search($listener, $this->listeners[$eventType]);
        if ($key !== false) {
            unset($this->listeners[$eventType][$key]);
            return true;
        }
        
        return false;
    }
    
    /**
     * 清空所有监听器
     * 
     * @return void
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
    }
}