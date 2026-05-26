-- ----------------------------
-- 1.  回收订单主表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}recycle_order`;
CREATE TABLE `{{prefix}}recycle_order` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `flow_mode` varchar(20) NOT NULL DEFAULT 'order' COMMENT '订单流转模式：order-整单流转，device-按设备流转',
  `order_no` varchar(50)   NOT NULL DEFAULT '' COMMENT '订单编号',
  `customer_name` varchar(50)   NOT NULL DEFAULT '' COMMENT '客户姓名',
  `customer_phone` varchar(20)   NOT NULL DEFAULT '' COMMENT '客户电话',
  `pay_type` varchar(20)   NOT NULL DEFAULT '' COMMENT '打款时间',
  `pay_account` varchar(50)   NOT NULL DEFAULT '' COMMENT '支付账号',
  `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '打款状态：0-未打款，1-已打款',
  `pay_name` varchar(50) NOT NULL DEFAULT '' COMMENT '收款人姓名',
  `pay_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '打款备注',
  `pay_url` varchar(500) NOT NULL DEFAULT '' COMMENT '打款凭证',
  `payment_images` text COMMENT '打款凭证图片',
  `delivery_type` varchar(20)   NOT NULL DEFAULT 'express' COMMENT '发货方式：express-快递，self-自送',
  `express_company` varchar(50)   DEFAULT '' COMMENT '快递公司',
  `express_no` varchar(50)   DEFAULT '' COMMENT '快递单号',
  `delivery_platform` varchar(50) NOT NULL DEFAULT '' COMMENT '快递平台',
  `delivery_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '快递状态：0-未下单，1-已下单，2-运输中，3-已签收，4-已取消',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '快递费用',
  `delivery_order_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方快递订单号',
  `pickup_time` varchar(50) NOT NULL DEFAULT '' COMMENT '预约揽收时间',
  `delivery_data` text COMMENT '快递扩展数据',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单状态：1-待签收，2-已签收，3-质检中，4-已质检，5-已支付，6-已完成，7-已取消',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '订单总金额',
  `device_count` int NOT NULL DEFAULT 0 COMMENT '设备数量',
  `count` int NOT NULL DEFAULT 1 COMMENT '提交设备数量',
  `remark` varchar(255)   DEFAULT '' COMMENT '备注',
  `create_at` int not null DEFAULT 0 COMMENT '创建时间',
  `update_at` int not null DEFAULT 0 COMMENT '更新时间',
  `sign_at` int NOT NULL DEFAULT 0 COMMENT '签收时间',
  `complete_at` int DEFAULT NULL COMMENT '完成时间',
  `confirm_time` int NOT NULL DEFAULT 0 COMMENT '确认价格时间',
  `receipt_confirm_time` int NOT NULL DEFAULT 0 COMMENT '用户确认收货时间',
  `cancel_time` int NOT NULL DEFAULT 0 COMMENT '取消时间',
  `cancel_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '取消原因',
  `close_time` int NOT NULL DEFAULT 0 COMMENT '关闭时间',
  `close_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '关闭原因',
  `negotiate_time` int NOT NULL DEFAULT 0 COMMENT '议价时间',
  `expected_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '用户期望价格',
  `negotiate_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '议价原因',
  `is_negotiating` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否议价中',
  `is_force_confirm` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否强制确认',
  `member_id` int NOT NULL COMMENT '用户 id',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '打款时间',
  `pay_uid` int NOT NULL DEFAULT 0 COMMENT '打款人ID',
  `delete_at` int NOT NULL DEFAULT 0 COMMENT '删除时间',
  PRIMARY KEY (`id`)
)  COMMENT='回收订单主表';




-- ----------------------------
-- 2. 设备表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}recycle_device`;
CREATE TABLE `{{prefix}}recycle_device` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '设备ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `category_id` int NOT NULL DEFAULT '1' COMMENT '设备分类ID',
  `check_template_id` int NOT NULL DEFAULT 0 COMMENT '质检模板ID',
  `imei` varchar(50)   NOT NULL DEFAULT '' COMMENT 'IMEI号码',
  `imei2` varchar(50) NOT NULL DEFAULT '' COMMENT 'IMEI2号码',
  `sn` varchar(100) NOT NULL DEFAULT '' COMMENT '设备SN序列号',
  `system_version` varchar(100) NOT NULL DEFAULT '' COMMENT '系统版本（如iOS 17.3.1）',
  `warranty_info` varchar(100) NOT NULL DEFAULT '' COMMENT '保修信息（保修日期或"过保"）',
  `user_sn` varchar(100) NOT NULL DEFAULT '' COMMENT '用户提交SN码',
  `model` varchar(100)   NOT NULL DEFAULT '' COMMENT '设备型号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '设备状态：1-待质检，2-质检中，3-已质检，4-待确认，5-已回收，6-已退回，7-已定价，8-已定价（重新定价）',
  `check_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '质检状态：0-未质检，1-质检中，2-已质检',
  `check_at` int DEFAULT NULL COMMENT '质检时间',
  `check_result` text   COMMENT '质检结果',
  `check_result_seller` text COMMENT '卖家可见质检结果',
  `check_result_buyer` text COMMENT '买家可见质检结果',
  `check_uid` int NOT NULL DEFAULT 0 COMMENT '质检员ID',
  `check_images` text   COMMENT '质检图片 逗号 , 隔开 ',
  `check_images_seller` text COMMENT '卖家可见质检图片 逗号 , 隔开',
  `check_images_buyer` text COMMENT '买家可见质检图片 逗号 , 隔开',
  `initial_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '预估价格',
  `final_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最终价格',
  `sell_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '卖货价格',
  `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '内存/规格（如256GB）',
  `color` varchar(50) NOT NULL DEFAULT '' COMMENT '颜色（如深空黑色）',
  `price_at` int DEFAULT 0 COMMENT '定价时间',
  `final_price_at` int DEFAULT 0 COMMENT '最终价格时间',
  `price_uid` int NOT NULL DEFAULT 0 COMMENT '价格确认人ID',
  `final_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '最终状态：0 1-已确认 0-未确认',
  `confirm_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '报价确认状态：0-待确认，1-已确认，2-已拒绝',
  `confirm_time` int NOT NULL DEFAULT 0 COMMENT '报价确认时间',
  `confirm_member_id` int NOT NULL DEFAULT 0 COMMENT '报价确认会员ID',
  `confirm_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '报价确认备注',
  `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '设备打款状态：0-未打款，1-已打款',
  `pay_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '设备实付金额',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '设备打款时间',
  `pay_uid` int NOT NULL DEFAULT 0 COMMENT '设备打款操作人ID',
  `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '最近一次设备打款批次号',
  `settlement_mode` varchar(20) NOT NULL DEFAULT 'recycle' COMMENT '结算模式：recycle-普通回收，consign-代卖',
  `dispose_type` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '处置类型：pending-未处置，recycle-普通回收，return-退回，consign-代卖',
  `dispose_status` tinyint NOT NULL DEFAULT 0 COMMENT '处置状态：0-未处置，1-已回收，2-已退回，3-已转代卖',
  `consignment_order_id` int NOT NULL DEFAULT 0 COMMENT '关联代卖订单ID',
  `price_remark` varchar(255)   DEFAULT '' COMMENT '价格备注',
  `remark` varchar(255)   DEFAULT '' COMMENT '备注',
  `create_at` int not null DEFAULT 0 COMMENT '创建时间',
  `update_at` int not null DEFAULT 0 COMMENT '更新时间',
  `export_time` int NOT NULL DEFAULT 0 COMMENT '导出时间',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `before_price` varchar(255)   DEFAULT '' COMMENT '之前定价',
  `info` json,
  PRIMARY KEY (`id`)
)  COMMENT='设备表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_consignment_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '代卖订单ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `consignment_no` varchar(64) NOT NULL DEFAULT '' COMMENT '代卖单号',
  `source_order_id` int NOT NULL DEFAULT 0 COMMENT '来源回收订单ID',
  `source_order_no` varchar(64) NOT NULL DEFAULT '' COMMENT '来源回收订单号',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '来源设备ID',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `customer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '客户姓名',
  `customer_phone` varchar(30) NOT NULL DEFAULT '' COMMENT '客户手机号',
  `device_imei` varchar(50) NOT NULL DEFAULT '' COMMENT '设备IMEI',
  `device_model` varchar(100) NOT NULL DEFAULT '' COMMENT '设备型号',
  `quote_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '原回收报价',
  `expected_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户期望价',
  `min_settlement_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户最低结算价',
  `listing_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '挂牌价',
  `sold_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '实际成交价',
  `settlement_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '客户结算金额',
  `service_fee` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '服务收益',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '代卖状态：0待上架 1代卖中 2已售出 3待结算 4已结算 5已取消 6已退回',
  `pay_status` tinyint NOT NULL DEFAULT 0 COMMENT '结算状态：0未结算 1已结算',
  `listed_time` int NOT NULL DEFAULT 0 COMMENT '上架时间',
  `sold_time` int NOT NULL DEFAULT 0 COMMENT '售出时间',
  `settle_time` int NOT NULL DEFAULT 0 COMMENT '结算时间',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '打款时间',
  `pay_uid` int NOT NULL DEFAULT 0 COMMENT '打款操作人',
  `cancel_time` int NOT NULL DEFAULT 0 COMMENT '取消时间',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '最近操作人',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_no` (`site_id`,`consignment_no`),
  KEY `idx_site_order` (`site_id`,`source_order_id`),
  KEY `idx_site_device` (`site_id`,`source_device_id`),
  KEY `idx_site_status` (`site_id`,`status`)
) COMMENT='回收代卖订单表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_consignment_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `consignment_id` int NOT NULL DEFAULT 0 COMMENT '代卖订单ID',
  `source_order_id` int NOT NULL DEFAULT 0 COMMENT '来源订单ID',
  `source_device_id` int NOT NULL DEFAULT 0 COMMENT '来源设备ID',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人名称',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作动作',
  `old_status` tinyint NOT NULL DEFAULT 0 COMMENT '原状态',
  `new_status` tinyint NOT NULL DEFAULT 0 COMMENT '新状态',
  `before_data` json COMMENT '变更前数据',
  `after_data` json COMMENT '变更后数据',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_consignment` (`site_id`,`consignment_id`),
  KEY `idx_site_device` (`site_id`,`source_device_id`)
) COMMENT='回收代卖日志表';

