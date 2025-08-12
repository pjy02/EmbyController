<?php

namespace app\media\controller;

use app\BaseController;
use app\media\model\MediaHistoryModel;
use app\media\model\UserModel;
use think\facade\Request;
use think\facade\Session;
use think\facade\Db;
use think\facade\Log;

class UserNew extends BaseController
{
    /**
     * 重构后的最近观看功能
     * 包含完整的错误处理、调试信息和数据验证
     */
    public function getLatestSeen()
    {
        try {
            // 1. 验证用户登录状态
            if (Session::get('r_user') == null) {
                Log::warning("getLatestSeen - 用户未登录");
                return json(['code' => 400, 'message' => '未登录']);
            }
            
            // 2. 验证请求方法
            if (!Request::isPost()) {
                Log::warning("getLatestSeen - 非POST请求");
                return json(['code' => 405, 'message' => '请求方法错误']);
            }
            
            // 3. 获取请求参数
            $data = Request::post();
            $page = isset($data['page']) ? (int)$data['page'] : 1;
            $pageSize = isset($data['pageSize']) ? (int)$data['pageSize'] : 10;
            
            // 4. 参数验证
            if ($page < 1) $page = 1;
            if ($pageSize < 1 || $pageSize > 100) $pageSize = 10;
            
            // 5. 获取用户信息
            $userId = Session::get('r_user')->id;
            Log::info("getLatestSeen开始 - 用户ID: {$userId}, 页码: {$page}, 每页大小: {$pageSize}");
            
            // 6. 验证用户是否存在
            $userModel = new UserModel();
            $user = $userModel->where('id', $userId)->find();
            if (!$user) {
                Log::error("getLatestSeen - 用户不存在: {$userId}");
                return json(['code' => 404, 'message' => '用户不存在']);
            }
            
            // 7. 检查数据库连接
            try {
                Db::connect()->query("SELECT 1");
                Log::info("getLatestSeen - 数据库连接正常");
            } catch (\Exception $e) {
                Log::error("getLatestSeen - 数据库连接失败: " . $e->getMessage());
                return json(['code' => 500, 'message' => '数据库连接失败']);
            }
            
            // 8. 检查表是否存在
            try {
                $tables = Db::connect()->getTables();
                $tableName = config('database.connections.mysql.prefix') . 'media_history';
                if (!in_array($tableName, $tables)) {
                    Log::error("getLatestSeen - media_history表不存在");
                    return json(['code' => 500, 'message' => '数据表不存在']);
                }
                Log::info("getLatestSeen - media_history表存在");
            } catch (\Exception $e) {
                Log::error("getLatestSeen - 检查表失败: " . $e->getMessage());
                return json(['code' => 500, 'message' => '检查数据表失败']);
            }
            
            // 9. 查询媒体历史记录
            $mediaHistoryModel = new MediaHistoryModel();
            
            // 先查询总记录数
            $totalCount = $mediaHistoryModel->where('userId', $userId)->count();
            Log::info("getLatestSeen - 用户{$userId}总记录数: {$totalCount}");
            
            if ($totalCount == 0) {
                Log::info("getLatestSeen - 用户{$userId}无观看记录");
                return json([
                    'code' => 200, 
                    'message' => '暂无观看记录', 
                    'data' => [],
                    'total' => 0,
                    'page' => $page,
                    'pageSize' => $pageSize
                ]);
            }
            
            // 分页查询记录
            $myLastSeen = $mediaHistoryModel
                ->where('userId', $userId)
                ->order('updatedAt', 'desc')
                ->page($page, $pageSize)
                ->select();
            
            Log::info("getLatestSeen - 查询到记录数: " . count($myLastSeen));
            
            // 记录查询结果详情
            if (count($myLastSeen) > 0) {
                foreach ($myLastSeen as $index => $record) {
                    Log::info("getLatestSeen - 记录{$index}: " . json_encode([
                        'id' => $record->id,
                        'mediaId' => $record->mediaId,
                        'mediaName' => $record->mediaName,
                        'type' => $record->type,
                        'updatedAt' => $record->updatedAt
                    ]));
                }
            }
            
            // 10. 检查查询结果是否为空
            if (empty($myLastSeen)) {
                Log::warning("getLatestSeen - 分页查询结果为空，可能页码超出范围");
                return json([
                    'code' => 200, 
                    'message' => '暂无更多记录', 
                    'data' => [],
                    'total' => $totalCount,
                    'page' => $page,
                    'pageSize' => $pageSize
                ]);
            }
            
            // 11. 格式化返回数据
            $formattedData = [];
            foreach ($myLastSeen as $record) {
                $formattedData[] = [
                    'id' => $record->id,
                    'mediaId' => $record->mediaId,
                    'mediaName' => $record->mediaName,
                    'mediaYear' => $record->mediaYear,
                    'type' => $record->type,
                    'historyInfo' => $record->historyInfo,
                    'createdAt' => $record->createdAt,
                    'updatedAt' => $record->updatedAt,
                    'typeText' => $this->getTypeText($record->type)
                ];
            }
            
            Log::info("getLatestSeen - 成功返回数据，记录数: " . count($formattedData));
            
            // 12. 返回成功响应
            return json([
                'code' => 200, 
                'message' => '获取成功', 
                'data' => $formattedData,
                'total' => $totalCount,
                'page' => $page,
                'pageSize' => $pageSize
            ]);
            
        } catch (\Exception $e) {
            // 捕获所有异常
            Log::error("getLatestSeen异常: " . $e->getMessage());
            Log::error("getLatestSeen异常堆栈: " . $e->getTraceAsString());
            return json([
                'code' => 500, 
                'message' => '系统错误，请稍后重试',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取播放类型文本描述
     */
    private function getTypeText($type)
    {
        switch ($type) {
            case 1:
                return '播放中';
            case 2:
                return '暂停';
            case 3:
                return '完成播放';
            default:
                return '未知';
        }
    }
    
    /**
     * 测试数据写入功能
     * 用于调试数据写入问题
     */
    public function testAddRecord()
    {
        try {
            if (Session::get('r_user') == null) {
                return json(['code' => 400, 'message' => '未登录']);
            }
            
            $userId = Session::get('r_user')->id;
            
            // 创建测试记录
            $mediaHistoryModel = new MediaHistoryModel();
            $testData = [
                'userId' => $userId,
                'mediaId' => 'test_' . time(),
                'mediaName' => '测试媒体 ' . date('Y-m-d H:i:s'),
                'mediaYear' => '2024',
                'type' => 1,
                'historyInfo' => [
                    'test' => true,
                    'timestamp' => time(),
                    'message' => '这是一条测试记录'
                ]
            ];
            
            $result = $mediaHistoryModel->save($testData);
            
            if ($result) {
                Log::info("testAddRecord - 测试记录添加成功: " . json_encode($testData));
                return json([
                    'code' => 200, 
                    'message' => '测试记录添加成功',
                    'data' => $testData
                ]);
            } else {
                Log::error("testAddRecord - 测试记录添加失败");
                return json(['code' => 500, 'message' => '测试记录添加失败']);
            }
            
        } catch (\Exception $e) {
            Log::error("testAddRecord异常: " . $e->getMessage());
            return json([
                'code' => 500, 
                'message' => '系统错误',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取用户统计信息
     */
    public function getStatistics()
    {
        try {
            if (Session::get('r_user') == null) {
                return json(['code' => 400, 'message' => '未登录']);
            }
            
            $userId = Session::get('r_user')->id;
            $mediaHistoryModel = new MediaHistoryModel();
            
            // 总观看记录数
            $totalCount = $mediaHistoryModel->where('userId', $userId)->count();
            
            // 按类型统计
            $typeStats = $mediaHistoryModel
                ->field('type, COUNT(*) as count')
                ->where('userId', $userId)
                ->group('type')
                ->select();
            
            // 最近观看时间
            $latestRecord = $mediaHistoryModel
                ->where('userId', $userId)
                ->order('updatedAt', 'desc')
                ->find();
            
            $statistics = [
                'totalCount' => $totalCount,
                'typeStats' => $typeStats,
                'latestWatchTime' => $latestRecord ? $latestRecord->updatedAt : null,
                'latestMediaName' => $latestRecord ? $latestRecord->mediaName : null
            ];
            
            Log::info("getStatistics - 用户{$userId}统计信息: " . json_encode($statistics));
            
            return json([
                'code' => 200, 
                'message' => '获取成功', 
                'data' => $statistics
            ]);
            
        } catch (\Exception $e) {
            Log::error("getStatistics异常: " . $e->getMessage());
            return json([
                'code' => 500, 
                'message' => '系统错误',
                'error' => $e->getMessage()
            ]);
        }
    }
}