-- ----------------------------
-- 1.  回收订单主表
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}recycle_order`;
CREATE TABLE `{{prefix}}recycle_order` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_no` varchar(50)   NOT NULL DEFAULT '' COMMENT '订单编号',
  `customer_name` varchar(50)   NOT NULL DEFAULT '' COMMENT '客户姓名',
  `customer_phone` varchar(20)   NOT NULL DEFAULT '' COMMENT '客户电话',
  `pay_type` varchar(20)   NOT NULL DEFAULT '' COMMENT '打款时间',
  `pay_account` varchar(50)   NOT NULL DEFAULT '' COMMENT '支付账号',
  `delivery_type` varchar(20)   NOT NULL DEFAULT 'express' COMMENT '发货方式：express-快递，self-自送',
  `express_company` varchar(50)   DEFAULT '' COMMENT '快递公司',
  `express_no` varchar(50)   DEFAULT '' COMMENT '快递单号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '订单状态：1-待签收，2-已签收，3-质检中，4-已质检，5-已支付，6-已完成，7-已取消',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '订单总金额',
  `device_count` int NOT NULL DEFAULT 0 COMMENT '设备数量',
  `remark` varchar(255)   DEFAULT '' COMMENT '备注',
  `create_at` int not null DEFAULT 0 COMMENT '创建时间',
  `update_at` int not null DEFAULT 0 COMMENT '更新时间',
  `sign_at` int NOT NULL DEFAULT 0 COMMENT '签收时间',
  `complete_at` int DEFAULT NULL COMMENT '完成时间',
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
  `imei` varchar(50)   NOT NULL DEFAULT '' COMMENT 'IMEI号码',
  `model` varchar(100)   NOT NULL DEFAULT '' COMMENT '设备型号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '设备状态：1-待质检，2-质检中，3-已质检，4-待确认，5-已回收，6-已退回，7-已定价，8-已定价（重新定价）',
  `check_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '质检状态：0-未质检，1-质检中，2-已质检',
  `check_at` int DEFAULT NULL COMMENT '质检时间',
  `check_result` text   COMMENT '质检结果',
  `check_uid` int NOT NULL DEFAULT 0 COMMENT '质检员ID',
  `check_images` text   COMMENT '质检图片 逗号 , 隔开 ',
  `initial_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '预估价格',
  `final_price` decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最终价格',
  `price_at` int DEFAULT 0 COMMENT '定价时间',
  `final_price_at` int DEFAULT 0 COMMENT '最终价格时间',
  `price_uid` int NOT NULL DEFAULT 0 COMMENT '价格确认人ID',
  `final_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '最终状态：0 1-已确认 0-未确认',
  `price_remark` varchar(255)   DEFAULT '' COMMENT '价格备注',
  `remark` varchar(255)   DEFAULT '' COMMENT '备注',
  `create_at` int not null DEFAULT 0 COMMENT '创建时间',
  `update_at` int not null DEFAULT 0 COMMENT '更新时间',
  `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `before_price` varchar(255)   DEFAULT '' COMMENT '之前定价',
  `info` json,
  PRIMARY KEY (`id`)
)  COMMENT='设备表';



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
    `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型：device_label-设备标签，order_receipt-订单小票，return_label-退回标签，custom-自定义',
    `size` varchar(20) NOT NULL DEFAULT '58mm' COMMENT '模板尺寸：58mm，80mm等',
    `content` text NOT NULL COMMENT '模板内容(JSON格式)',
    `html_content` text NOT NULL COMMENT '模板内容(HTML格式)',
    `width` int(11) NOT NULL DEFAULT 0 COMMENT '模板宽度',
    `height` int(11) NOT NULL DEFAULT 0 COMMENT '模板高度',
    `instruction_content` text NOT NULL COMMENT '芯烨云打印指令内容',
    `variables` text NOT NULL COMMENT '可用变量(JSON格式)',
    `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态(0-禁用,1-启用)',
    `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认模板：0-否，1-是',
    `uid` int(11) NOT NULL DEFAULT 0 COMMENT '创建用户ID',
    `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `delete_time` int(11) NOT NULL DEFAULT 0 COMMENT '删除时间',
    PRIMARY KEY (`template_id`)
)  COMMENT='回收打印模板表';

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


