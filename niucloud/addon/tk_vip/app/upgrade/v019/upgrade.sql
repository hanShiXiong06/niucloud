-- ----------------------------
-- Table structure for tkvip_fenxiao_member
-- ----------------------------
DROP TABLE IF EXISTS `tkvip_fenxiao_member`;
CREATE TABLE `tkvip_fenxiao_member`
(
    `member_id`   int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`     int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `pid`         int(11) NULL DEFAULT 0 COMMENT '推荐会员id(分销)',
    `bind_time`   int(11) NULL DEFAULT 0 COMMENT '绑定时间',
    `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`member_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '分销会员' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for tkvip_fenxiao_order
-- ----------------------------
DROP TABLE IF EXISTS `tkvip_fenxiao_order`;
CREATE TABLE `tkvip_fenxiao_order`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `member_id`        int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`          int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`         varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '会员id',
    `type`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '类型',
    `state`            int(11) NULL DEFAULT NULL COMMENT '订单状态',
    `first_commission` decimal(10, 2) NULL DEFAULT NULL COMMENT '一级佣金',
    `two_commission`   decimal(10, 2) NULL DEFAULT NULL COMMENT '二级佣金',
    `first_point`      int(13) NULL DEFAULT NULL COMMENT '一级积分',
    `two_point`        int(13) NULL DEFAULT NULL COMMENT '二级积分',
    `self_commission`  decimal(10, 2) NULL DEFAULT NULL COMMENT '自购佣金',
    `self_point`       int(13) NULL DEFAULT NULL COMMENT '自购积分',
    `status`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '状态',
    `create_time`      int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`      int(11) NULL DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '分销订单' ROW_FORMAT = DYNAMIC;