DROP TABLE IF EXISTS `{{prefix}}recycle_device_payment`;
CREATE TABLE `{{prefix}}recycle_device_payment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '打款批次号',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单编号',
  `device_imei` varchar(50) NOT NULL DEFAULT '' COMMENT '设备IMEI',
  `device_model` varchar(100) NOT NULL DEFAULT '' COMMENT '设备型号',
  `amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '打款金额',
  `pay_type` varchar(50) NOT NULL DEFAULT '' COMMENT '打款方式',
  `pay_account` varchar(255) NOT NULL DEFAULT '' COMMENT '收款账号',
  `pay_name` varchar(50) NOT NULL DEFAULT '' COMMENT '收款人',
  `pay_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '打款备注',
  `payment_images` text COMMENT '打款凭证图片',
  `pay_uid` int NOT NULL DEFAULT 0 COMMENT '打款操作人ID',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '打款时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_order` (`site_id`,`order_id`),
  KEY `idx_site_device` (`site_id`,`device_id`),
  KEY `idx_pay_no` (`pay_no`)
) COMMENT='回收设备打款记录表';

DROP TABLE IF EXISTS `{{prefix}}recycle_notice_log`;
CREATE TABLE `{{prefix}}recycle_notice_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单编号',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `notice_key` varchar(100) NOT NULL DEFAULT '' COMMENT '通知标识',
  `scene` varchar(100) NOT NULL DEFAULT '' COMMENT '业务场景',
  `receiver_type` varchar(30) NOT NULL DEFAULT 'member' COMMENT '接收人类型',
  `receiver_id` int NOT NULL DEFAULT 0 COMMENT '接收人ID',
  `device_ids` text COMMENT '设备ID JSON数组',
  `device_count` int NOT NULL DEFAULT 0 COMMENT '涉及设备数',
  `target_page` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转页面',
  `request_data` text COMMENT '发送参数JSON',
  `response_data` text COMMENT '响应JSON',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待发送，1成功，2失败，3重复跳过',
  `fail_reason` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `send_time` int NOT NULL DEFAULT 0 COMMENT '发送时间',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_order` (`site_id`,`order_id`),
  KEY `idx_site_member` (`site_id`,`member_id`),
  KEY `idx_notice_status` (`notice_key`,`status`),
  KEY `idx_create_at` (`create_at`)
) COMMENT='回收通知发送日志表';



