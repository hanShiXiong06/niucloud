-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_brand
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_brand`;
CREATE TABLE `{{prefix}}tkjhkd_brand`
(
    `id`            int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`       int(11) NOT NULL COMMENT '站点id',
    `delivery_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '类型',
    `name`          varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '名称',
    `logo`          varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'logo',
    `status`        tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否启用 1启用 0不启用',
    `create_time`   int(11) NULL DEFAULT NULL COMMENT '创建时间',
    `update_time`   int(11) NULL DEFAULT NULL COMMENT '修改时间',
    `addon`         varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '所属插件',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 27 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '代码生成表字段信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_help
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_help`;
CREATE TABLE `{{prefix}}tkjhkd_help`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `site_id`     int(11) NULL DEFAULT NULL COMMENT '站点id',
    `title`       varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '问题名称',
    `content`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '解决方案',
    `create_time` datetime NULL DEFAULT NULL,
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 59 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_notice
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_notice`;
CREATE TABLE `{{prefix}}tkjhkd_notice`
(
    `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`     int(11) NOT NULL COMMENT '站点id',
    `title`       varchar(88) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '标题',
    `image`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '封面',
    `content`     longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '内容',
    `status`      int(1) NOT NULL DEFAULT 1 COMMENT '是否启用 1启用 0不启用',
    `create_time` int(11) NOT NULL COMMENT '创建时间',
    `update_time` int(11) NOT NULL COMMENT '修改时间',
    `addon`       varchar(88) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '所属插件',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '代码生成表字段信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_order`;
