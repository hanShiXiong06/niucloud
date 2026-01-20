CREATE TABLE `tkjhkd_shop_order`
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
