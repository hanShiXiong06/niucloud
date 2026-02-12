-- 快递服务商配置表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_express_provider_config` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `provider` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商标识: yisu|anguo',
    `provider_name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商名称',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用状态: 0禁用 1启用',
    `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认: 0否 1是',
    `config` json DEFAULT NULL COMMENT '扩展配置(JSON)',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序(越大越前)',
    `create_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_site_provider` (`site_id`, `provider`),
    KEY `idx_site_status` (`site_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递服务商配置表';

ALTER TABLE `saas_recycle_order`
ADD COLUMN `delivery_platform` varchar(50) NOT NULL DEFAULT '' COMMENT '快递平台：anguo-安果ERP，manual-手动录入' AFTER `express_no`,
ADD COLUMN `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '快递费用' AFTER `delivery_platform`,
ADD COLUMN `delivery_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '快递状态：0-未下单，1-已下单，2-运输中，3-已签收，4-已取消' AFTER `delivery_fee`,
ADD COLUMN `delivery_order_id` varchar(100) NOT NULL DEFAULT '' COMMENT '快递平台订单ID（用于拦截等操作）' AFTER `delivery_status`,
ADD COLUMN `delivery_data` json COMMENT '快递下单原始数据（发件人、收件人、预约时间等）' AFTER `delivery_order_id`;
ALTER TABLE `saas_recycle_order` ADD COLUMN `delivery_operator_uid` int(11) NOT NULL DEFAULT 0 COMMENT '快递操作人UID' ;
ALTER TABLE `saas_recycle_order` ADD COLUMN  `delivery_operator_name` varchar(100) NOT NULL DEFAULT '' COMMENT '快递操作人姓名' ;