CREATE TABLE `{{prefix}}tkjhkd_order`
(
    `id`                   int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`              int(11) NOT NULL COMMENT '站点id',
    `member_id`            int(11) NOT NULL COMMENT '会员id',
    `order_from`           varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '订单来源',
    `order_id`             varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '订单id',
    `order_money`          decimal(10, 2)                                               NOT NULL COMMENT '订单金额',
    `order_discount_money` decimal(10, 2)                                               NOT NULL DEFAULT 0.00 COMMENT '订单优惠金额',
    `pay_money`            decimal(10, 2)                                                        DEFAULT NULL COMMENT '支付金额',
    `coupon_value`         longtext COMMENT '优惠信息',
    `is_send`              int(1) NULL DEFAULT 0 COMMENT '是否发单1已发单0未发单',
    `is_pick`              int(1) NULL DEFAULT 0 COMMENT '是否揽收 1揽收 0未揽收',
    `order_status`         int(1) NOT NULL DEFAULT 0 COMMENT '订单状态',
    `refund_status`        int(3) NULL DEFAULT 0 COMMENT '退款状态',
    `out_trade_no`         varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '支付编号',
    `mer_remark`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商户备注',
    `remark`               varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单备注',
    `pay_time`             int(13) NULL DEFAULT NULL COMMENT '支付时间',
    `create_time`          int(11) NULL DEFAULT NULL COMMENT '创建时间',
    `close_reason`         varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '关闭原因',
    `is_enable_refund`     varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '是否允许退款',
    `close_time`           int(11) NULL DEFAULT NULL COMMENT '订单关闭时间',
    `ip`                   varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '会员ip',
    `update_time`          int(13) NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time`          int(13) NULL DEFAULT 0 COMMENT '删除时间',
    `send_log`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '发单记录',
    `task_brand`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '发单渠道',
    `task_type`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '发单类型',
    `pub_id`               varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'API推广ID',
    `sid`                  varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'API跟单参数',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX                  `id`(`id`, `site_id`, `member_id`, `order_from`, `order_id`, `is_send`, `order_status`, `refund_status`, `create_time`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 230 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单列表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_shop_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_shop_order`;
CREATE TABLE `{{prefix}}tkjhkd_shop_order`
(
    `id`                int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`           int                                                          NOT NULL COMMENT '站点id',
    `order_no`          varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '商城订单号',
    `delivery_id`       varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '快递单号',
    `yida_order_no`     varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '易达订单号',
    `order_status_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单状态',
    `is_pick`           int NULL DEFAULT NULL COMMENT '是否揽收',
    `is_send`           int NULL DEFAULT 0 COMMENT '是否发单1已发单0未发单',
    `real_price`        decimal(10, 2) NULL DEFAULT NULL COMMENT '实际运费',
    `close_time`        int NULL DEFAULT NULL COMMENT '订单关闭时间',
    `update_time`       int NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time`       int NULL DEFAULT 0 COMMENT '删除时间',
    `create_time`       int NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX               `id`(`id`, `site_id`, `order_no`, `is_send`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 233 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '商城发单' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_order_add
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_order_add`;
CREATE TABLE `{{prefix}}tkjhkd_order_add`
(
    `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
    `site_id`      int(11) NULL DEFAULT NULL COMMENT '站点id',
    `member_id`    int(10) NULL DEFAULT NULL COMMENT '会员id',
    `order_id`     varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '订单id',
    `order_no`     varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单no',
    `order_money`  decimal(10, 2)                                               NOT NULL COMMENT '订单金额',
    `order_status` int(1) NULL DEFAULT 0 COMMENT '订单状态',
    `out_trade_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '支付编号',
    `pay_time`     int(13) NULL DEFAULT NULL COMMENT '支付时间',
    `create_time`  int(11) NULL DEFAULT NULL COMMENT '创建时间',
    `close_time`   int(11) NULL DEFAULT NULL COMMENT '订单关闭时间',
    `ip`           varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '会员ip',
    `update_time`  int(13) NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time`  int(13) NULL DEFAULT 0 COMMENT '删除时间',
    `notice_num`   int(11) DEFAULT NULL COMMENT '通知次数',
    `remark`       varchar(255) DEFAULT NULL COMMENT '备注',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX          `id`(`id`, `order_id`, `order_status`, `create_time`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单列表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_order_delivery
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_order_delivery`;
CREATE TABLE `{{prefix}}tkjhkd_order_delivery`
(
    `order_id`          varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '订单id',
    `task_id`           varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '任务id',
    `member_id`         int(11) NULL DEFAULT NULL COMMENT '会员id',
    `platform`          varchar(255)                                                          DEFAULT NULL COMMENT '平台',
    `order_no`          varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '易达订单号',
    `start_address`     longtext COMMENT '开始地址',
    `end_address`       longtext COMMENT '结束地址',
    `goods`             varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '物品',
    `long` double NOT NULL COMMENT '长',
    `width` double NOT NULL COMMENT '宽',
    `height` double NOT NULL COMMENT '高',
    `weight` double NOT NULL COMMENT '重量',
    `package_count`     int(4) NULL DEFAULT NULL COMMENT '包裹数',
    `delivery_id`       varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '运送单号',
    `delivery_status`   int(60) NULL DEFAULT 1 COMMENT '订单状态',
    `delivery_type`     varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '快递公司',
    `bj_price`          varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '保价金额',
    `customer_type`     varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '寄件通道',
    `delivery_business` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '产品类型',
    `online_pay`        varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '是否在线支付YNALL',
    `start_time`        varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '预约开始时间',
    `end_time`          varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '预约结束时间',
    `order_status_desc` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单状态描述',
    `order_status`      varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '1' COMMENT '订单状态快递信息状态',
    `courier_context`   varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '揽收信息',
    `pay_method`        varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '支付方式',
    `remark`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单备注',
    `create_time`       int(13) NULL DEFAULT NULL COMMENT '创建时间',
    `update_time`       int(13) NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time`       int(13) NULL DEFAULT NULL COMMENT '删除时间',
    `channel_id`        varchar(255) COMMENT '渠道id',
    `price_rule`        longtext COMMENT '计价规则',
    `original_rule`     longtext COMMENT '原价计价规则',
    PRIMARY KEY (`order_id`(15)) USING BTREE,
    INDEX               `order_id`(`order_id`, `member_id`, `order_no`, `delivery_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单快递信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_order_delivery_real
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_order_delivery_real`;
CREATE TABLE `{{prefix}}tkjhkd_order_delivery_real`
(
    `order_id`      varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `weight` double NULL DEFAULT NULL COMMENT '重量',
    `fee_weight` double NULL DEFAULT NULL COMMENT '计费重量',
    `volume` double NULL DEFAULT NULL COMMENT '体积',
    `package_count` int(3) NULL DEFAULT NULL COMMENT '包裹数量',
    `fee_blockList` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '费用明细',
    `total_fee` double NULL DEFAULT NULL COMMENT '总费用',
    `pay_fee` double NULL DEFAULT NULL COMMENT '支付费用',
    `create_time`   int(13) NULL DEFAULT NULL COMMENT '创建时间',
    `update_time`   int(13) NULL DEFAULT NULL COMMENT '更新时间',
    `delete_time`   int(13) NULL DEFAULT NULL COMMENT '删除时间',
    PRIMARY KEY (`order_id`) USING BTREE,
    INDEX           `order_id`(`order_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单快递信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_order_discount
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_order_discount`;
CREATE TABLE `{{prefix}}tkjhkd_order_discount`
(
    `order_id`      int(11) NOT NULL,
    `discount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '优惠类型',
    `money` double NULL DEFAULT NULL COMMENT '优惠金额',
    `coupon_id`     int(11) NULL DEFAULT NULL COMMENT '优惠券id',
    `card_id`       int(11) NULL DEFAULT NULL COMMENT '会员卡id',
    PRIMARY KEY (`order_id`) USING BTREE,
    INDEX           `order_id`(`order_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_order_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_order_log`;
CREATE TABLE `{{prefix}}tkjhkd_order_log`
(
    `id`           int(13) NOT NULL AUTO_INCREMENT,
    `site_id`      int(13) NULL DEFAULT NULL,
    `order_id`     varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci  NOT NULL COMMENT '订单id',
    `action`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '操作',
    `main_type`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '操作身份',
    `main_id`      int(11) NULL DEFAULT NULL COMMENT '身份id',
    `nick_name`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '昵称',
    `order_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单状态',
    `create_time`  int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX          `id`(`id`, `order_id`, `main_type`, `order_status`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_fenxiao_member
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_fenxiao_member`;
CREATE TABLE `{{prefix}}tkjhkd_fenxiao_member`
(
    `member_id`         int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`           int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `pid`               int(11) NULL DEFAULT 0 COMMENT '推荐会员id(分销)',
    `fenxiao_member_id` int(11) NULL DEFAULT 0 COMMENT '会员上级分销商会员id',
    `is_fenxiao`        tinyint(4) NULL DEFAULT 0 COMMENT '是否是分销商',
    `bind_time`         int(11) NULL DEFAULT 0 COMMENT '绑定时间',
    `create_time`       int(11) NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`member_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}tkjhkd_fenxiao_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_fenxiao_order`;
CREATE TABLE `{{prefix}}tkjhkd_fenxiao_order`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `member_id`        int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`          int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`         varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '会员id',
    `type`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '类型',
    `state`            int(11) NULL DEFAULT NULL COMMENT '订单状态',
    `first_commission` decimal(10, 2) NULL DEFAULT NULL COMMENT '一级佣金',
    `two_commission`   decimal(10, 2) NULL DEFAULT NULL COMMENT '二级佣金',
    `status`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '状态',
    `create_time`      int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`      int(11) NULL DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_coupon`;
CREATE TABLE `{{prefix}}tkjhkd_coupon`
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
DROP TABLE IF EXISTS `{{prefix}}tkjhkd_coupon_member`;
CREATE TABLE `{{prefix}}tkjhkd_coupon_member`
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
-- ----------------------------
-- Records of {{prefix}}tkjhkd_brand
-- ----------------------------
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'JD', '京东', 'addon/tk_jhkd/logo/jd.png', 1, 1690035304, 1690035304, '8');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'YTO', '圆通', 'addon/tk_jhkd/logo/yt.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'STO-INT', '申通', 'addon/tk_jhkd/logo/st.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'DOP', '德邦', 'addon/tk_jhkd/logo/db.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'SF', '顺丰', 'addon/tk_jhkd/logo/sf.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'JT', '极兔', 'addon/tk_jhkd/logo/jt.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'ZTO', '中通', 'addon/tk_jhkd/logo/zt.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'YUND', '韵达', 'addon/tk_jhkd/logo/yd.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'CNSD', '菜鸟速递', 'addon/tk_jhkd/logo/cn.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'CAINIAO', '菜鸟裹裹', 'addon/tk_jhkd/logo/cnsd.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'EMS', 'EMS', 'addon/tk_jhkd/logo/ems.png', 1, 0, 0, '');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'KY', '跨越速运', 'addon/tk_jhkd/logo/ky.png', 1, 0, 0, '0');
INSERT INTO `{{prefix}}tkjhkd_brand`
VALUES ('', 0, 'BEST', '百事速运', 'addon/tk_jhkd/logo/bs.png', 1, NULL, NULL, NULL);

-- ----------------------------
-- Records of {{prefix}}sys_dict
-- ----------------------------
DELETE
FROM `{{prefix}}sys_dict`
WHERE `key` = 'jhkd_is_send';
INSERT INTO `{{prefix}}sys_dict`
VALUES ('', '是否发单', 'jhkd_is_send',
        '\"[{\\\"name\\\":\\\"\\u5df2\\u53d1\\u5355\\\",\\\"value\\\":\\\"1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u672a\\u53d1\\u5355\\\",\\\"value\\\":\\\"0\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"}]\"',
        '{{prefix}}jhkd  addon', 1703694249, 1703695142);
DELETE
FROM `{{prefix}}sys_dict`
WHERE `key` = 'jhkd_is_pick';
INSERT INTO `{{prefix}}sys_dict`
VALUES ('', '是否揽收', 'jhkd_is_pick',
        '\"[{\\\"name\\\":\\\"\\u672a\\u63fd\\u6536\\\",\\\"value\\\":\\\"0\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u5df2\\u63fd\\u6536\\\",\\\"value\\\":\\\"1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"}]\"',
        '{{prefix}}jhkd  addon', 1703694969, 1703695029);
DELETE
FROM `{{prefix}}sys_dict`
WHERE `key` = 'jhkd_order_status';
INSERT INTO `{{prefix}}sys_dict`
VALUES ('', '订单状态', 'jhkd_order_status',
        '\"[{\\\"name\\\":\\\"\\u5f85\\u652f\\u4ed8\\\",\\\"value\\\":\\\"0\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u5df2\\u5b8c\\u6210\\\",\\\"value\\\":\\\"10\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u5df2\\u5173\\u95ed\\\",\\\"value\\\":\\\"-1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u5df2\\u652f\\u4ed8\\\",\\\"value\\\":\\\"1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u5728\\u9014\\u4e2d\\\",\\\"value\\\":\\\"2\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"}]\"',
        'tk_jhkd addon', 1703695191, 1705993389);
DELETE
FROM `{{prefix}}sys_dict`
WHERE `key` = 'jhkd_refund_status';
INSERT INTO `{{prefix}}sys_dict`
VALUES ('', '退款状态', 'jhkd_refund_status',
        '\"[{\\\"name\\\":\\\"\\u672a\\u7533\\u8bf7\\\",\\\"value\\\":\\\"0\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u9000\\u6b3e\\u4e2d\\\",\\\"value\\\":\\\"1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u5df2\\u5b8c\\u6210\\\",\\\"value\\\":\\\"2\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u9000\\u6b3e\\u5931\\u8d25\\\",\\\"value\\\":\\\"-1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"}]\"',
        '', 1703695463, 1703695537);
DELETE
FROM `{{prefix}}sys_dict`
WHERE `key` = 'jhkd_is_refund';
INSERT INTO `{{prefix}}sys_dict`
VALUES ('', '是否允许退款', 'jhkd_is_refund',
        '\"[{\\\"name\\\":\\\"\\u5141\\u8bb8\\\",\\\"value\\\":\\\"1\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"},{\\\"name\\\":\\\"\\u4e0d\\u5141\\u8bb8\\\",\\\"value\\\":\\\"0\\\",\\\"sort\\\":0,\\\"memo\\\":\\\"\\\"}]\"',
        '', 1703696174, 1703696199);