-- 3. 

DROP TABLE IF EXISTS `{{prefix}}recycle_return_order`;

-- 创建退回订单表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_return_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '原订单ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '退回订单号',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待退回，1退回中，2已完成，-1已取消',
  `express_company` varchar(100) NOT NULL DEFAULT '' COMMENT '快递公司',
  `express_no` varchar(100) NOT NULL DEFAULT '' COMMENT '快递单号',
  `return_address` varchar(255) NOT NULL DEFAULT '' COMMENT '退回地址',
  `comment` text COMMENT '备注1',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '管理员备注',
  `create_at` int not null DEFAULT 0 COMMENT '创建时间',
  `update_at` int not null DEFAULT 0 COMMENT '更新时间',
  `over_at` datetime DEFAULT NULL COMMENT '完成时间',
  `operator_uid` int(11) NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人姓名',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `member_name` varchar(50) NOT NULL DEFAULT '' COMMENT '会员姓名',
  `member_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '会员手机号',
  `delete_at` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
  PRIMARY KEY (`id`)
)  COMMENT='回收退回订单表';



-- ----------------------------
-- 4. 退货订单设备关联表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}recycle_return_device`;
-- 创建退回设备关联表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_return_device` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `return_order_id` int(11) NOT NULL DEFAULT 0 COMMENT '退回订单ID',
  `device_id` int(11) NOT NULL DEFAULT 0 COMMENT '设备ID',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待退回，1已退回',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
) COMMENT='回收退回设备关联表';




-- ----------------------------
-- 5. 回收分类表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}recycle_category`;
CREATE TABLE `{{prefix}}recycle_category` (
  `category_id` int NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `category_name` varchar(255) NOT NULL DEFAULT '' COMMENT '分类名称',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '分类图片',
  `images` text NOT NULL COMMENT '分类展示图片',
  `need_vip` int not null DEFAULT 0  COMMENT 'VIP',
  `level` int NOT NULL DEFAULT 0 COMMENT '分类层级',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父分类ID',
  `category_full_name` varchar(255) NOT NULL DEFAULT '' COMMENT '分类全称',
  `is_show` tinyint NOT NULL DEFAULT '1' COMMENT '是否显示',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`category_id`)
)  COMMENT='回收分类表';



DROP TABLE IF EXISTS `{{prefix}}recycle_shop_address`;
CREATE TABLE `{{prefix}}recycle_shop_address`
(
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `site_id`             int           NOT NULL DEFAULT 0 COMMENT '站点id',
    `contact_name`        varchar(255)  NOT NULL DEFAULT '' COMMENT '联系人',
    `mobile`              varchar(50)   NOT NULL DEFAULT '' COMMENT '手机号',
    `province_id`         int(11) NOT NULL DEFAULT 0 COMMENT '省',
    `city_id`             int(11) NOT NULL DEFAULT 0 COMMENT '市',
    `district_id`         int(11) NOT NULL DEFAULT 0 COMMENT '区',
    `address`             varchar(255)  NOT NULL DEFAULT '' COMMENT '详细地址',
    `full_address`        varchar(1000) NOT NULL DEFAULT '' COMMENT '地址',
    `lat`                 varchar(50)   NOT NULL DEFAULT '' COMMENT '纬度',
    `lng`                 varchar(50)   NOT NULL DEFAULT '' COMMENT '经度',
    `is_delivery_address` int(11) NOT NULL DEFAULT 0 COMMENT '是否是发货地址',
    `is_refund_address`   int(11) NOT NULL DEFAULT 0 COMMENT '是否是退货地址',
    `is_default_delivery` int(11) NOT NULL DEFAULT 0 COMMENT '默认发货地址',
    `is_default_refund`   int(11) NOT NULL DEFAULT 0 COMMENT '默认收货地址',
    PRIMARY KEY (`id`)
)  COMMENT='商家地址库';

