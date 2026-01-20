DROP TABLE IF EXISTS `tkjhkd_coupon`;
CREATE TABLE `tkjhkd_coupon`
(
    `id`                  int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id`             int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `title`               varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
    `start_time`          int(11) NOT NULL DEFAULT '0' COMMENT '活动开启时间',
    `end_time`            int(11) NOT NULL DEFAULT '0' COMMENT '活动结束时间',
    `remain_count`        int(11) NOT NULL DEFAULT '0' COMMENT '剩余数量',
    `receive_count`       int(11) NOT NULL DEFAULT '0' COMMENT '已领取数量',
    `limit_count`         int(11) NOT NULL DEFAULT '0' COMMENT '单个会员限制领取数量',
    `status`              tinyint(4) NOT NULL DEFAULT '1' COMMENT ' 状态 1 正常 2 未开启 3 已无效',
    `create_time`         int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
    `price`               decimal(10, 2) unsigned NOT NULL DEFAULT '0.00' COMMENT '面值',
    `min_condition_money` decimal(10, 2) unsigned NOT NULL DEFAULT '0.00' COMMENT '商品最低多少金额可用优惠券',
    `type`                tinyint(4) NOT NULL DEFAULT '0' COMMENT '优惠券类型 1通用优惠券 2商品品类优惠券 3商品优惠券',
    `receive_type`        int(11) NOT NULL DEFAULT '0' COMMENT '领取方式',
    `valid_type`          int(11) unsigned NOT NULL DEFAULT '0' COMMENT '有效时间',
    `length`              int(11) NOT NULL DEFAULT '0' COMMENT '有效期时长(天)',
    `valid_start_time`    int(11) NOT NULL DEFAULT '0' COMMENT '有效期开始时间',
    `valid_end_time`      int(11) NOT NULL DEFAULT '0' COMMENT '有效期结束时间',
    `sort`                int(11) NOT NULL DEFAULT '0' COMMENT '排序',
    `receive_status`      tinyint(4) NOT NULL DEFAULT '1' COMMENT ' 状态 1 正常 2 关闭',
    PRIMARY KEY (`id`),
    KEY                   `status` (`status`),
    KEY                   `title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表';
DROP TABLE IF EXISTS `tkjhkd_coupon_member`;
CREATE TABLE `tkjhkd_coupon_member`
(
    `id`                  int(11) NOT NULL AUTO_INCREMENT COMMENT '优惠券发放记录id',
    `site_id`             int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `coupon_id`           int(11) NOT NULL DEFAULT '0' COMMENT '优惠券id',
    `member_id`           int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
    `create_time`         int(11) unsigned NOT NULL DEFAULT '0' COMMENT '领取时间',
    `expire_time`         int(11) unsigned NOT NULL DEFAULT '0' COMMENT '过期时间',
    `use_time`            int(11) unsigned NOT NULL DEFAULT '0' COMMENT '使用时间',
    `type`                varchar(32)    NOT NULL DEFAULT '' COMMENT '优惠券类型',
    `status`              tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态',
    `title`               varchar(255)   NOT NULL DEFAULT '' COMMENT '优惠券名称',
    `price`               decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '面值',
    `min_condition_money` decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '最低使用门槛',
    `receive_type`        varchar(255)   NOT NULL DEFAULT '' COMMENT '领取方式',
    `trade_id`            int(11) NOT NULL DEFAULT '0' COMMENT '关联业务id',
    PRIMARY KEY (`id`),
    KEY                   `coupon_id` (`coupon_id`),
    KEY                   `member_id` (`member_id`),
    KEY                   `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券会员领取记录表';

ALTER TABLE tkjhkd_order
    ADD COLUMN pay_money DECIMAL(10, 2) NOT NULL DEFAULT '0.00' COMMENT '支付金额' AFTER order_discount_money,
    ADD COLUMN coupon_value LONGTEXT COMMENT '优惠券信息';