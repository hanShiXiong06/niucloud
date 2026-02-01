-- ----------------------------
-- Table structure for {{prefix}}fastpay_business
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}fastpay_business`;
CREATE TABLE `{{prefix}}fastpay_business`
(
    `id`             int(10) UNSIGNED                                              NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`        int(11)                                                       NULL DEFAULT NULL COMMENT '站点id',
    `member_id`      int(11)                                                       NULL DEFAULT NULL COMMENT '会员id',
    `admin_id`       int(11)                                                       NULL DEFAULT NULL COMMENT '员工管理员',
    `mch_id`         int(11)                                                       NULL DEFAULT NULL COMMENT '商户号',
    `name`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家名称',
    `banner`         varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家轮播展示',
    `qrcode`         varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家微信',
    `friend_gift`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '加好友奖品',
    `share_gift`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '转发奖品',
    `desc`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家描述',
    `address`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家地址',
    `content`        longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci     NULL COMMENT '商家详情',
    `lat`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '经度',
    `lng`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '维度',
    `type`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '跳转类型',
    `finderUserName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '视频号主页ID',
    `feedId`         varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '视频ID',
    `page`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '三方链接',
    `mini_appid`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '小程序APPid',
    `mini_page`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '小程序页面',
    `active_num`     int(11)                                                       NULL DEFAULT NULL COMMENT '活动数量',
    `status`         varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家状态',
    `over_time`      datetime                                                      NULL DEFAULT NULL COMMENT '到期时间',
    `create_time`    int(13)                                                       NULL DEFAULT NULL COMMENT '创建时间',
    `update_time`    int(13)                                                       NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time`    int(13)                                                       NULL DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX `id` (`id`, `site_id`, `member_id`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 426
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT = '商户列表'
  ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}fastpay_business_active
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}fastpay_business_active`;
CREATE TABLE `{{prefix}}fastpay_business_active`
(
    `id`          int(10) UNSIGNED                                              NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`     int(11)                                                       NULL DEFAULT NULL COMMENT '站点id',
    `business_id` int(11)                                                       NULL DEFAULT NULL COMMENT '商户ID',
    `name`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '活动名称',
    `desc`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '活动描述',
    `gift`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '活动奖品',
    `image`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '活动主题',
    `contect`     longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci     NULL COMMENT '详情',
    `create_time` int(13)                                                       NULL DEFAULT NULL COMMENT '创建时间',
    `update_time` int(13)                                                       NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time` int(13)                                                       NULL DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX `id` (`id`, `site_id`, `business_id`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT = '商户活动'
  ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}fastpay_business_member
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}fastpay_business_member`;
CREATE TABLE `{{prefix}}fastpay_business_member`
(
    `id`          int(10) UNSIGNED                                              NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`     int(11)                                                       NULL DEFAULT NULL COMMENT '站点id',
    `business_id` int(11)                                                       NULL DEFAULT NULL COMMENT '商户ID',
    `member_id`   varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '活动名称',
    `level`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '等级',
    `balance`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '余额',
    `create_time` int(13)                                                       NULL DEFAULT NULL COMMENT '创建时间',
    `update_time` int(13)                                                       NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time` int(13)                                                       NULL DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX `id` (`id`, `site_id`, `business_id`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 1
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT = '商户会员'
  ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}fastpay_business_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}fastpay_business_order`;
CREATE TABLE `{{prefix}}fastpay_business_order`
(
    `id`                   int(10) UNSIGNED                                              NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`              int(11)                                                       NOT NULL COMMENT '站点id',
    `member_id`            int(11)                                                       NOT NULL COMMENT '会员id',
    `business_id`          int(11)                                                       NULL     DEFAULT NULL COMMENT '关联商户',
    `order_from`           varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NOT NULL COMMENT '订单来源',
    `order_id`             varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NOT NULL COMMENT '订单id',
    `order_money`          decimal(10, 2)                                                NOT NULL COMMENT '订单金额',
    `order_discount_money` decimal(10, 2)                                                NOT NULL DEFAULT 0.00 COMMENT '订单优惠金额',
    `order_status`         int(1)                                                        NOT NULL DEFAULT 0 COMMENT '订单状态',
    `refund_status`        int(3)                                                        NULL     DEFAULT 0 COMMENT '退款状态',
    `out_trade_no`         varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NULL     DEFAULT NULL COMMENT '支付编号',
    `remark`               varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL     DEFAULT NULL COMMENT '订单备注',
    `pay_time`             int(13)                                                       NULL     DEFAULT NULL COMMENT '支付时间',
    `create_time`          int(11)                                                       NULL     DEFAULT NULL COMMENT '创建时间',
    `close_reason`         varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NULL     DEFAULT NULL COMMENT '关闭原因',
    `is_enable_refund`     varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci   NULL     DEFAULT NULL COMMENT '是否允许退款',
    `close_time`           int(11)                                                       NULL     DEFAULT NULL COMMENT '订单关闭时间',
    `ip`                   varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NULL     DEFAULT NULL COMMENT '会员ip',
    `update_time`          int(13)                                                       NULL     DEFAULT NULL COMMENT '更新时间',
    `delete_time`          int(13)                                                       NULL     DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX `id` (`id`, `site_id`, `member_id`, `order_from`, `order_id`, `order_status`, `refund_status`,
                `create_time`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 43
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT = '商户订单列表'
  ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}fastpay_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}fastpay_order`;
CREATE TABLE `{{prefix}}fastpay_order`
(
    `id`                   int(10) UNSIGNED                                              NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`              int(11)                                                       NOT NULL COMMENT '站点id',
    `member_id`            int(11)                                                       NOT NULL COMMENT '会员id',
    `order_from`           varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NOT NULL COMMENT '订单来源',
    `order_id`             varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NOT NULL COMMENT '订单id',
    `order_money`          decimal(10, 2)                                                NOT NULL COMMENT '订单金额',
    `order_discount_money` decimal(10, 2)                                                NOT NULL DEFAULT 0.00 COMMENT '订单优惠金额',
    `order_status`         int(1)                                                        NOT NULL DEFAULT 0 COMMENT '订单状态',
    `refund_status`        int(3)                                                        NULL     DEFAULT 0 COMMENT '退款状态',
    `out_trade_no`         varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NULL     DEFAULT NULL COMMENT '支付编号',
    `remark`               varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL     DEFAULT NULL COMMENT '订单备注',
    `pay_time`             int(13)                                                       NULL     DEFAULT NULL COMMENT '支付时间',
    `create_time`          int(11)                                                       NULL     DEFAULT NULL COMMENT '创建时间',
    `close_reason`         varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NULL     DEFAULT NULL COMMENT '关闭原因',
    `is_enable_refund`     varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci   NULL     DEFAULT NULL COMMENT '是否允许退款',
    `close_time`           int(11)                                                       NULL     DEFAULT NULL COMMENT '订单关闭时间',
    `ip`                   varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NULL     DEFAULT NULL COMMENT '会员ip',
    `update_time`          int(13)                                                       NULL     DEFAULT NULL COMMENT '更新时间',
    `delete_time`          int(13)                                                       NULL     DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX `id` (`id`, `site_id`, `member_id`, `order_from`, `order_id`, `order_status`, `refund_status`,
                `create_time`) USING BTREE
) ENGINE = InnoDB
  AUTO_INCREMENT = 426
  CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT = '快速支付订单表'
  ROW_FORMAT = DYNAMIC;

