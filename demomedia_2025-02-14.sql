/*
 Navicat Premium Data Transfer

 Source Server         : Server1
 Source Server Type    : MySQL
 Source Server Version : 80400 (8.4.0)
 Source Host           : 127.0.0.1:3306
 Source Schema         : demomedia

 Target Server Type    : MySQL
 Target Server Version : 80400 (8.4.0)
 File Encoding         : 65001

 Date: 14/02/2025 15:03:41
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for rc_auth_licenses
-- ----------------------------
DROP TABLE IF EXISTS `rc_auth_licenses`;
CREATE TABLE `rc_auth_licenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `license_key` varchar(32) NOT NULL COMMENT '授权密钥',
  `app_name` varchar(50) NOT NULL DEFAULT 'ALL' COMMENT '应用名称,ALL表示全部应用',
  `ipv4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'IPv4地址/段列表，多个用逗号分隔',
  `ipv6` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'IPv6地址/段列表，多个用逗号分隔',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:0=禁用,1=启用',
  `expire_time` datetime DEFAULT NULL COMMENT '过期时间',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_key` (`license_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_auth_licenses
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_auth_logs
-- ----------------------------
DROP TABLE IF EXISTS `rc_auth_logs`;
CREATE TABLE `rc_auth_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `license_key` varchar(32) NOT NULL COMMENT '授权密钥',
  `ip_address` varchar(15) NOT NULL COMMENT '请求IP',
  `status` tinyint(1) DEFAULT '0' COMMENT '验证状态:0=失败,1=成功',
  `message` varchar(255) DEFAULT NULL COMMENT '验证消息',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_auth_logs
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_bet
-- ----------------------------
DROP TABLE IF EXISTS `rc_bet`;
CREATE TABLE `rc_bet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chatId` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '群组ID',
  `creatorId` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '创建者ID',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1进行中，2已开奖',
  `randomType` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `result` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '开奖结果',
  `createTime` datetime NOT NULL COMMENT '创建时间',
  `endTime` datetime NOT NULL COMMENT '结束时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_chatId` (`chatId`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=246 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC COMMENT='赌博记录表';

-- ----------------------------
-- Records of rc_bet
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_bet_participant
-- ----------------------------
DROP TABLE IF EXISTS `rc_bet_participant`;
CREATE TABLE `rc_bet_participant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `betId` int NOT NULL COMMENT '赌博ID',
  `telegramId` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '参与者TG ID',
  `userId` int NOT NULL COMMENT '用户ID',
  `type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '投注类型',
  `amount` decimal(10,2) NOT NULL COMMENT '投注金额',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0未开奖，1赢，2输',
  `winAmount` decimal(10,2) DEFAULT NULL COMMENT '赢得金额',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_betId` (`betId`) USING BTREE,
  KEY `idx_telegramId` (`telegramId`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1559 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC COMMENT='赌博参与记录表';

-- ----------------------------
-- Records of rc_bet_participant
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_config
-- ----------------------------
DROP TABLE IF EXISTS `rc_config`;
CREATE TABLE `rc_config` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'id',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `appName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '所属应用',
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '键',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '值',
  `type` int NOT NULL DEFAULT '0' COMMENT '此键值对的所属安全状态，0仅管理员可见，1登陆可见，2公开',
  `status` int NOT NULL DEFAULT '1' COMMENT '使用状态，1开启，0关闭',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='网站配置';

-- ----------------------------
-- Records of rc_config
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_emby_device
-- ----------------------------
DROP TABLE IF EXISTS `rc_emby_device`;
CREATE TABLE `rc_emby_device` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'createdAt',
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updatedAt',
  `lastUsedTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '上次使用时间',
  `lastUsedIp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '上次使用ip',
  `embyId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'emby注册用户id',
  `deviceId` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '设备id',
  `client` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '设备类型',
  `deviceName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '设备名称',
  `deviceInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '其他信息json',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `rc_emby_user_pk_2` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of rc_emby_device
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_emby_user
-- ----------------------------
DROP TABLE IF EXISTS `rc_emby_user`;
CREATE TABLE `rc_emby_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'createdAt',
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updatedAt',
  `activateTo` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '激活到某一时刻',
  `userId` int NOT NULL COMMENT '本系统用户id',
  `embyId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'emby注册用户id',
  `userInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '其他信息(json)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_emby_user_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2030 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_emby_user
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_exchange_code
-- ----------------------------
DROP TABLE IF EXISTS `rc_exchange_code`;
CREATE TABLE `rc_exchange_code` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '激活码',
  `type` int NOT NULL DEFAULT '0' COMMENT '0未使用，1已使用，-1已禁用',
  `exchangeType` int NOT NULL DEFAULT '1' COMMENT '可兑换类型（1激活，2按天续期，3按月续期，4充值余额）',
  `exchangeCount` int NOT NULL DEFAULT '1' COMMENT '兑换数量',
  `exchangeDate` timestamp NULL DEFAULT NULL COMMENT '兑换日期',
  `usedByUserId` int DEFAULT NULL COMMENT '被用户（ID）使用',
  `codeInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_exchange_code_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=781 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='激活码';

-- ----------------------------
-- Records of rc_exchange_code
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_finance_record
-- ----------------------------
DROP TABLE IF EXISTS `rc_finance_record`;
CREATE TABLE `rc_finance_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `userId` int DEFAULT NULL COMMENT '对应用户id',
  `action` int DEFAULT NULL COMMENT '1充值，2兑换兑换码，3使用余额',
  `count` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '充值消费则显示数量，兑换激活码填入对应激活码',
  `recordInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_finance_record_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22835 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='交易记录';

-- ----------------------------
-- Records of rc_finance_record
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_lottery
-- ----------------------------
DROP TABLE IF EXISTS `rc_lottery`;
CREATE TABLE `rc_lottery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '标题',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '描述',
  `prizes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '奖品列表(JSON)',
  `drawTime` timestamp NOT NULL COMMENT '开奖时间',
  `keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1' COMMENT '状态:-1禁用,1进行中,2已结束',
  `createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `chatId` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '群组ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_drawTime` (`drawTime`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC COMMENT='抽奖表';

-- ----------------------------
-- Records of rc_lottery
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_lottery_participant
-- ----------------------------
DROP TABLE IF EXISTS `rc_lottery_participant`;
CREATE TABLE `rc_lottery_participant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lotteryId` int NOT NULL COMMENT '抽奖ID',
  `telegramId` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '参与者TelegramID',
  `status` int NOT NULL DEFAULT '0' COMMENT '状态:0已参与,1已中奖',
  `prize` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '中奖奖品(json)',
  `createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '参与时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_lottery_telegram` (`lotteryId`,`telegramId`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=872 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC COMMENT='抽奖参与者表';

-- ----------------------------
-- Records of rc_lottery_participant
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_media_comment
-- ----------------------------
DROP TABLE IF EXISTS `rc_media_comment`;
CREATE TABLE `rc_media_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `userId` int NOT NULL COMMENT '用户id',
  `mediaId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '媒体id',
  `rating` double NOT NULL DEFAULT '5',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `mentions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `quotedComment` int DEFAULT NULL COMMENT '引用的评论id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_media_comment_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='评论';

-- ----------------------------
-- Records of rc_media_comment
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_media_history
-- ----------------------------
DROP TABLE IF EXISTS `rc_media_history`;
CREATE TABLE `rc_media_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `type` int NOT NULL DEFAULT '1' COMMENT '1播放中 2暂停 3完成播放',
  `userId` int NOT NULL,
  `mediaId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mediaName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mediaYear` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `historyInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_media_history_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8844 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='播放历史';

-- ----------------------------
-- Records of rc_media_history
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_media_info
-- ----------------------------
DROP TABLE IF EXISTS `rc_media_info`;
CREATE TABLE `rc_media_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mediaName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `mediaYear` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `mediaType` int NOT NULL DEFAULT '1' COMMENT '1电影 2剧集',
  `mediaMainId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Emby中对应的主要id，用于图片获取',
  `mediaInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_media_info_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_media_info
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_media_seek
-- ----------------------------
DROP TABLE IF EXISTS `rc_media_seek`;
CREATE TABLE `rc_media_seek` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userId` int NOT NULL COMMENT '请求用户ID',
  `title` varchar(255) NOT NULL COMMENT '影片名称',
  `description` text COMMENT '备注信息',
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '状态:0=已请求,1=管理员已确认,2=正在收集资源,3=已入库,-1=暂不收录',
  `statusRemark` varchar(255) DEFAULT NULL COMMENT '状态备注',
  `seekCount` int NOT NULL DEFAULT '1' COMMENT '同求人数',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `downloadId` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'MoviePilot下载任务ID',
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB AUTO_INCREMENT=654 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='求片记录表';

-- ----------------------------
-- Records of rc_media_seek
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_media_seek_log
-- ----------------------------
DROP TABLE IF EXISTS `rc_media_seek_log`;
CREATE TABLE `rc_media_seek_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `seekId` int NOT NULL COMMENT '求片ID',
  `type` tinyint NOT NULL COMMENT '类型:1=创建求片,2=同求,3=状态变更',
  `content` text COMMENT '日志内容',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seekId` (`seekId`)
) ENGINE=InnoDB AUTO_INCREMENT=1159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='求片日志表';

-- ----------------------------
-- Records of rc_media_seek_log
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_media_seek_user
-- ----------------------------
DROP TABLE IF EXISTS `rc_media_seek_user`;
CREATE TABLE `rc_media_seek_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `seekId` int NOT NULL COMMENT '求片ID',
  `userId` int NOT NULL COMMENT '同求用户ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seekId_userId` (`seekId`,`userId`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='求片同求用户表';

-- ----------------------------
-- Records of rc_media_seek_user
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_memo
-- ----------------------------
DROP TABLE IF EXISTS `rc_memo`;
CREATE TABLE `rc_memo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `userId` int NOT NULL COMMENT '用户id',
  `type` int NOT NULL DEFAULT '1' COMMENT '类型，1公开，0指定好友可见，-1删除',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '内容',
  `memoInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '存储json类型数据',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=171 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_memo
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_memo_comment
-- ----------------------------
DROP TABLE IF EXISTS `rc_memo_comment`;
CREATE TABLE `rc_memo_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `memoId` int NOT NULL,
  `userId` int DEFAULT NULL,
  `userName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `replyTo` int DEFAULT NULL,
  `type` int NOT NULL DEFAULT '1',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `commentInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_memo_comment
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_notification
-- ----------------------------
DROP TABLE IF EXISTS `rc_notification`;
CREATE TABLE `rc_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `type` int NOT NULL DEFAULT '0' COMMENT '0系统通知 1用户消息',
  `readStatus` int NOT NULL DEFAULT '0' COMMENT '0未读 1已读',
  `fromUserId` int NOT NULL DEFAULT '0' COMMENT '0系统 >0用户',
  `toUserId` int NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `notificationInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_notification_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9626 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='通知';

-- ----------------------------
-- Records of rc_notification
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_pay_record
-- ----------------------------
DROP TABLE IF EXISTS `rc_pay_record`;
CREATE TABLE `rc_pay_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `payCompleteKey` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` int NOT NULL DEFAULT '0',
  `userId` int NOT NULL,
  `tradeNo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `money` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `clientip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `payRecordInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pay_record_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=437 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='支付记录';

-- ----------------------------
-- Records of rc_pay_record
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_request
-- ----------------------------
DROP TABLE IF EXISTS `rc_request`;
CREATE TABLE `rc_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `type` int NOT NULL DEFAULT '1' COMMENT '0暂不请求，1请求未回复，2已经回复，-1已关闭',
  `requestUserId` int NOT NULL,
  `replyUserId` int DEFAULT NULL COMMENT '回复的管理员id',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT '对话记录',
  `requestInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_request_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=214 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='emby求片';

-- ----------------------------
-- Records of rc_request
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_telegram_user
-- ----------------------------
DROP TABLE IF EXISTS `rc_telegram_user`;
CREATE TABLE `rc_telegram_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `userId` int NOT NULL,
  `telegramId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` int NOT NULL DEFAULT '1' COMMENT '1正常绑定，2已经解绑',
  `userInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_telegram_user_pk_2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=815 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='电报用户信息';

-- ----------------------------
-- Records of rc_telegram_user
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_update_logs
-- ----------------------------
DROP TABLE IF EXISTS `rc_update_logs`;
CREATE TABLE `rc_update_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `app_name` varchar(50) NOT NULL,
  `update_date` date NOT NULL,
  `version` varchar(20) DEFAULT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_update_logs
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rc_user
-- ----------------------------
DROP TABLE IF EXISTS `rc_user`;
CREATE TABLE `rc_user` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'id',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'createdAt',
  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updatedAt',
  `userName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '用户名（登陆名称）',
  `nickName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `authority` int NOT NULL DEFAULT '1' COMMENT '权限（1:注册用户，之后数字为等级，0为管理员',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `rCoin` double NOT NULL DEFAULT '0' COMMENT '余额',
  `userInfo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1907 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户表';

-- ----------------------------
-- Records of rc_user
-- ----------------------------
BEGIN;
INSERT INTO `rc_user` (`id`, `createdAt`, `updatedAt`, `userName`, `nickName`, `password`, `authority`, `email`, `rCoin`, `userInfo`) VALUES (1, '2024-12-14 08:00:35', '2024-12-14 08:00:35', 'admin', 'admin', '$2y$10$rJff.jXkgLpFBN0qE9B.Uu/gnlH2WsUqblAMJOH4iNg7w7OjKJZG6', 0, 'randall@randallanjie.com', 0, NULL);
COMMIT;

-- ----------------------------
-- Table structure for rc_version_updates
-- ----------------------------
DROP TABLE IF EXISTS `rc_version_updates`;
CREATE TABLE `rc_version_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `app_name` varchar(50) NOT NULL DEFAULT 'ALL' COMMENT '应用名称,ALL表示全部应用',
  `version` varchar(20) DEFAULT NULL COMMENT '版本号',
  `description` text COMMENT '更新说明',
  `download_url` varchar(255) DEFAULT NULL COMMENT '下载地址',
  `is_release` tinyint(1) DEFAULT '0' COMMENT '是否发布:0=未发布,1=已发布',
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_version` (`app_name`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of rc_version_updates
-- ----------------------------
BEGIN;
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
