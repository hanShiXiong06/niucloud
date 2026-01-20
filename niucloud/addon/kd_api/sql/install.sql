-- ----------------------------
-- Table structure for {{prefix}}kdapi_api
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}kdapi_api`;
CREATE TABLE `{{prefix}}kdapi_api`
(
    `id`           int(13) NOT NULL AUTO_INCREMENT,
    `site_id`      int(13) NULL DEFAULT NULL COMMENT '站点id',
    `member_id`    int(13) NULL DEFAULT NULL COMMENT '关联会员',
    `rate`         decimal(10, 2) NULL DEFAULT NULL COMMENT '比例',
    `api_key`      varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'apikey',
    `api_secret`   varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '密钥',
    `status`       int(3) NULL DEFAULT NULL COMMENT '状态',
    `qps`          int(13) NULL DEFAULT NULL COMMENT 'qps',
    `limit`        int(13) NULL DEFAULT NULL COMMENT '每天限制次数',
    `num`          int(13) NULL DEFAULT NULL COMMENT '已访问次数',
    `commission`   decimal(10, 2) NULL DEFAULT NULL COMMENT '佣金',
    `callback_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '回调地址',
    `create_time`  int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX          `id`(`id`, `site_id`, `member_id`, `api_key`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'api对接表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}kdapi_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}kdapi_order`;
CREATE TABLE `{{prefix}}kdapi_order`
(
    `id`          int(13) NOT NULL AUTO_INCREMENT,
    `site_id`     int(13) NULL DEFAULT NULL COMMENT '站点id',
    `member_id`   int(13) NULL DEFAULT NULL COMMENT '关联会员',
    `order_id`    varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单ID',
    `title`       longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '订单详情',
    `order_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '订单金额',
    `pay_money`   decimal(10, 2) NULL DEFAULT NULL COMMENT '实际支付',
    `commission`  decimal(10, 2) NULL DEFAULT NULL COMMENT '订单佣金',
    `status`      int(11) NULL DEFAULT NULL COMMENT '订单状态',
    `is_js`       int(11) NULL DEFAULT 0 COMMENT '是否结算',
    `sid`         varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '跟单参数',
    `pub_id`      varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '推广ID',
    `create_time` int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX         `id`(`id`, `order_id`, `status`, `is_js`, `sid`, `pub_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单列表' ROW_FORMAT = Dynamic;