CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_ai_conversation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `uid` int NOT NULL DEFAULT 0 COMMENT '操作人(管理员)uid',
  `scene` varchar(32) NOT NULL DEFAULT 'general' COMMENT '场景 key',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '对话标题(取首条提问)',
  `messages` json DEFAULT NULL COMMENT '消息体: [{role,content,references}]',
  `tokens` int NOT NULL DEFAULT 0 COMMENT '累计 token',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site_uid` (`site_id`,`uid`,`update_time`),
  KEY `idx_site_scene` (`site_id`,`scene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI对话记录';