-- ----------------------------
-- 8.收款方式信息表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}payment_info`;
CREATE TABLE `{{prefix}}payment_info` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `pay_type` varchar(50) NOT NULL DEFAULT '' COMMENT '收款方式',
  `account` varchar(255) NOT NULL DEFAULT '' COMMENT '收款账号',
  `qrcode_image` varchar(255) NOT NULL DEFAULT '' COMMENT '收款码图片',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
)  COMMENT='收款方式信息表';

-- 

-- 用户的退货地址 身份证 姓名 
DROP TABLE IF EXISTS `{{prefix}}recycle_user_address`;
CREATE TABLE `{{prefix}}recycle_user_address` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '地址',
  `mobile` varchar(255) NOT NULL DEFAULT '' COMMENT '手机号',
  `id_card` varchar(255) NOT NULL DEFAULT '' COMMENT '身份证',
  `card_pic` varchar(255) NOT NULL DEFAULT '' COMMENT '身份证图片',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '姓名',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '删除时间',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  PRIMARY KEY (`id`)
) COMMENT='用户退货地址表';


-- 回收设备日志表 

DROP TABLE IF EXISTS `{{prefix}}recycle_device_log`;
CREATE TABLE `{{prefix}}recycle_device_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `device_id` int NOT NULL DEFAULT 0 COMMENT '设备ID',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人姓名',
  `operation_type` varchar(50) NOT NULL DEFAULT '' COMMENT '操作类型',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作类型',
  `old_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '旧状态',
  `new_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '新状态',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`)
) COMMENT='回收设备日志表';

-- 回收订单日志表 
DROP TABLE IF EXISTS `{{prefix}}recycle_order_log`;
CREATE TABLE `{{prefix}}recycle_order_log` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人姓名',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作类型',
  `old_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '旧状态',
  `new_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '新状态',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`)
)  COMMENT='回收订单日志表';

DROP TABLE IF EXISTS `{{prefix}}recycle_printer`;
-- 回收打印机表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_printer` (
    `printer_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '打印机ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `brand` varchar(50) NOT NULL DEFAULT '' COMMENT '打印机品牌',
    `printer_name` varchar(50) NOT NULL DEFAULT '' COMMENT '打印机名称',
    `sn` varchar(100) NOT NULL DEFAULT '' COMMENT '打印机编号',
    `user_name` varchar(100) NOT NULL DEFAULT '' COMMENT '开发者ID',
    `user_key` varchar(100) NOT NULL DEFAULT '' COMMENT '开发者密钥',
    `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态(0-禁用,1-启用)',
    `type` varchar(20) NOT NULL DEFAULT 'label' COMMENT '打印机类型：ticket-小票打印机，label-标签打印机',
    `is_default` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否默认打印机：0-否，1-是',
    `uid` int(11) NOT NULL DEFAULT 0 COMMENT '绑定的用户ID',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `delete_time` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`printer_id`)
)  COMMENT='回收打印机表';

-- 回收打印模板表
DROP TABLE IF EXISTS `{{prefix}}recycle_printer_template`;
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_printer_template` (
    `template_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '模板ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
    `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型：device_label-设备标签，order_receipt-订单小票，return_label-退回标签，consignment_receipt-代卖凭证，custom-自定义',
    `size` varchar(20) NOT NULL DEFAULT '58mm' COMMENT '模板尺寸：58mm，80mm等',
    `content` text NOT NULL COMMENT '模板内容(JSON格式)',
    `html_content` text NOT NULL COMMENT '模板内容(HTML格式)',
    `width` int(11) NOT NULL DEFAULT 0 COMMENT '模板宽度',
    `height` int(11) NOT NULL DEFAULT 0 COMMENT '模板高度',
    `instruction_content` text NOT NULL COMMENT '芯烨云打印指令内容',
    `variables` text NOT NULL COMMENT '可用变量(JSON格式)',
    `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态(0-禁用,1-启用)',
    `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认模板：0-否，1-是',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '绑定打印机ID，0表示不指定',
    `trigger_event` varchar(50) NOT NULL DEFAULT '' COMMENT '旧版触发时机，兼容字段',
    `uid` int(11) NOT NULL DEFAULT 0 COMMENT '创建用户ID',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `delete_time` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`template_id`)
)  COMMENT='回收打印模板表';

-- 回收打印场景表
DROP TABLE IF EXISTS `{{prefix}}recycle_print_scene`;
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_scene` (
    `scene_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '场景ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `trigger_key` varchar(100) NOT NULL DEFAULT '' COMMENT '触发事件标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型：device-设备，order-订单，return-退货，consignment-代卖',
    `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型',
    `auto_print` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动打印：0-否，1-是',
    `idempotency_scope` varchar(50) NOT NULL DEFAULT 'site_scene_biz' COMMENT '幂等范围：none/site_scene_biz/site_scene_device/site_scene_order',
    `retry_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否失败重试：0-否，1-是',
    `max_attempts` int(11) NOT NULL DEFAULT 3 COMMENT '最大尝试次数',
    `condition_config` text NOT NULL COMMENT '触发条件配置',
    `template_id` int(11) NOT NULL DEFAULT 0 COMMENT '指定模板ID，0使用默认模板',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '指定打印机ID，0使用模板绑定或账号默认打印机',
    `copies` int(11) NOT NULL DEFAULT 1 COMMENT '打印份数',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0-停用，1-启用',
    `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`scene_id`),
    UNIQUE KEY `uk_site_scene` (`site_id`, `scene_key`)
) COMMENT='回收打印场景表';

-- 回收打印任务表
DROP TABLE IF EXISTS `{{prefix}}recycle_print_task`;
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_task` (
    `task_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '任务ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `trigger_key` varchar(100) NOT NULL DEFAULT '' COMMENT '触发事件标识',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型',
    `biz_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务ID',
    `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
    `device_id` int(11) NOT NULL DEFAULT 0 COMMENT '设备ID',
    `template_id` int(11) NOT NULL DEFAULT 0 COMMENT '模板ID',
    `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '打印机ID',
    `printer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '打印机名称',
    `copies` int(11) NOT NULL DEFAULT 1 COMMENT '打印份数',
    `priority` int(11) NOT NULL DEFAULT 100 COMMENT '优先级，越小越优先',
    `mode` varchar(20) NOT NULL DEFAULT 'auto' COMMENT '打印模式：auto/manual/reprint',
    `unique_key` varchar(191) NOT NULL DEFAULT '' COMMENT '幂等键',
    `payload` text NOT NULL COMMENT '业务入参快照',
    `variables_snapshot` text NOT NULL COMMENT '变量快照',
    `instruction_snapshot` mediumtext COMMENT '打印指令快照',
    `response_snapshot` text NOT NULL COMMENT '打印响应快照',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0待执行 1执行中 2成功 3失败 4跳过 5取消',
    `fail_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '失败原因',
    `attempts` int(11) NOT NULL DEFAULT 0 COMMENT '已尝试次数',
    `max_attempts` int(11) NOT NULL DEFAULT 3 COMMENT '最大尝试次数',
    `next_retry_at` int(11) NOT NULL DEFAULT 0 COMMENT '下次重试时间',
    `operator_uid` int(11) NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `finish_time` int(11) NOT NULL DEFAULT 0 COMMENT '完成时间',
    PRIMARY KEY (`task_id`),
    UNIQUE KEY `uk_site_unique` (`site_id`, `unique_key`),
    KEY `idx_status_retry` (`status`, `next_retry_at`),
    KEY `idx_site_scene` (`site_id`, `scene_key`),
    KEY `idx_site_biz` (`site_id`, `biz_type`, `biz_id`),
    KEY `idx_site_device` (`site_id`, `device_id`),
    KEY `idx_site_order` (`site_id`, `order_id`)
) COMMENT='回收打印任务表';

