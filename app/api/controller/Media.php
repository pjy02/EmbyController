<?php

namespace app\api\controller;

use app\api\model\EmbyUserModel;
use app\api\model\MediaHistoryModel;
use app\api\model\TelegramModel;
use app\api\model\UserModel;
use think\facade\Cache;
use think\facade\Config;
use app\BaseController;
use Telegram\Bot\Api;
use think\facade\Request;
use WebSocket\Client;

class Media extends BaseController
{
    public function index()
    {
        $time = time();
        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'time' => $time
            ]
        ]);
    }

    public function ping()
    {
        $time = time();
        return json([
            'code' => 200,
            'msg' => 'pong',
            'data' => [
                'time' => $time
            ]
        ]);
    }

    public function webhook()
    {
        try {
            // 获取get参数
            $data = Request::get();
            if (isset($data['key']) && $data['key'] == Config::get('media.crontabKey')) {
                $data = Request::param();
                if (isset($data['Event']) && $data['Event'] != '') {
                    $user = null;
                    if (isset($data['User']) && $data['User'] != '') {
                        $embyUserModel = new EmbyUserModel();
                        $embyUser = $embyUserModel->where('embyId', $data['User']['Id'])->find();
                        if ($embyUser) {
                            $userModel = new UserModel();
                            $user = $userModel->where('id', $embyUser['userId'])->find();
                        }
                    }
                    $item = null;
                    if (isset($data['Item']) && $data['Item'] != '') {
                        $item = $data['Item'];
                    }
                    $session = null;
                    if (isset($data['Session']) && $data['Session'] != '') {
                        $session = $data['Session'];
                    }
                    $playbackInfo = null;
                    if (isset($data['PlaybackInfo']) && $data['PlaybackInfo'] != '') {
                        $playbackInfo = $data['PlaybackInfo'];
                    }
                    $type = 0;
                    if ($data['Event'] == 'system.notificationtest') {
                        // 测试通知
                    } elseif ($data['Event'] == 'playback.start') {
                        // 开始播放
                        $type = 1;
                    } elseif ($data['Event'] == 'playback.pause') {
                        // 暂停播放
                        $type = 2;
                    } elseif ($data['Event'] == 'playback.unpause') {
                        // 取消暂停
                        $type = 1;
                    } elseif ($data['Event'] == 'playback.stop') {
                        // 停止播放
                        $type = 3;
                    }

                    // 播放记录
                    if ($user && $item && $playbackInfo && $session && $type > 0) {
                        $mediaHistoryModel = new MediaHistoryModel();
                        $mediaHistory = $mediaHistoryModel->where([
                            'userId' => $user['id'],
                            'mediaId' => $item['Id'],
                        ])->find();
                        if ($mediaHistory) {
                            // 更新type为1
                            $mediaHistory->type = $type;
                            $mediaHistory->historyInfo = json_encode([
                                'session' => $session,
                                'item' => $item,
                                'percentage' => (isset($data['PlaybackInfo']['PositionTicks']) && isset($data['Item']['RunTimeTicks']))?($data['PlaybackInfo']['PositionTicks'] / $data['Item']['RunTimeTicks']):0,
                            ]);
                            $mediaHistory->save();
                        } else {
                            $mediaHistoryModel->save([
                                'type' => $type,
                                'userId' => $user['id'],
                                'mediaId' => $item['Id'],
                                'mediaName' => $item['Name'],
                                'mediaYear' => isset($item['PremiereDate'])?date('Y', strtotime($item['PremiereDate'])):null,
                                'historyInfo' => json_encode([
                                    'session' => $session,
                                    'item' => $item,
                                    'percentage' => (isset($data['PlaybackInfo']['PositionTicks']) && isset($data['Item']['RunTimeTicks']))?($data['PlaybackInfo']['PositionTicks'] / $data['Item']['RunTimeTicks']):0,
                                ])
                            ]);
                        }
                    }

                    // 播放完成通知
                    if ($user && $item && $playbackInfo && $type == 3 && $data['PlaybackInfo']['PositionTicks'] / $data['Item']['RunTimeTicks'] > 0.8) {
                        // 播放完成

                        if (isset($item['Type']) && $item['Type'] != '') {
                            $msg = '';
                            if ($item['Type'] == 'Movie') {
                                $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，现在用户刚刚看完了电影《" . $item['Name'] . "》，这部电影的简介是：" . $item['Overview'] . "，请你根据这部电影的特点，还有你的知识库，对用户表示感谢观看这部电影，并且期望用户在我的网站多看电影，回答内容中要包含电影名，直接告诉我需要告诉用户的内容。";
                                $msg = xfyun($inComeMessage);
                            } else if ($item['Type'] == 'Episode') {
                                $inComeMessage = "你是RandallAnjie.com网站下的的专属机器人，现在用户刚刚看完了剧集《" . $item['SeriesName'] . "》中名为《" . $item['Name'] . "》的一集，这部剧集的简介是：" . $item['Overview'] . "，请你根据这部剧集的特点，还有你的知识库，对用户表示感谢观看这部剧集，并且期望用户在我的网站多看剧集，回答内容中要包含剧集名称和这一集的名称，直接告诉我需要告诉用户的内容。";
                                $msg = xfyun($inComeMessage);
                            }
                            if ($msg != '') {
                                sendStationMessage($user['id'], $msg);
                                $telegramModel = new TelegramModel();
                                $telegramUser = $telegramModel->where('userId', $user['id'])->find();
                                if ($telegramUser) {
                                    $telegramUserInfoArray = json_decode(json_encode($telegramUser['userInfo']), true);
                                    if (isset($telegramUserInfoArray['notification']) && $telegramUserInfoArray['notification'] == 1) {
                                        $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
                                        $telegram->sendMessage([
                                            'chat_id' => $telegramUser['telegramId'],
                                            'text' => $msg,
                                            'parse_mode' => 'HTML',
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $message = '第' . $exception->getLine() . '行发生错误：' . $exception->getMessage();
            // 错误内容
            $telegram = new Api(Config::get('telegram.botConfig.bots.randallanjie_bot.token'));
            $telegram->sendMessage([
                'chat_id' => Config::get('telegram.adminId'),
                'text' => $message . PHP_EOL . 'get: ' . json_encode(Request::get()) . PHP_EOL . 'post: ' . json_encode(Request::post()),
                'parse_mode' => 'HTML',
            ]);
            return false;
        }
    }
}
