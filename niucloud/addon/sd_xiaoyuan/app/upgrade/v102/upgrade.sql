SET FOREIGN_KEY_CHECKS = 0;

-- 创建社区点赞记录表
DROP TABLE IF EXISTS `xiaoyuan_community_like`;
CREATE TABLE `xiaoyuan_community_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '点赞用户ID',
  `post_id` int(11) NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '点赞时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_like` (`site_id`, `member_id`, `post_id`) USING BTREE,
  KEY `idx_post_id` (`post_id`) USING BTREE,
  KEY `idx_member_id` (`member_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='社区帖子点赞记录表';

-- 创建表白墙点赞记录表
DROP TABLE IF EXISTS `xiaoyuan_confession_like`;
CREATE TABLE `xiaoyuan_confession_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '点赞用户ID',
  `confession_id` int(11) NOT NULL DEFAULT 0 COMMENT '表白ID',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '点赞时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_like` (`site_id`, `member_id`, `confession_id`) USING BTREE,
  KEY `idx_confession_id` (`confession_id`) USING BTREE,
  KEY `idx_member_id` (`member_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='表白墙点赞记录表';

SET FOREIGN_KEY_CHECKS = 1;