-- 回收打印日志表
DROP TABLE IF EXISTS `{{prefix}}recycle_print_log`;
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_log` (
    `log_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `scene_key` varchar(50) NOT NULL DEFAULT '' COMMENT '场景标识',
    `scene_name` varchar(100) NOT NULL DEFAULT '' COMMENT '场景名称',
    `biz_type` varchar(30) NOT NULL DEFAULT '' COMMENT '业务类型',
    `biz_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务ID',
    `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
    `device_id` int(11) NOT NULL DEFAULT 0 COMMENT '设备ID',
    `template_id` int(11) NOT NULL DEFAULT 0 COMMENT '模板ID',
    `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
    `printer_id` int(11) NOT NULL DEFAULT 0 COMMENT '打印机ID',
    `printer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '打印机名称',
    `copies` int(11) NOT NULL DEFAULT 1 COMMENT '打印份数',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0-失败/跳过，1-成功',
    `message` varchar(500) NOT NULL DEFAULT '' COMMENT '结果消息',
    `operator_uid` int(11) NOT NULL DEFAULT 0 COMMENT '操作人ID',
    `plan_snapshot` text NOT NULL COMMENT '打印计划快照',
    `request_snapshot` text NOT NULL COMMENT '请求快照',
    `response_snapshot` text NOT NULL COMMENT '响应快照',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`log_id`),
    KEY `idx_site_scene` (`site_id`, `scene_key`),
    KEY `idx_site_device` (`site_id`, `device_id`),
    KEY `idx_site_order` (`site_id`, `order_id`)
) COMMENT='回收打印日志表';

-- 第三方API调用日志表
DROP TABLE IF EXISTS `{{prefix}}third_party_api_log`;
CREATE TABLE IF NOT EXISTS `{{prefix}}third_party_api_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `service_type` varchar(50) NOT NULL DEFAULT '' COMMENT '服务类型',
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称',
    `method` varchar(100) NOT NULL DEFAULT '' COMMENT '调用方法',
    `request_params` json DEFAULT NULL COMMENT '请求参数',
    `response_data` json DEFAULT NULL COMMENT '响应数据',
    `cost` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '费用',
    `duration` int(11) NOT NULL DEFAULT 0 COMMENT '耗时（毫秒）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1成功 0失败',
    `error_msg` varchar(500) DEFAULT NULL COMMENT '错误信息',
    `create_at` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_site_service` (`site_id`, `service_type`, `create_at`),
    KEY `idx_create_at` (`create_at`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方API调用日志表';

-- 第三方服务费用统计表
DROP TABLE IF EXISTS `{{prefix}}third_party_cost_stats`;
CREATE TABLE IF NOT EXISTS `{{prefix}}third_party_cost_stats` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `service_type` varchar(50) NOT NULL DEFAULT '' COMMENT '服务类型',
    `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商名称',
    `date` date NOT NULL COMMENT '统计日期',
    `total_calls` int(11) NOT NULL DEFAULT 0 COMMENT '总调用次数',
    `success_calls` int(11) NOT NULL DEFAULT 0 COMMENT '成功次数',
    `failed_calls` int(11) NOT NULL DEFAULT 0 COMMENT '失败次数',
    `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '总费用',
    `avg_duration` int(11) NOT NULL DEFAULT 0 COMMENT '平均耗时（毫秒）',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_site_service_date` (`site_id`, `service_type`, `provider_name`, `date`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方服务费用统计表';

-- ----------------------------
-- 设备查询配置表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}device_query_config`;
CREATE TABLE `{{prefix}}device_query_config` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称',
  `api_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'API密钥',
  `base_url` varchar(255) NOT NULL DEFAULT 'http://api.3023data.com' COMMENT 'API基础URL',
  `enabled_apis` varchar(255) NOT NULL DEFAULT '/apple/activationlock' COMMENT '启用的API接口列表',
  `timeout` int NOT NULL DEFAULT '30' COMMENT '请求超时时间(秒)',
  `max_retry` int NOT NULL DEFAULT '3' COMMENT '最大重试次数',
  `cache_time` int NOT NULL DEFAULT '3600' COMMENT '缓存时间(秒)',
  `daily_limit` int NOT NULL DEFAULT '1000' COMMENT '每日查询限制',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '账户余额',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0-禁用，1-启用',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
)  COMMENT='设备查询配置表';

-- ----------------------------
-- 设备查询接口清单表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}device_query_api`;
CREATE TABLE `{{prefix}}device_query_api` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '接口ID',
  `name` varchar(100) NOT NULL DEFAULT '默认API接口清单' COMMENT '清单名称',
  `version` varchar(20) NOT NULL DEFAULT '1.0.0' COMMENT '版本号',
  `api_list` varchar(255) NOT NULL COMMENT '接口',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0-禁用，1-启用',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
) COMMENT='设备查询接口清单表';



