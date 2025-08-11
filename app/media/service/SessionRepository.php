<?php

namespace app\media\service;

use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;

/**
 * 会话仓库类
 * 负责Emby会话数据的获取、缓存和管理
 */
class SessionRepository
{
    /**
     * 获取用户会话列表
     * 
     * @param string $embyId Emby用户ID
     * @param bool $useCache 是否使用缓存
     * @return array
     */
    public function getUserSessions($embyId, $useCache = true)
    {
        try {
            $cacheKey = 'userSessions-' . $embyId;
            
            if ($useCache && Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $allSessions = $this->getAllSessions();
            $userSessions = [];
            
            foreach ($allSessions as $session) {
                if (isset($session['UserId']) && $session['UserId'] == $embyId) {
                    $userSessions[] = $session;
                }
            }
            
            // 缓存10秒
            if ($useCache) {
                Cache::set($cacheKey, $userSessions, 10);
            }
            
            return $userSessions;
            
        } catch (\Exception $e) {
            Log::error('获取用户会话列表失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取所有会话列表
     * 
     * @return array
     */
    public function getAllSessions()
    {
        try {
            $cacheKey = 'allSessions';
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            $url = Config::get('media.urlBase') . 'Sessions?api_key=' . Config::get('media.apiKey');
            $ch = curl_init($url);
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode != 200) {
                Log::error('获取会话列表失败，HTTP状态码: ' . $httpCode);
                return [];
            }
            
            $sessions = json_decode($response, true);
            
            // 缓存5秒
            Cache::set($cacheKey, $sessions, 5);
            
            return $sessions ?: [];
            
        } catch (\Exception $e) {
            Log::error('获取所有会话列表失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据会话ID获取会话信息
     * 
     * @param string $sessionId 会话ID
     * @return array|null
     */
    public function getSessionById($sessionId)
    {
        try {
            $sessions = $this->getAllSessions();
            
            foreach ($sessions as $session) {
                if (isset($session['Id']) && $session['Id'] === $sessionId) {
                    return $session;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('根据会话ID获取会话信息失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 根据设备ID获取会话信息
     * 
     * @param string $deviceId 设备ID
     * @return array|null
     */
    public function getSessionByDeviceId($deviceId)
    {
        try {
            $sessions = $this->getAllSessions();
            
            foreach ($sessions as $session) {
                if (isset($session['DeviceId']) && $session['DeviceId'] === $deviceId) {
                    return $session;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('根据设备ID获取会话信息失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取活跃会话（有播放活动的会话）
     * 
     * @param string $embyId Emby用户ID
     * @return array
     */
    public function getActiveSessions($embyId)
    {
        try {
            $sessions = $this->getUserSessions($embyId);
            $activeSessions = [];
            
            foreach ($sessions as $session) {
                if ($this->isActiveSession($session)) {
                    $activeSessions[] = $session;
                }
            }
            
            return $activeSessions;
            
        } catch (\Exception $e) {
            Log::error('获取活跃会话失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 检查会话是否活跃（有播放活动）
     * 
     * @param array $session 会话信息
     * @return bool
     */
    public function isActiveSession($session)
    {
        try {
            // 检查是否有播放活动
            if (isset($session['NowPlayingItem']) && !empty($session['NowPlayingItem'])) {
                return true;
            }
            
            // 检查是否有播放进度
            if (isset($session['PlayState']) && isset($session['PlayState']['PositionTicks'])) {
                return true;
            }
            
            // 检查会话是否最近活跃（5分钟内）
            if (isset($session['LastActivityDate'])) {
                $lastActivity = strtotime($session['LastActivityDate']);
                $fiveMinutesAgo = time() - 300; // 5分钟前
                return $lastActivity >= $fiveMinutesAgo;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('检查会话活跃状态失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取会话的播放信息
     * 
     * @param array $session 会话信息
     * @return array
     */
    public function getSessionPlaybackInfo($session)
    {
        try {
            $playbackInfo = [
                'is_playing' => false,
                'item_name' => '',
                'item_type' => '',
                'position' => 0,
                'duration' => 0,
                'percentage' => 0,
                'state' => 'stopped'
            ];
            
            if (isset($session['NowPlayingItem']) && !empty($session['NowPlayingItem'])) {
                $item = $session['NowPlayingItem'];
                $playbackInfo['is_playing'] = true;
                $playbackInfo['item_name'] = $item['Name'] ?? '';
                $playbackInfo['item_type'] = $item['Type'] ?? '';
                
                // 获取播放进度
                if (isset($session['PlayState'])) {
                    $playState = $session['PlayState'];
                    $positionTicks = $playState['PositionTicks'] ?? 0;
                    $durationTicks = $item['RunTimeTicks'] ?? 0;
                    
                    $playbackInfo['position'] = $positionTicks / 10000000; // 转换为秒
                    $playbackInfo['duration'] = $durationTicks / 10000000; // 转换为秒
                    
                    if ($durationTicks > 0) {
                        $playbackInfo['percentage'] = $positionTicks / $durationTicks;
                    }
                    
                    // 获取播放状态
                    $playbackInfo['state'] = $playState['State'] ?? 'stopped';
                }
            }
            
            return $playbackInfo;
            
        } catch (\Exception $e) {
            Log::error('获取会话播放信息失败: ' . $e->getMessage());
            return [
                'is_playing' => false,
                'item_name' => '',
                'item_type' => '',
                'position' => 0,
                'duration' => 0,
                'percentage' => 0,
                'state' => 'stopped'
            ];
        }
    }
    
    /**
     * 清除会话缓存
     * 
     * @param string|null $embyId Emby用户ID，为空时清除所有缓存
     * @return bool
     */
    public function clearCache($embyId = null)
    {
        try {
            if ($embyId) {
                Cache::delete('userSessions-' . $embyId);
            } else {
                Cache::delete('allSessions');
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('清除会话缓存失败: ' . $e->getMessage());
            return false;
        }
    }
}