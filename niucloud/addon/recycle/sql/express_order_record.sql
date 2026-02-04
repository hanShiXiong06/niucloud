-- 快递订单记录表
CREATE TABLE IF NOT EXISTS `{{prefix}}express_order_record` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `order_no` varchar(100) NOT NULL DEFAULT '' COMMENT '快递订单号（易速返回）',
    `recycle_order_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联回收订单ID',
    `recycle_device_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联设备ID',

    -- 快递服务商信息
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称：yisu, anguo等',
    `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '产品代码',
    `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
    `delivery_id` varchar(100) NOT NULL DEFAULT '' COMMENT '运单号',

    -- 发件人信息
    `sender_name` varchar(100) NOT NULL DEFAULT '' COMMENT '发件人姓名',
    `sender_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '发件人手机',
    `sender_province` varchar(50) NOT NULL DEFAULT '' COMMENT '发件省份',
    `sender_city` varchar(50) NOT NULL DEFAULT '' COMMENT '发件城市',
    `sender_district` varchar(50) NOT NULL DEFAULT '' COMMENT '发件区县',
    `sender_address` varchar(255) NOT NULL DEFAULT '' COMMENT '发件详细地址',

    -- 收件人信息
    `receiver_name` varchar(100) NOT NULL DEFAULT '' COMMENT '收件人姓名',
    `receiver_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '收件人手机',
    `receiver_province` varchar(50) NOT NULL DEFAULT '' COMMENT '收件省份',
    `receiver_city` varchar(50) NOT NULL DEFAULT '' COMMENT '收件城市',
    `receiver_district` varchar(50) NOT NULL DEFAULT '' COMMENT '收件区县',
    `receiver_address` varchar(255) NOT NULL DEFAULT '' COMMENT '收件详细地址',

    -- 物品信息
    `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '物品名称',
    `goods_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '物品价值（保价金额）',
    `package_count` int(11) NOT NULL DEFAULT 1 COMMENT '包裹数量',

    -- 重量和体积信息
    `estimated_weight` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '预估重量（kg）',
    `actual_weight` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际重量（kg）',
    `weight_diff` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '重量差异（实际-预估）',
    `volume` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '体积（立方米）',
    `volume_long` int(11) NOT NULL DEFAULT 0 COMMENT '长度（cm）',
    `volume_width` int(11) NOT NULL DEFAULT 0 COMMENT '宽度（cm）',
    `volume_height` int(11) NOT NULL DEFAULT 0 COMMENT '高度（cm）',

    -- 费用信息
    `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '预估费用（元）',
    `actual_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际费用（元）',
    `cost_diff` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '费用差异（实际-预估）',
    `payment_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态：0-未支付 1-已支付 2-部分支付',
    `user_paid` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '用户已支付金额',
    `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',

    -- 订单状态
    `order_status` varchar(50) NOT NULL DEFAULT 'pending' COMMENT '订单状态：pending-待揽收, picked-已揽收, in_transit-运输中, delivered-已签收, cancelled-已取消',
    `cancel_reason` varchar(255) DEFAULT '' COMMENT '取消原因',
    `status_history` json COMMENT '状态变更历史（JSON格式）',

    -- 时间信息
    `pickup_time` int(11) DEFAULT NULL COMMENT '揽收时间',
    `delivery_time` int(11) DEFAULT NULL COMMENT '签收时间',
    `cancel_time` int(11) DEFAULT NULL COMMENT '取消时间',
    `create_at` int(11) DEFAULT NULL COMMENT '创建时间',
    `update_at` int(11) DEFAULT NULL COMMENT '更新时间',

    -- 其他信息
    `remark` varchar(500) DEFAULT '' COMMENT '备注',
    `api_response` json COMMENT 'API响应数据（JSON格式）',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_site_id` (`site_id`),
    KEY `idx_recycle_order` (`recycle_order_id`),
    KEY `idx_recycle_device` (`recycle_device_id`),
    KEY `idx_delivery_id` (`delivery_id`),
    KEY `idx_order_status` (`order_status`),
    KEY `idx_create_at` (`create_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递订单记录表';