insert into `{{prefix}}device_query_api` (`name`,  `api_list`,  `remark`) values 

("苹果保修查询","/apple/coverage","0.2-0.8元"),
("苹果保修查询（容量/颜色）","/apple/coverage-capacity","1元"),
("苹果保修查询（备用）","/apple/coverage-backup","1.2元"),
("激活锁查询","/apple/activationlock","0.4元"),
("ID黑白查询","/apple/icloud","0.8元"),
("序列号转换","/apple/serial","1元"),
("维修状态查询","/apple/repair","0.2元"),
("网络锁查询","/apple/simlock","1元"),
("运营商查询","/apple/carrier","1.2元"),
("销售地查询","/apple/country","1.2元"),
("型号号码查询","/apple/partnumber","1.6元"),
("购买日期查询","/apple/purchase","1.2元"),
("监管锁查询","/apple/mdm","10元"),
("Mac激活锁查询","/apple/mac-activationlock","2元"),
("苹果验机报告（网络锁）","/apple/details","2.5元"),
("苹果验机报告（购买日期）","/apple/details-purchase","3元"),
("苹果验机报告（旗舰版）","/apple/details-ultimate","3.5元"),
("苹果型号查询","/apple/model","0.05元"),
("华为保修查询","/huawei/coverage","0.4元"),
("荣耀保修查询","/honor/coverage","0.4元"),
("小米保修查询","/xiaomi/coverage","0.8元"),
("OPPO保修查询","/oppo/coverage","0.8元"),
("vivo保修查询","/vivo/coverage","1元"),
("三星保修查询","/samsung/coverage","1元"),
("realme保修查询","/realme/coverage","0.8元"),
("努比亚保修查询","/nubia/coverage","1元"),
("moto保修查询","/motorola/coverage","1元"),
("中兴保修查询","/zte/coverage","0.6元"),
("小米账号锁查询","/xiaomi/activationlock","0.02元"),
("IMEI查询（型号）","/imei/model","0.2元"),
("IMEI查询（生产日期）","/imei/manufacture","0.6元"),
("IMEI查询（黑名单）","/imei/blacklist","0.4元"),
("AT&T状态查询","/imei/att","0.8元"),
("T-Mobile状态查询","/imei/t-mobile","0.8元"),
("Verizon状态查询","/imei/verizon","0.6元"),
("条码查询","/item/barcode","0.02元"),
("IP地址查询","/ip/location?ip=","0.001元"),
("号码归属地查询","/phone/location?phone=","0.001元");


-- ----------------------------
-- 设备查询结果表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}device_query_result`;
CREATE TABLE `{{prefix}}device_query_result` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '结果ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `query_code` varchar(100) NOT NULL DEFAULT '' COMMENT '查询码(IMEI/序列号等)',
  `query_type` varchar(50) NOT NULL DEFAULT 'imei' COMMENT '查询类型：imei,serial,model,coverage,activationlock,other',
  `api_endpoint` varchar(255) NOT NULL DEFAULT '' COMMENT 'API端点',
  `api_name` varchar(100) NOT NULL DEFAULT '' COMMENT 'API名称',
  `query_result` json COMMENT '查询结果(JSON格式)',
  `raw_response` json COMMENT '原始响应(JSON格式)',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0-查询失败，1-查询成功，2-查询中，-1-查询错误',
  `cost_amount` decimal(8,3) NOT NULL DEFAULT '0.000' COMMENT '查询费用',
  `response_time` int NOT NULL DEFAULT 0 COMMENT '响应时间(毫秒)',
  `error_code` varchar(50) NOT NULL DEFAULT '' COMMENT '错误码',
  `error_message` varchar(255) NOT NULL DEFAULT '' COMMENT '错误信息',
  `operator_id` int NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人姓名',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
)  COMMENT='设备查询结果表';

DROP TABLE IF EXISTS `{{prefix}}recycle_category_config`;
CREATE TABLE `{{prefix}}recycle_category_config` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `site_id` int NOT NULL COMMENT '站点ID',
  `is_enable` tinyint NOT NULL DEFAULT '1' COMMENT '是否启用',
  `create_at` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
)  COMMENT='回收分类配置表';

-- 回收报价-品牌表
DROP TABLE IF EXISTS `{{prefix}}recycle_device_brand`;
CREATE TABLE `{{prefix}}recycle_device_brand` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `brand_name` varchar(50) NOT NULL COMMENT '品牌名称',
  `brand_code` varchar(20) NOT NULL COMMENT '品牌编码',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  PRIMARY KEY (`id`)
)   COMMENT='回收设备品牌表';
-- 回收报价-型号表
DROP TABLE IF EXISTS `{{prefix}}recycle_device_model`;
CREATE TABLE `{{prefix}}recycle_device_model` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `brand_id` int NOT NULL COMMENT '品牌ID',
  `model_name` varchar(100) NOT NULL COMMENT '型号名称',
  `network_model` varchar(100) DEFAULT '' COMMENT '网络型号',
  `capacity` varchar(50) DEFAULT '' COMMENT '容量',
  `device_type` varchar(20) DEFAULT 'phone' COMMENT '设备类型 phone手机 tablet平板 watch手表',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model` (`site_id`,`brand_id`,`model_name`,`network_model`,`capacity`)
)  COMMENT='回收设备型号表';