-- 扣费配置表
CREATE TABLE IF NOT EXISTS `{{prefix}}recycle_deduction_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL DEFAULT '0' COMMENT '站点ID',
  `config_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称',
  `model_id` varchar(100) NOT NULL DEFAULT '' COMMENT '型号ID（goods_id，多个用逗号分隔）',
  `price_id` varchar(50) NOT NULL DEFAULT '' COMMENT '适用报价类型ID（quotation_id，多个用逗号分隔，如：113,114）',
  `remark_text` text COMMENT '完整的备注文本（用于显示）',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序（数字越小越靠前）',
  `is_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用 1=启用 0=禁用',
  `create_at` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_price_id` (`price_id`),
  KEY `idx_is_enable` (`is_enable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扣费配置表';

-- -------------------------------------------------------------

DROP TABLE IF EXISTS `{{prefix}}recycle_deduction_config`;
CREATE TABLE `{{prefix}}recycle_deduction_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `config_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称',
  `model_id` varchar(100) NOT NULL DEFAULT '',
  `price_id` varchar(50) NOT NULL DEFAULT '' COMMENT '适用报价类型（如：113,114）',
  `remark_text` text COMMENT '完整的备注文本（用于显示）',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序（数字越小越靠前）',
  `is_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用 1=启用 0=禁用',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_is_enable` (`is_enable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扣费配置表';


DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_request`;
CREATE TABLE `{{pre}}recycle_quotation_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `quotation_id` int NOT NULL DEFAULT '0' COMMENT '报价单ID',
  `price_name` varchar(100) NOT NULL DEFAULT '' COMMENT '价格名称',
  `default_price_value` int NOT NULL DEFAULT '0' COMMENT '默认价格值',
  `default_percentage_value` int NOT NULL DEFAULT '0' COMMENT '默认百分比值',
  `price_adjustment_type` tinyint NOT NULL DEFAULT '0' COMMENT '价格调整类型',
  `price_adjustment_value` int NOT NULL DEFAULT '0' COMMENT '价格调整值',
  `quotation_background_color` varchar(255) NOT NULL DEFAULT '' COMMENT '报价单背景色',
  `quotation_text_color` varchar(50) NOT NULL DEFAULT '' COMMENT '报价单文字颜色',
  `request_url` text COMMENT '完整请求URL',
  `request_headers` json DEFAULT NULL COMMENT '请求头信息',
  `response_data` json DEFAULT NULL COMMENT '接口返回的原始数据',
  `request_status` tinyint NOT NULL DEFAULT '0' COMMENT '请求状态：0-待请求，1-请求成功，2-请求失败',
  `error_message` text COMMENT '错误信息',
  `request_time` int NOT NULL DEFAULT '0' COMMENT '请求时间',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `idx_request_time` (`request_time`),
  KEY `idx_request_status` (`request_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='报价请求记录表';


DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_config`;
CREATE TABLE `{{prefix}}recycle_quotation_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `quotation_id` int NOT NULL DEFAULT '0' COMMENT '报价单ID',
  `price_name` varchar(100) NOT NULL DEFAULT '' COMMENT '价格名称',
  `config_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称（用于管理）',
  `default_price_value` int NOT NULL DEFAULT '0' COMMENT '默认价格值',
  `default_percentage_value` int NOT NULL DEFAULT '0' COMMENT '默认百分比值',
  `price_adjustment_type` tinyint NOT NULL DEFAULT '0' COMMENT '价格调整类型',
  `price_adjustment_value` int NOT NULL DEFAULT '0' COMMENT '价格调整值',
  `quotation_background_color` varchar(255) NOT NULL DEFAULT '' COMMENT '报价单背景色',
  `quotation_text_color` varchar(50) NOT NULL DEFAULT '' COMMENT '报价单文字颜色',
  `authorization_token` varchar(500) NOT NULL DEFAULT '' COMMENT 'Authorization Token（加密存储）',
  `open_id` varchar(100) NOT NULL DEFAULT '' COMMENT 'OpenId（加密存储）',
  `is_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用：1-启用，0-禁用',
  `auto_request` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否自动请求：1-是，0-否',
  `request_time` varchar(10) NOT NULL DEFAULT '00:00' COMMENT '自动请求时间（格式：HH:mm）',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_quotation_price` (`site_id`,`quotation_id`,`price_name`),
  KEY `idx_is_enable` (`is_enable`),
  KEY `idx_auto_request` (`auto_request`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='报价单配置表';

DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_data`;
CREATE TABLE `{{prefix}}recycle_quotation_data` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `request_id` int NOT NULL DEFAULT '0' COMMENT '请求记录ID',
  `quotation_id` int NOT NULL DEFAULT '0' COMMENT '报价单ID',
  `price_name` varchar(100) NOT NULL DEFAULT '' COMMENT '价格名称',
  `model_id` int NOT NULL DEFAULT '0' COMMENT '型号ID（关联recycle_quotation_model.id）',
  `capacity_id` int NOT NULL DEFAULT '0' COMMENT '内存ID（关联recycle_quotation_capacity.id）',
  `grade_spec_id` int NOT NULL DEFAULT '0' COMMENT '等级规格ID（关联recycle_quotation_grade_spec.id）',
  `deduction_config_id` int DEFAULT NULL COMMENT '扣费配置ID（关联recycle_deduction_config.id）',
  `goods_id` int NOT NULL DEFAULT '0' COMMENT '商品ID（冗余字段，用于查询）',
  `goods_name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称（冗余字段，用于查询）',
  `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '容量（冗余字段，用于查询）',
  `grade_spec_name` varchar(100) NOT NULL DEFAULT '' COMMENT '等级规格名称（冗余字段，用于查询）',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '价格',
  `price_date` date NOT NULL COMMENT '价格日期',
  `add_value_info` int,
  `is_current` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否当前价格：1-是，0-否',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_capacity_id` (`capacity_id`),
  KEY `idx_grade_spec_id` (`grade_spec_id`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_price_date` (`price_date`),
  KEY `idx_is_current` (`is_current`),
  UNIQUE KEY `uk_site_model_capacity_spec_current` (`site_id`,`model_id`,`capacity_id`,`grade_spec_id`,`quotation_id`,`price_name`,`price_date`,`is_current`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='报价数据表';

DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_model`;
CREATE TABLE `{{prefix}}recycle_quotation_model` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `goods_id` int NOT NULL DEFAULT '0' COMMENT '商品ID（外部接口）',
  `goods_name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称（型号名称）',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1-启用，0-禁用',
  `sync_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否同步价格：1-同步，0-不同步',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_goods` (`site_id`,`goods_id`),
  KEY `idx_goods_name` (`goods_name`),
  KEY `idx_status` (`status`),
  KEY `idx_sync_enable` (`sync_enable`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='报价型号表';

-- 报价内存表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_capacity`;
CREATE TABLE `{{prefix}}recycle_quotation_capacity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `model_id` int NOT NULL DEFAULT '0' COMMENT '型号ID（关联recycle_quotation_model.id）',
  `goods_id` int NOT NULL DEFAULT '0' COMMENT '商品ID（外部接口，冗余字段）',
  `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '内存容量（如：256G、512G）',
  `capacity_answer_id` int NOT NULL DEFAULT '0' COMMENT '容量答案ID（从接口数据中获取）',
  `sync_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否同步价格：1-同步，0-不同步',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_model_capacity` (`site_id`,`model_id`,`capacity_answer_id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_sync_enable` (`sync_enable`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='报价内存表';

-- 报价等级规格表
DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_grade_spec`;
CREATE TABLE `{{prefix}}recycle_quotation_grade_spec` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `spec_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规格名称（如：全套充新 橙色、花机、内爆可测）',
  `spec_code` varchar(50) NOT NULL DEFAULT '' COMMENT '规格编码（用于标识，可空）',
  `sync_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否同步价格：1-同步，0-不同步',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_spec_name` (`site_id`,`spec_name`),
  KEY `idx_sync_enable` (`sync_enable`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='报价等级规格表';


DROP TABLE IF EXISTS `{{prefix}}recycle_quotation_price_config`;
CREATE TABLE `{{prefix}}recycle_quotation_price_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int NOT NULL DEFAULT '0' COMMENT '站点ID',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '配置标题',
  `config_type` tinyint NOT NULL DEFAULT '1' COMMENT '配置类型：1-SKU级别(型号+内存+配置项)，2-批量管理(型号+内存)，3-按型号，4-按分组',
  `goods_id` int NOT NULL DEFAULT '0' COMMENT '商品ID（兼容旧数据，SKU级别配置时为空）',
  `capacity` varchar(50) NOT NULL DEFAULT '' COMMENT '内存容量（兼容旧数据，SKU级别配置时为空）',
  `capacity_answer_id` int NOT NULL DEFAULT '0' COMMENT '容量答案ID（从接口数据中获取）',
  `config_item_name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置项名称（兼容旧数据，SKU级别配置时为空）',
  `group_key` int NOT NULL DEFAULT '0' COMMENT '分组key（config_type=4时使用）',
  `sku_list` json DEFAULT NULL COMMENT 'SKU列表（JSON数组，存储多个SKU信息，格式：[{"goods_id":1,"goods_name":"xxx","capacity":"256G","capacity_answer_id":1,"config_item_name":"花机"},...]，仅config_type=1时使用）',
  `adjustment_type` tinyint NOT NULL DEFAULT '1' COMMENT '调整方式：1-固定金额，2-百分比，3-固定价格覆盖',
  `adjustment_value` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '调整值（正数为加，负数为减）',
  `is_enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用：1-启用，0-禁用',
  `create_at` int NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_at` int NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_config_type` (`config_type`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_capacity` (`capacity`),
  KEY `idx_group_key` (`group_key`),
  KEY `idx_is_enable` (`is_enable`),
  KEY `idx_goods_capacity` (`goods_id`,`capacity`),
  KEY `idx_goods_capacity_config` (`goods_id`,`capacity`,`config_item_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='价格配置表';