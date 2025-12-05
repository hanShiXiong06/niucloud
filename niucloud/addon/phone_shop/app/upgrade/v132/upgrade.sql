

ALTER TABLE `phone_shop_point_exchange` CHANGE COLUMN `product_detail` `product_detail` TEXT DEFAULT NULL COMMENT '兑换产品信息';



DROP TABLE IF EXISTS `phone_shop_delivery_electronic_sheet`;
CREATE TABLE `phone_shop_delivery_electronic_sheet` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `template_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '模板名称',
  `express_company_id` INT(11) NOT NULL DEFAULT 0 COMMENT '物流公司id',
  `customer_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单客户账号（CustomerName）',
  `customer_pwd` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单密码（CustomerPwd）',
  `send_site` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'SendSite',
  `send_staff` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'SendStaff',
  `month_code` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'MonthCode',
  `pay_type` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '邮费支付方式（1：现付，2：到付，3：月结）',
  `is_notice` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '快递员上门揽件（0：否，1：是）',
  `status` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '状态（1：开启，0：关闭）',
  `exp_type` INT(11) NOT NULL DEFAULT 0 COMMENT '物流公司业务类型',
  `print_style` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单打印模板样式',
  `is_default` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否默认（1：是，0：否）',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`id`)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '电子面单';

ALTER TABLE `phone_shop_delivery_company` CHANGE COLUMN `express_no` `express_no` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '物流公司编号(用于物流跟踪)';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `express_no_electronic_sheet` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '物流公司编号(用于电子面单)';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `electronic_sheet_switch` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否支持电子面单（0：不支持，1：支持）';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `print_style` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '电子面单打印模板样式，json字符串';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `exp_type` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '物流公司业务类型，json字符串';

ALTER TABLE `phone_shop_delivery_company` MODIFY `create_time` INT(11) NOT NULL DEFAULT 0 AFTER `exp_type`;

ALTER TABLE `phone_shop_delivery_company` MODIFY `update_time` INT(11) NOT NULL DEFAULT 0 AFTER `create_time`;

ALTER TABLE `phone_shop_point_exchange` CHANGE COLUMN `product_detail` `product_detail` TEXT DEFAULT NULL COMMENT '兑换产品信息';

