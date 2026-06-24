CREATE TABLE IF NOT EXISTS `xiaoyuan_service_card` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `card_type` varchar(20) NOT NULL DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  `subtitle` varchar(200) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `origin_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `times` int(11) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  `create_time` int(11) DEFAULT 0,
  `update_time` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_card_type` (`site_id`,`card_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='次卡配置';

CREATE TABLE IF NOT EXISTS `xiaoyuan_member_card` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `member_id` int(11) NOT NULL DEFAULT 0,
  `card_type` varchar(20) NOT NULL DEFAULT '',
  `total_times` int(11) NOT NULL DEFAULT 0,
  `used_times` int(11) NOT NULL DEFAULT 0,
  `remain_times` int(11) NOT NULL DEFAULT 0,
  `create_time` int(11) DEFAULT 0,
  `update_time` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_member_card` (`site_id`,`member_id`,`card_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员次卡';

CREATE TABLE IF NOT EXISTS `xiaoyuan_card_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `member_id` int(11) NOT NULL DEFAULT 0,
  `card_type` varchar(20) NOT NULL DEFAULT '',
  `card_id` int(11) NOT NULL DEFAULT 0,
  `runner_id` int(11) NOT NULL DEFAULT 0,
  `order_no` varchar(32) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `times` int(11) NOT NULL DEFAULT 0,
  `pay_status` tinyint(1) NOT NULL DEFAULT 0,
  `pay_time` int(11) DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `create_time` int(11) DEFAULT 0,
  `update_time` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_runner` (`runner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='次卡订单';