-- 回收报价-价格表

DROP TABLE IF EXISTS `{{prefix}}recycle_device_price`;
CREATE TABLE `{{prefix}}recycle_device_price` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `device_model_id` int NOT NULL COMMENT '设备型号ID',
  `import_batch` varchar(32) NOT NULL COMMENT '导入批次号',
  `price_data` json NOT NULL COMMENT '价格数据JSON格式：{"高保充新":7000,"充新":6900,"靓机":6500}',
  `price_date` date NOT NULL COMMENT '价格日期',
  `is_current` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否当前价格 1是 0否',
  `create_at` int NOT NULL DEFAULT 0,
  `update_at` int NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `import_record_id` int NOT NULL DEFAULT 0 COMMENT '导入记录ID',
  PRIMARY KEY (`id`)
)  COMMENT='设备价格表';


CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `template_key` varchar(80) NOT NULL DEFAULT '' COMMENT '模板标识',
  `template_name` varchar(120) NOT NULL DEFAULT '' COMMENT '模板名称',
  `scene` varchar(50) NOT NULL DEFAULT 'phone' COMMENT '场景',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `version` int NOT NULL DEFAULT '1' COMMENT '模板版本',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_template_key` (`site_id`,`template_key`),
  KEY `idx_site_scene` (`site_id`,`scene`,`status`,`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检模板';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `template_id` int NOT NULL DEFAULT '0' COMMENT '模板ID',
  `group_key` varchar(80) NOT NULL DEFAULT '' COMMENT '分组标识',
  `group_name` varchar(120) NOT NULL DEFAULT '' COMMENT '分组名称',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '说明',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_template` (`template_id`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检分组';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_field` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `template_id` int NOT NULL DEFAULT '0' COMMENT '模板ID',
  `group_id` int NOT NULL DEFAULT '0' COMMENT '分组ID',
  `field_key` varchar(80) NOT NULL DEFAULT '' COMMENT '字段标识',
  `field_name` varchar(120) NOT NULL DEFAULT '' COMMENT '字段名称',
  `component` varchar(40) NOT NULL DEFAULT 'input' COMMENT '组件类型',
  `selection_mode` varchar(20) NOT NULL DEFAULT '' COMMENT 'single/multiple',
  `unit` varchar(20) NOT NULL DEFAULT '' COMMENT '单位',
  `placeholder` varchar(255) NOT NULL DEFAULT '' COMMENT '提示语',
  `default_value` varchar(500) NOT NULL DEFAULT '' COMMENT '默认值',
  `is_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否必填',
  `is_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示',
  `seller_visible` tinyint(1) NOT NULL DEFAULT '1' COMMENT '卖家可见',
  `buyer_visible` tinyint(1) NOT NULL DEFAULT '0' COMMENT '买家可见',
  `result_visible` tinyint(1) NOT NULL DEFAULT '1' COMMENT '参与结果文案',
  `result_template` varchar(255) NOT NULL DEFAULT '' COMMENT '结果文案模板',
  `api_fill_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '允许API回填',
  `api_fill_policy` varchar(30) NOT NULL DEFAULT 'empty_only' COMMENT '回填策略',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `extra_config` json DEFAULT NULL COMMENT '扩展配置',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_field` (`template_id`,`field_key`),
  KEY `idx_group` (`group_id`,`is_show`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检字段';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_check_option` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `field_id` int NOT NULL DEFAULT '0' COMMENT '字段ID',
  `option_label` varchar(120) NOT NULL DEFAULT '' COMMENT '选项名称',
  `option_value` varchar(80) NOT NULL DEFAULT '' COMMENT '选项值',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `is_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `extra_config` json DEFAULT NULL COMMENT '扩展配置',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_field` (`field_id`,`is_show`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收质检字段选项';

CREATE TABLE IF NOT EXISTS `{{prefix}}express_address_book` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `address_type` varchar(20) NOT NULL DEFAULT 'sender' COMMENT '地址类型 sender寄件人 receiver收件人',
  `name` varchar(80) NOT NULL DEFAULT '' COMMENT '联系人',
  `mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '手机号',
  `province` varchar(80) NOT NULL DEFAULT '' COMMENT '省',
  `city` varchar(80) NOT NULL DEFAULT '' COMMENT '市',
  `district` varchar(80) NOT NULL DEFAULT '' COMMENT '区县',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '详细地址',
  `tag` varchar(50) NOT NULL DEFAULT '' COMMENT '标签',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_site_type` (`site_id`,`address_type`,`status`,`is_top`,`is_default`),
  KEY `idx_mobile` (`site_id`,`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递常用寄收件地址';

CREATE TABLE IF NOT EXISTS `{{prefix}}express_order_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `order_no` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方订单号',
  `third_order_no` varchar(100) NOT NULL DEFAULT '' COMMENT '商户订单号',
  `recycle_order_id` int NOT NULL DEFAULT '0' COMMENT '回收订单ID',
  `recycle_device_id` int NOT NULL DEFAULT '0' COMMENT '回收设备ID',
  `provider_name` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商',
  `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '快递产品编码',
  `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '快递产品名称',
  `delivery_id` varchar(100) NOT NULL DEFAULT '' COMMENT '运单号',
  `sender_name` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件人',
  `sender_mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '寄件手机号',
  `sender_province` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件省',
  `sender_city` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件市',
  `sender_district` varchar(80) NOT NULL DEFAULT '' COMMENT '寄件区县',
  `sender_address` varchar(255) NOT NULL DEFAULT '' COMMENT '寄件详细地址',
  `receiver_name` varchar(80) NOT NULL DEFAULT '' COMMENT '收件人',
  `receiver_mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '收件手机号',
  `receiver_province` varchar(80) NOT NULL DEFAULT '' COMMENT '收件省',
  `receiver_city` varchar(80) NOT NULL DEFAULT '' COMMENT '收件市',
  `receiver_district` varchar(80) NOT NULL DEFAULT '' COMMENT '收件区县',
  `receiver_address` varchar(255) NOT NULL DEFAULT '' COMMENT '收件详细地址',
  `goods_name` varchar(100) NOT NULL DEFAULT '' COMMENT '物品名称',
  `goods_value` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '保价金额',
  `package_count` int NOT NULL DEFAULT '1' COMMENT '包裹数',
  `estimated_weight` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预估重量',
  `actual_weight` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实际重量',
  `weight_diff` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '重量差异',
  `volume` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT '体积',
  `volume_long` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '长cm',
  `volume_width` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '宽cm',
  `volume_height` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '高cm',
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预估费用',
  `actual_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实际费用',
  `cost_diff` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '费用差异',
  `payment_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付状态',
  `user_paid` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '用户支付',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `order_status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT '运单状态',
  `status_history` json DEFAULT NULL COMMENT '状态历史',
  `api_response` json DEFAULT NULL COMMENT 'API响应',
  `pickup_time` int NOT NULL DEFAULT '0' COMMENT '揽收时间',
  `delivery_time` int NOT NULL DEFAULT '0' COMMENT '签收时间',
  `cancel_time` int NOT NULL DEFAULT '0' COMMENT '取消时间',
  `cancel_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '取消原因',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_site_create` (`site_id`,`create_at`),
  KEY `idx_order_no` (`site_id`,`order_no`),
  KEY `idx_third_order_no` (`site_id`,`third_order_no`),
  KEY `idx_delivery_id` (`site_id`,`delivery_id`),
  KEY `idx_recycle_order` (`site_id`,`recycle_order_id`),
  KEY `idx_status` (`site_id`,`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快递运单记录';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_express_provider_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `provider` varchar(50) NOT NULL DEFAULT '' COMMENT '服务商标识',
  `provider_name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务商名称',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认',
  `config` json DEFAULT NULL COMMENT '服务商配置',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_provider` (`site_id`,`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收快递服务商配置';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_dashboard_widget` (
  `widget_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '组件ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `widget_key` varchar(100) NOT NULL DEFAULT '' COMMENT '组件标识',
  `widget_name` varchar(100) NOT NULL DEFAULT '' COMMENT '组件名称',
  `widget_type` varchar(30) NOT NULL DEFAULT 'stat' COMMENT '组件类型 stat/chart/table/action/section',
  `data_key` varchar(100) NOT NULL DEFAULT '' COMMENT '指标标识',
  `data_scope` varchar(30) NOT NULL DEFAULT 'own' COMMENT '数据范围 own/site/assigned/none',
  `role_ids` text COMMENT '可见角色ID JSON数组，空表示不限制',
  `uids` text COMMENT '可见用户ID JSON数组，空表示不限制',
  `config` text COMMENT '组件扩展配置',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用 1启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`widget_id`),
  UNIQUE KEY `uk_site_widget` (`site_id`,`widget_key`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_type` (`site_id`,`widget_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收首页组件配置表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_alert_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `rule_key` varchar(100) NOT NULL DEFAULT '' COMMENT '规则标识',
  `rule_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名称',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '规则说明',
  `condition_type` varchar(50) NOT NULL DEFAULT '' COMMENT '条件类型 timeout/rate/amount/count',
  `threshold_value` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '阈值',
  `threshold_unit` varchar(30) NOT NULL DEFAULT '' COMMENT '阈值单位 hour/percent/yuan/count',
  `level` varchar(30) NOT NULL DEFAULT 'warning' COMMENT '级别 info/warning/danger',
  `message_template` varchar(500) NOT NULL DEFAULT '' COMMENT '提醒文案模板',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用 1启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_rule` (`site_id`,`rule_key`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收看板异常规则配置表';

CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_print_variable` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `variable_key` varchar(100) NOT NULL DEFAULT '' COMMENT '变量标识',
  `variable_name` varchar(100) NOT NULL DEFAULT '' COMMENT '变量名称',
  `category` varchar(50) NOT NULL DEFAULT '' COMMENT '变量分类 order/device/payment/custom',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '变量说明',
  `sample_value` varchar(255) NOT NULL DEFAULT '' COMMENT '示例值',
  `source_type` varchar(30) NOT NULL DEFAULT 'field' COMMENT '来源类型 field/json/custom',
  `source_path` varchar(255) NOT NULL DEFAULT '' COMMENT '来源路径',
  `alias_keys` text COMMENT '兼容别名 JSON数组',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0停用 1启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_variable` (`site_id`,`variable_key`),
  KEY `idx_site_category` (`site_id`,`category`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回收打印变量配置表';

CREATE TABLE IF NOT EXISTS `{{prefix}}yisu_product_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `product_code` varchar(50) NOT NULL DEFAULT '' COMMENT '产品编码',
  `product_name` varchar(100) NOT NULL DEFAULT '' COMMENT '产品名称',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '产品图标',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_at` int NOT NULL DEFAULT '0',
  `update_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_product` (`site_id`,`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='易速快递产品配置';
