ALTER TABLE `xiaoyuan_runner` ADD COLUMN `weapp_subscribe_num` int(11) NOT NULL DEFAULT 0 COMMENT '小程序订单订阅剩余次数' AFTER `accept_types`;