DROP TABLE IF EXISTS `phone_shop_order_batch_delivery`;
CREATE TABLE `shop_order_batch_delivery` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `main_id` INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
  `status` INT(11) NOT NULL DEFAULT 1 COMMENT '状态 进行中  已完成  已失败',
  `type` VARCHAR(255) NOT NULL DEFAULT '操作类型 批量发货  批量打单 ....' COMMENT '操作类型',
  `total_num` INT(11) NOT NULL DEFAULT 0 COMMENT '总发货单数',
  `success_num` INT(11) NOT NULL DEFAULT 0 COMMENT '成功发货单数',
  `fail_num` INT(11) NOT NULL DEFAULT 0 COMMENT '失败发货单数',
  `data` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '导入文件的路径',
  `output` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '对外输出记录',
  `fail_output` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败记录',
  `fail_remark` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `create_time` INT(11) NOT NULL COMMENT '创建时间',
  `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '操作时间',
  PRIMARY KEY (`id`)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '订单批量发货表';


DROP TABLE IF EXISTS `phone_shop_delivery_electronic_sheet`;
CREATE TABLE `phone_shop_delivery_electronic_sheet` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `template_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '模板名称',
  `express_company_id` INT(11) NOT NULL DEFAULT 0 COMMENT '物流公司id',
  `customer_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单客户账号（CustomerName）',
  `customer_pwd` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单密码（CustomerPwd）',
  `send_site` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'SendSite',
  `send_staff` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'SendStaff',
  `month_code` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'MonthCode',
  `pay_type` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '邮费支付方式（1：现付，2：到付，3：月结）',
  `is_notice` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '快递员上门揽件（0：否，1：是）',
  `status` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '状态（1：开启，0：关闭）',
  `exp_type` INT(11) NOT NULL DEFAULT 0 COMMENT '物流公司业务类型',
  `print_style` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单打印模板样式',
  `is_default` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否默认（1：是，0：否）',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`id`)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '电子面单';

ALTER TABLE `phone_shop_delivery_company` CHANGE COLUMN `express_no` `express_no` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '物流公司编号(用于物流跟踪)';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `express_no_electronic_sheet` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '物流公司编号(用于电子面单)';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `electronic_sheet_switch` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否支持电子面单（0：不支持，1：支持）';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `print_style` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '电子面单打印模板样式，json字符串';

ALTER TABLE `phone_shop_delivery_company` ADD COLUMN `exp_type` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '物流公司业务类型，json字符串';

ALTER TABLE `phone_shop_delivery_company` MODIFY `create_time` INT(11) NOT NULL DEFAULT 0 AFTER `exp_type`;

ALTER TABLE `phone_shop_delivery_company` MODIFY `update_time` INT(11) NOT NULL DEFAULT 0 AFTER `create_time`;

ALTER TABLE `phone_shop_order_refund` CHANGE COLUMN `delivery` `delivery` VARCHAR(3000) NOT NULL DEFAULT '' COMMENT '退货配送信息';

DROP TABLE IF EXISTS `phone_shop_newcomer_member_records`;
CREATE TABLE `phone_shop_newcomer_member_records` (
  `record_id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `member_id` INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
  `validity_time` INT(11) NOT NULL DEFAULT 0 COMMENT '有效期',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '参与时间',
  `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `is_join` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否参与',
  `order_id` INT(11) NOT NULL DEFAULT 0 COMMENT '参与订单id',
  `goods_ids` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '参与商品id集合',
  `sku_ids` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '参与商品规格id集合',
  PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='新人专享会员参与记录表';

ALTER TABLE `phone_shop_goods` ADD COLUMN `goods_video` VARCHAR(555) DEFAULT '' COMMENT '商品视频';

ALTER TABLE `phone_shop_goods` MODIFY `goods_video` VARCHAR(555) DEFAULT '' COMMENT '商品视频' AFTER `goods_image`;

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `time_is_open` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '配送时间设置 0 关闭 1 开启';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `time_type` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '时间选取类型 0 每天  1 自定义';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `time_week` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '营业时间  周一 周二.......';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `time_interval` INT(11) NOT NULL DEFAULT 30 COMMENT '时段设置单位分钟';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `advance_day` INT(11) NOT NULL DEFAULT 0 COMMENT '时间选择需提前多少天';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `most_day` INT(11) NOT NULL DEFAULT 7 COMMENT '最多可预约多少天';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `start_time` INT(11) NOT NULL DEFAULT 0 COMMENT '当日的起始时间';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `end_time` INT(11) NOT NULL DEFAULT 0 COMMENT '当日的营业结束时间';

ALTER TABLE `phone_shop_delivery_local_delivery` ADD COLUMN `delivery_time` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '配送时间段';

ALTER TABLE `phone_shop_active_goods` ADD COLUMN `sku_id` INT(11) DEFAULT 0 COMMENT '商品规格id';

ALTER TABLE `phone_shop_active_goods` MODIFY `sku_id` INT(11) DEFAULT 0 COMMENT '商品规格id' AFTER `goods_id`;

DROP TABLE IF EXISTS `phone_shop_goods_label_group`;
CREATE TABLE `phone_shop_goods_label_group` (
  group_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分组ID',
  site_id INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  group_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT '分组名称',
  sort INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  create_time INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  update_time INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品标签分组表';

ALTER TABLE `phone_shop_goods_label` ADD COLUMN `group_id` INT(11) NOT NULL DEFAULT 0 COMMENT '标签分组id';

ALTER TABLE `phone_shop_goods_label` ADD COLUMN `style_type` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '效果设置，diy：自定义，icon：图片';

ALTER TABLE `phone_shop_goods_label` ADD COLUMN `color_json` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '自定义颜色（文字、背景、边框），json格式';

ALTER TABLE `phone_shop_goods_label` ADD COLUMN `icon` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '图标';

ALTER TABLE `phone_shop_goods_label` ADD COLUMN status INT(11) NOT NULL DEFAULT 0 COMMENT '状态，1：启用，0；关闭';

ALTER TABLE `phone_shop_goods_label` MODIFY `memo` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '标签说明' AFTER `status`;

ALTER TABLE `phone_shop_goods_label` MODIFY `sort` INT(11) NOT NULL DEFAULT 0 COMMENT '排序' AFTER `memo`;

ALTER TABLE `phone_shop_goods_label` MODIFY `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间' AFTER `sort`;

ALTER TABLE `phone_shop_goods_label` MODIFY `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间' AFTER `create_time`;


DROP TABLE IF EXISTS `phone_shop_site`;
CREATE TABLE `phone_shop_site` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL COMMENT '站点id',
  `site_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '商户名',
  `client` tinyint NOT NULL DEFAULT '0' COMMENT '是否是回收商 默认0 , 1是',
  `category_status` tinyint NOT NULL DEFAULT '1' COMMENT '是否使用0站点分类',
  `brand_status` tinyint NOT NULL DEFAULT '1' COMMENT '是否使用0站点品牌',
  `label_group_status` tinyint NOT NULL DEFAULT '1' COMMENT '是否使用0站点标签分组',
  `label_status` tinyint NOT NULL DEFAULT '1' COMMENT '是否使用0站点的标签',
  `service_status` tinyint NOT NULL DEFAULT '1' COMMENT '是否使用0站点的服务信息',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;