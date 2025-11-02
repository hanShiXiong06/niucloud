
DROP TABLE IF EXISTS `{{prefix}}home_service_card`;
CREATE TABLE `{{prefix}}home_service_card`
(
    `card_id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '商品id',
    `site_id`              INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `card_name`            VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '次卡名称',
    `card_cover`           VARCHAR(2000)    NOT NULL DEFAULT '' COMMENT '次卡封面',
    `card_image`           TEXT             DEFAULT NULL COMMENT '次卡图片',
    `card_content`         TEXT             DEFAULT NULL COMMENT '次卡详情',
    `status`               TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '商品状态（1:正常 0:下架）',
    `sort`                 INT(11)          NOT NULL DEFAULT 0 COMMENT '排序',
    `sale_num`             INT(11)          NOT NULL DEFAULT 0 COMMENT '销量（总销量）',
    `virtually_sale`       INT(11)          NOT NULL DEFAULT 0 COMMENT '虚拟销量',
    `price`                DECIMAL(10,2)    NOT NULL DEFAULT 0.00 COMMENT '售卖价格(最低价,用于查询)',
    `original_price`       DECIMAL(10,2)    NOT NULL DEFAULT 0.00 COMMENT '原价',
    `create_time`          INT(11)          NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`          INT(11)          NOT NULL DEFAULT 0 COMMENT '修改时间',
    `delete_time`          INT(11)          NOT NULL DEFAULT 0 COMMENT '删除时间',
    `is_delete`            INT(11)          NOT NULL DEFAULT 0 COMMENT '是否删除',
    `poster_id`            INT(11)          NOT NULL DEFAULT 0 COMMENT '海报id',
    `total_times`          INT(11)          NOT NULL DEFAULT 0 COMMENT '总次数',
    `valid_type`           VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '有效期（年、月、永久）',
    `member_discount`      VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '会员等级折扣（空：不参与；discount：会员折扣；fixed_price：指定会员价）',
    `guarantee_id`         VARCHAR(255)     NOT NULL DEFAULT '' COMMENT '保障服务id',
    PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)次卡商品表';

DROP TABLE IF EXISTS `{{prefix}}home_service_card_order`;
CREATE TABLE `{{prefix}}home_service_card_order`
(
    `order_id`         INT(11) NOT NULL AUTO_INCREMENT COMMENT '订单id',
    `site_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_no`         VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '订单编号',
    `order_from`       VARCHAR(55)  NOT NULL DEFAULT '' COMMENT '订单来源',
    `order_type`       VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '订单类型',
    `card_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '次卡套餐id',
    `out_trade_no`     VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '支付流水号',
    `order_status`     VARCHAR(30)  NOT NULL DEFAULT '' COMMENT '订单状态',
    `refund_status`    VARCHAR(30)  NOT NULL DEFAULT '' COMMENT '退款状态',
    `refund_no`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '退款单号',
    `member_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `ip`               VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '会员ip',
    `member_message`   VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '会员留言信息',
    `order_money`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
    `create_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `pay_time`         INT(11) NOT NULL DEFAULT 0 COMMENT '订单支付时间',
    `close_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '订单关闭时间',
    `auto_close_time`  INT(11) NOT NULL DEFAULT 0 COMMENT '自动关闭时间',
    `delete_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '是否删除(针对后台)',
    `is_enable_refund` INT(11) NOT NULL DEFAULT 0 COMMENT '是否允许退款',
    `remark`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '商家留言',
    `pay_money`        DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
    `order_name`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单名称',
    `original_price`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
    PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)次卡订单表';

DROP TABLE IF EXISTS `{{prefix}}home_service_card_order_item`;
CREATE TABLE `{{prefix}}home_service_card_order_item`
(
    `order_item_id`   INT(11) NOT NULL AUTO_INCREMENT COMMENT '订单项id',
    `order_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '购买会员id',
    `card_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '次卡套餐id',
    `card_sku_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '次卡套餐项id',
    `goods_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '商品项目id',
    `goods_sku_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '商品项目skuid',
    `goods_sku_name`  VARCHAR(400)  NOT NULL DEFAULT '' COMMENT '项目名称',
    `goods_sku_image` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '项目图片',
    `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '项目单价',
    `num`             INT(11) NOT NULL DEFAULT 0 COMMENT '购买数量',
    `item_money`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '项目总价',
    `is_refund`       INT(11) NOT NULL DEFAULT 0 COMMENT '是否退款',
    `refund_no`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '退款编号',
    `refund_status`   INT(11) NOT NULL DEFAULT 0 COMMENT '退款状态',
    `max_use_times`   INT(11) NOT NULL DEFAULT 0 COMMENT '最多可使用次数',
    `original_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
    `sku_unit`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '单位',
    `valid_type`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '有效期（年 月 永久）',
    PRIMARY KEY (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)次卡订单子项表';

DROP TABLE IF EXISTS `{{prefix}}home_service_card_order_log`;
CREATE TABLE `{{prefix}}home_service_card_order_log`
(
    `id`                INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `order_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `site_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `action`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作内容',
    `uid`               INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `nick_name`         VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '操作人名称',
    `order_status`      VARCHAR(10)  NOT NULL DEFAULT '' COMMENT '订单状态(操作后)',
    `action_way`        VARCHAR(10)  NOT NULL DEFAULT '' COMMENT '操作类型(member买家 user卖家 system系统)',
    `order_status_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单状态名称(操作后)',
    `action_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '操作时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单操作记录表';

DROP TABLE IF EXISTS `{{prefix}}home_service_card_sku`;
CREATE TABLE `{{prefix}}home_service_card_sku`
(
    `sku_id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '商品sku_id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `sku_name`        VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '商品sku名称',
    `sku_image`       VARCHAR(2000) NOT NULL DEFAULT '' COMMENT 'sku主图',
    `card_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '商品id',
    `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'sku单价',
    `original_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
    `goods_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '商品项目id',
    `goods_sku_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '商品项目skuid',
    `max_use_times`   INT(11) NOT NULL DEFAULT 0 COMMENT '最多可使用次数',
    `is_take`         INT(11) NOT NULL DEFAULT 0 COMMENT '是否参与',
    `goods_name`      VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '商品名称',
    `sale_num`        INT(11) NOT NULL DEFAULT 0 COMMENT '销量',
    `sku_unit`        VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '单位',
    PRIMARY KEY (`sku_id`),
    KEY `idx_goods_sku_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)次卡项目规格表';

DROP TABLE IF EXISTS `{{prefix}}home_service_card_use_records`;
CREATE TABLE `{{prefix}}home_service_card_use_records` (
  `record_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '使用记录ID',
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` INT(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `order_id` INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
  `member_card_id` INT(11) NOT NULL DEFAULT 0 COMMENT '会员次卡id',
  `member_card_item_id` INT(11) NOT NULL DEFAULT 0 COMMENT '会员次卡项id',
  `card_id` INT(11) NOT NULL DEFAULT 0 COMMENT '卡id',
  `card_sku_id` INT(11) NOT NULL DEFAULT 0 COMMENT '卡项id',
  `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`record_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)次卡使用记录表';

DROP TABLE IF EXISTS `{{prefix}}home_service_cash_out`;
CREATE TABLE `{{prefix}}home_service_cash_out` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点ID',
  `cash_out_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '提现交易号',
  `source` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '来源',
  `related_id` INT NOT NULL DEFAULT 0 COMMENT '相关ID 师傅ID/门店ID',
  `account_type` VARCHAR(255) NOT NULL DEFAULT 'money' COMMENT '提现账户类型',
  `transfer_type` VARCHAR(20) NOT NULL DEFAULT '0' COMMENT '转账提现类型',
  `transfer_realname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人名称',
  `transfer_mobile` VARCHAR(11) NOT NULL DEFAULT '' COMMENT '手机号',
  `transfer_bank` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '银行名称',
  `transfer_account` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '收款账号',
  `transfer_payee` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '转账收款方(json)，主要用于对接在线打款方式',
  `transfer_payment_code` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '收款码图片',
  `transfer_fail_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '失败原因',
  `transfer_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '转账状态',
  `transfer_time` INT NOT NULL DEFAULT 0 COMMENT '转账时间',
  `apply_money` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现申请金额',
  `rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现手续费比率',
  `service_money` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现手续费',
  `money` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现到账金额',
  `status` INT NOT NULL DEFAULT 0 COMMENT '状态 1.待转账 2.已转账 -1已取消',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '申请时间',
  `refuse_reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '拒绝理由',
  `update_time` INT NOT NULL DEFAULT 0 COMMENT '更新时间',
  `transfer_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '转账单号',
  `cancel_time` INT NOT NULL DEFAULT 0 COMMENT '取消时间',
  `final_transfer_type` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '转账方式',
  PRIMARY KEY (`id`),
  KEY `IDX_SITE_ID` (`site_id`),
  KEY `IDX_CASH_OUT_NO` (`cash_out_no`),
  KEY `IDX_CREATE_TIME` (`create_time`),
  KEY `IDX_STATUS` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员提现表';

DROP TABLE IF EXISTS `{{prefix}}home_service_cash_out_account`;
CREATE TABLE `{{prefix}}home_service_cash_out_account` (
  `account_id` INT NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点ID',
  `source` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '来源',
  `related_id` INT NOT NULL DEFAULT 0 COMMENT '相关ID 师傅ID/门店ID',
  `account_type` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '账户类型',
  `bank_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '银行名称',
  `realname` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '真实名称',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` INT NOT NULL DEFAULT 0 COMMENT '修改时间',
  `account_no` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '提现账户',
  `transfer_payment_code` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '收款码',
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员提现账户';

DROP TABLE IF EXISTS `{{prefix}}home_service_city_strategy`;
CREATE TABLE `{{prefix}}home_service_city_strategy` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `province_id` INT NOT NULL DEFAULT 0 COMMENT '父级',
  `city_id` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '名称',
  `way` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '方式',
  `value` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '值',
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点ID',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_province_id` (`province_id`),
  KEY `idx_city_id` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政) 城市策略表';

DROP TABLE IF EXISTS `{{prefix}}home_service_coupon`;
CREATE TABLE `{{prefix}}home_service_coupon`
(
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `title` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
    `start_time` int NOT NULL DEFAULT 0 COMMENT '活动开启时间',
    `end_time` int NOT NULL DEFAULT 0 COMMENT '活动结束时间',
    `remain_count` int NOT NULL DEFAULT 0 COMMENT '剩余数量',
    `receive_count` int NOT NULL DEFAULT 0 COMMENT '已领取数量',
    `give_count` int NOT NULL DEFAULT 0 COMMENT '已发放数量',
    `limit_count` int NOT NULL DEFAULT 0 COMMENT '单个会员限制领取数量',
    `status` tinyint NOT NULL DEFAULT 1 COMMENT ' 状态 1 正常 2 未开启 3 已无效',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '添加时间',
    `price` decimal(10, 2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT '面值',
    `min_condition_money` decimal(10, 2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT '商品最低多少金额可用优惠券',
    `type` tinyint NOT NULL DEFAULT 0 COMMENT '优惠券类型 1通用优惠券 2商品品类优惠券 3商品优惠券',
    `receive_type` int NOT NULL DEFAULT 0 COMMENT '领取方式',
    `valid_type` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '有效时间',
    `length` int NOT NULL DEFAULT 0 COMMENT '有效期时长(天)',
    `valid_start_time` int NOT NULL DEFAULT 0 COMMENT '有效期开始时间',
    `valid_end_time` int NOT NULL DEFAULT 0 COMMENT '有效期结束时间',
    `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
    `receive_status` tinyint NOT NULL DEFAULT 1 COMMENT ' 状态 1 正常 2 关闭',
    PRIMARY KEY (`id`),
    KEY `status` (`status`),
    KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)优惠券表';

DROP TABLE IF EXISTS `{{prefix}}home_service_coupon_goods`;
CREATE TABLE `{{prefix}}home_service_coupon_goods`
(
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `coupon_id` int NOT NULL DEFAULT 0 COMMENT '优惠券模板id',
    `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品id',
    `category_id` int NOT NULL DEFAULT 0 COMMENT '分类id',
    PRIMARY KEY (`id`),
    KEY `index_category_id` (`category_id`),
    KEY `index_coupon_id` (`coupon_id`),
    KEY `index_goods_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)优惠券商品或品类关联表';

DROP TABLE IF EXISTS `{{prefix}}home_service_coupon_member`;
CREATE TABLE `{{prefix}}home_service_coupon_member`
(
    `id` int NOT NULL AUTO_INCREMENT COMMENT '优惠券发放记录id',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `coupon_id` int NOT NULL DEFAULT 0 COMMENT '优惠券id',
    `member_id` int NOT NULL DEFAULT 0 COMMENT '会员id',
    `create_time` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '领取时间',
    `expire_time` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '过期时间',
    `use_time` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用时间',
    `type` varchar(32) NOT NULL DEFAULT '' COMMENT '优惠券类型',
    `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态',
    `title` varchar(255) NOT NULL DEFAULT '' COMMENT '优惠券名称',
    `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '面值',
    `min_condition_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '最低使用门槛',
    `receive_type` varchar(255) NOT NULL DEFAULT '' COMMENT '领取方式',
    `trade_id` int NOT NULL DEFAULT 0 COMMENT '关联业务id',
    PRIMARY KEY (`id`),
    KEY `coupon_id` (`coupon_id`),
    KEY `member_id` (`member_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)优惠券会员领取记录表';

DROP TABLE IF EXISTS `{{prefix}}home_service_coupon_send_records`;
CREATE TABLE `{{prefix}}home_service_coupon_send_records`
(
    `id` int NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `coupon_id` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '优惠券id',
    `send_num` int NOT NULL DEFAULT 0 COMMENT '每位会员发放数量',
    `range_type` varchar(20) NOT NULL DEFAULT '' COMMENT '发券范围',
    `range_param` text DEFAULT NULL COMMENT '范围对应参数',
    `success_num` int NOT NULL DEFAULT 0 COMMENT '发放成功数',
    `status` varchar(255) NOT NULL DEFAULT '' COMMENT '状态 wait-待发送 process-发送中 finish-结束',
    `member_num` int NOT NULL DEFAULT 0 COMMENT '发放会员数',
    `end_time` int NOT NULL DEFAULT 0 COMMENT '发放结束时间',
    `admin_uid` int NOT NULL DEFAULT 0 COMMENT '操作人id',
    `admin_username` varchar(255) NOT NULL DEFAULT '' COMMENT '操作人名称',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)优惠券发券记录表';

DROP TABLE IF EXISTS `{{prefix}}home_service_feedback`;
CREATE TABLE `{{prefix}}home_service_feedback`
(
    `feedback_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `source` varchar(50) NOT NULL DEFAULT '' COMMENT '来源',
    `title` varchar(255) NOT NULL DEFAULT '' COMMENT '反馈标题',
    `images` text DEFAULT NULL COMMENT '图片',
    `content` text DEFAULT NULL COMMENT '内容',
    `related_id` int NOT NULL DEFAULT 0 COMMENT '相关id',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '修改时间',
    PRIMARY KEY (`feedback_id`),
    KEY `index_coupon_id` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)反馈表';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods`;
CREATE TABLE `{{prefix}}home_service_goods`
(
    `goods_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '商品id',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `goods_subtitle` varchar(300) DEFAULT NULL COMMENT '副标题',
    `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
    `goods_cover` varchar(2000) NOT NULL DEFAULT '' COMMENT '商品封面',
    `goods_image` text DEFAULT NULL COMMENT '商品图片',
    `buy_info` text DEFAULT NULL COMMENT '购买须知',
    `goods_content` text DEFAULT NULL COMMENT '商品详情',
    `goods_category` varchar(255) NOT NULL DEFAULT '0' COMMENT '商品分类',
    `status` tinyint NOT NULL DEFAULT 1 COMMENT '商品状态（1.正常0下架）',
    `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
    `sale_num` int NOT NULL DEFAULT 0 COMMENT '销量（总销量）',
    `virtually_sale` int NOT NULL DEFAULT 0 COMMENT '虚拟销量',
    `buy_type` varchar(255) NOT NULL DEFAULT '' COMMENT '购买类型 reservation-预约 buy-购买',
    `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '售卖价格(最低,查询)',
    `after_sales` int NOT NULL DEFAULT 0 COMMENT '售后服务1-支持',
    `price_list` text DEFAULT NULL COMMENT '价目表',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '修改时间',
    `delete_time` int NOT NULL DEFAULT 0 COMMENT '删除时间',
    `is_delete` int NOT NULL DEFAULT 0 COMMENT '是否删除',
    `poster_id` int NOT NULL DEFAULT 0 COMMENT '海报id',
    `member_discount` varchar(255) NOT NULL DEFAULT '' COMMENT '会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price',
    `is_force_clock_in` int NOT NULL DEFAULT 0 COMMENT '强制拍照',
    `is_force_departure` int NOT NULL DEFAULT 0 COMMENT '强制服务开始',
    `grab_orders` int NOT NULL DEFAULT 0 COMMENT '抢单模式  1开启 0 不开启',
    `additional_manage` text NOT NULL COMMENT '增项管理',
    `evaluate_num` int NOT NULL DEFAULT 0 COMMENT '评论数量',
    `top_category` int UNSIGNED NOT NULL DEFAULT 0 COMMENT '顶级分累id',
    `is_finish_photograph` int NOT NULL DEFAULT 1 COMMENT '服务完成拍照',
    `guarantee_id` varchar(1000) NOT NULL DEFAULT '' COMMENT '保障id',
    PRIMARY KEY (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)项目表';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods_browse`;
CREATE TABLE `{{prefix}}home_service_goods_browse`
(
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_id` int NOT NULL DEFAULT 0,
    `member_id` int NOT NULL DEFAULT 0 COMMENT '浏览人',
    `sku_id` int NOT NULL DEFAULT 0 COMMENT 'sku_id',
    `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品id',
    `browse_time` int NOT NULL DEFAULT 0 COMMENT '浏览时间',
    `goods_cover` varchar(2000) NOT NULL DEFAULT '' COMMENT '商品图片',
    `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品浏览历史';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods_category`;
CREATE TABLE `{{prefix}}home_service_goods_category`
(
    `category_id` int NOT NULL AUTO_INCREMENT,
    `site_id` int NOT NULL DEFAULT 0,
    `category_name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
    `pid` int NOT NULL DEFAULT 0 COMMENT '分类上级',
    `level` int NOT NULL DEFAULT 0 COMMENT '层级',
    `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
    `image` varchar(255) NOT NULL DEFAULT '' COMMENT '分类图片',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '添加时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
    `is_show` int DEFAULT 1 COMMENT '是否展示',
    `is_settled` int DEFAULT 0 COMMENT '是否支持入驻',
    `intro` varchar(1000) NOT NULL DEFAULT '' COMMENT '简介',
    `adv_image` varchar(1200) NOT NULL DEFAULT '' COMMENT '广告位图片',
    `errand_business` int DEFAULT 0 COMMENT '是否跑腿服务 0是上门服务 1是跑腿服务',
    PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)商品分类';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods_collect`;
CREATE TABLE `{{prefix}}home_service_goods_collect`
(
    `id` int NOT NULL AUTO_INCREMENT,
    `member_id` int NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品id',
    `create_time` int NOT NULL DEFAULT 0,
    `type` varchar(255) NOT NULL DEFAULT '' COMMENT '收藏类型',
    PRIMARY KEY (`id`),
    KEY `IDX_member_collect_goods` (`goods_id`),
    KEY `IDX_member_collect_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)会员收藏表';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods_evaluate`;
CREATE TABLE `{{prefix}}home_service_goods_evaluate`
(
    `evaluate_id` int NOT NULL AUTO_INCREMENT,
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id` int NOT NULL DEFAULT 0 COMMENT '订单id',
    `store_id` int NOT NULL DEFAULT 0 COMMENT '门店id',
    `technician_id` int NOT NULL DEFAULT 0 COMMENT '师傅id',
    `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID',
    `member_id` int NOT NULL DEFAULT 0 COMMENT '会员ID',
    `member_head` varchar(255) NOT NULL DEFAULT '' COMMENT '会员头像',
    `member_name` varchar(100) NOT NULL DEFAULT '' COMMENT '会员名称',
    `content` varchar(3000) NOT NULL DEFAULT '' COMMENT '评价内容',
    `images` varchar(3000) NOT NULL DEFAULT '' COMMENT '评价图片',
    `is_anonymous` tinyint NOT NULL DEFAULT 1 COMMENT '1匿名  2不匿名',
    `scores` tinyint NOT NULL DEFAULT 1 COMMENT '评论分数 1-5',
    `auto_adopt_time` int NOT NULL DEFAULT 0 COMMENT '自动审核通过时间',
    `is_audit` tinyint NOT NULL DEFAULT 1 COMMENT '审核状态 1待审 2通过 3拒绝',
    `explain_first` varchar(3000) NOT NULL DEFAULT '' COMMENT '解释内容',
    `evaluate_num` int NOT NULL DEFAULT 0 COMMENT '评论数量',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '评论时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '修改时间',
    PRIMARY KEY (`evaluate_id`),
    KEY `idx_goods_evaluate_create_time` (`create_time`),
    KEY `idx_goods_evaluate_goods_id` (`goods_id`),
    KEY `idx_goods_evaluate_is_anonymous` (`is_anonymous`),
    KEY `idx_goods_evaluate_is_audit` (`is_audit`),
    KEY `idx_goods_evaluate_member_id` (`member_id`),
    KEY `idx_goods_evaluate_order_id` (`order_id`),
    KEY `idx_goods_evaluate_scores` (`scores`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)商品评价表';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods_guarantee`;
CREATE TABLE `{{prefix}}home_service_goods_guarantee`
(
    `id` bigint NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id` int NOT NULL DEFAULT 0,
    `guarantee_title` varchar(255) NOT NULL COMMENT '保障标题',
    `guarantee_content` text NOT NULL COMMENT '保障内容',
    `guarantee_image` varchar(2000) NOT NULL DEFAULT '' COMMENT '保障图片',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '编辑时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4  COMMENT='商品类家庭服务保障表';

DROP TABLE IF EXISTS `{{prefix}}home_service_goods_sku`;
CREATE TABLE `{{prefix}}home_service_goods_sku`
(
    `sku_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '商品sku_id',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `sku_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品sku名称',
    `sku_image` varchar(2000) NOT NULL DEFAULT '' COMMENT 'sku主图',
    `sku_no` varchar(255) NOT NULL DEFAULT '' COMMENT '商品sku编码',
    `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品id',
    `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'sku单价',
    `market_price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '划线价',
    `sale_num` int NOT NULL DEFAULT 0 COMMENT '销量',
    `sku_unit` varchar(50) NOT NULL DEFAULT '' COMMENT 'sku单位名称',
    `min_buy` int NOT NULL DEFAULT 0 COMMENT '最小服务量',
    `is_default` tinyint NOT NULL DEFAULT 0 COMMENT '是否默认',
    `member_price` text DEFAULT NULL COMMENT '会员价，json格式，指定会员价，数据结构为：{"level_1":"10.00","level_2":"10.00"}',
    PRIMARY KEY (`sku_id`),
    KEY `idx_goods_sku_is_default` (`is_default`),
    KEY `idx_goods_sku_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)项目规格表';

DROP TABLE IF EXISTS `{{prefix}}home_service_help`;
CREATE TABLE `{{prefix}}home_service_help`
(
    `help_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
    `name` varchar(255) NOT NULL DEFAULT '' COMMENT '帮助名称',
    `type` varchar(50) NOT NULL DEFAULT '' COMMENT '类型',
    `category_id` int NOT NULL DEFAULT 0 COMMENT '分类id',
    `content` text DEFAULT NULL COMMENT '内容',
    `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
    `is_show` int NOT NULL DEFAULT 1 COMMENT '是否展示',
    `views_count` int NOT NULL DEFAULT 0 COMMENT '浏览量',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '修改时间',
    PRIMARY KEY (`help_id`),
    KEY `index_category_id` (`category_id`),
    KEY `index_coupon_id` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)帮助表';

DROP TABLE IF EXISTS `{{prefix}}home_service_help_category`;
CREATE TABLE `{{prefix}}home_service_help_category`
(
    `category_id` int NOT NULL AUTO_INCREMENT,
    `site_id` int NOT NULL DEFAULT 0,
    `category_name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
    `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
    `is_show` int NOT NULL DEFAULT 1 COMMENT '是否展示',
    `help_count` int NOT NULL DEFAULT 0 COMMENT '帮助数量',
    `create_time` int NOT NULL DEFAULT 0 COMMENT '添加时间',
    `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
    `is_default` int NOT NULL DEFAULT 0 COMMENT '默认',
    `is_builtin_data` tinyint NOT NULL DEFAULT 0 COMMENT '是否内置数据 1=是，0=否',
    PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)帮助分类';

DROP TABLE IF EXISTS `{{prefix}}home_service_hour_stat`;
CREATE TABLE `{{prefix}}home_service_hour_stat`
(
    `id` int NOT NULL AUTO_INCREMENT,
    `site_id` int NOT NULL DEFAULT 0,
    `date` varchar(20) NOT NULL DEFAULT '' COMMENT '年月日',
    `hour` int NOT NULL DEFAULT 0 COMMENT '时',
    `date_time` int NOT NULL DEFAULT 0 COMMENT '时间戳',
    `order_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '收款金额',
    `order_num` int NOT NULL DEFAULT 0 COMMENT '订单数',
    `refund_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '退款金额',
    `refund_num` int NOT NULL DEFAULT 0 COMMENT '退款数量',
    `item_order_num` int NOT NULL DEFAULT 0 COMMENT '报单数',
    `item_order_money` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '报单金额',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)时统计表';

DROP TABLE IF EXISTS `{{prefix}}home_service_invoice`;
CREATE TABLE `{{prefix}}home_service_invoice` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT '发票ID',
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` INT NOT NULL DEFAULT 0 COMMENT '会员ID',
  `order_ids` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '业务ID',
  `header_type` VARCHAR(50) NOT NULL DEFAULT '1' COMMENT '抬头类型',
  `header_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '名称（发票抬头）',
  `type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发票类型',
  `content` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发票内容',
  `tax_number` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '公司税号',
  `mobile` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '开票人手机号',
  `email` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '开票人邮箱',
  `telephone` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '注册电话',
  `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '注册地址',
  `bank_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '开户银行',
  `bank_card_number` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '银行账号',
  `money` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '开票金额',
  `invoice_number` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '发票代码',
  `invoice_voucher` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '发票凭证',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '申请时间',
  `invoice_time` INT NOT NULL DEFAULT 0 COMMENT '开票时间',
  `status` INT NOT NULL DEFAULT 0 COMMENT '开票状态 0:待开具 1:已开具',
  `order_money` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)发票表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_member_card
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_member_card`;
CREATE TABLE `{{prefix}}home_service_member_card` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` INT NOT NULL DEFAULT 0 COMMENT '会员ID',
  `card_id` INT NOT NULL DEFAULT 0 COMMENT '次卡套餐ID',
  `card_no` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '卡号',
  `order_id` INT NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `total_num` INT NOT NULL DEFAULT 0 COMMENT '卡项总次数',
  `total_use_num` INT NOT NULL DEFAULT 0 COMMENT '卡项使用次数',
  `status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '状态',
  `create_time` INT NOT NULL DEFAULT 0 COMMENT '创建时间',
  `expire_time` INT NOT NULL DEFAULT 0 COMMENT '到期时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)会员次卡表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_member_card_item
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_member_card_item`;
CREATE TABLE `{{prefix}}home_service_member_card_item` (
  `item_id` INT NOT NULL AUTO_INCREMENT,
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_card_id` INT NOT NULL DEFAULT 0 COMMENT '会员次卡ID',
  `card_id` INT NOT NULL DEFAULT 0 COMMENT '次卡套餐ID',
  `card_sku_id` INT NOT NULL DEFAULT 0 COMMENT '次卡套餐项ID',
  `member_id` INT NOT NULL DEFAULT 0 COMMENT '会员ID',
  `goods_id` INT NOT NULL DEFAULT 0 COMMENT '项目ID',
  `goods_sku_id` INT NOT NULL DEFAULT 0 COMMENT '项目SKU ID',
  `num` INT NOT NULL DEFAULT 0 COMMENT '商品次数/数量',
  `use_num` INT NOT NULL DEFAULT 0 COMMENT '使用次数/数量',
  `sku_unit` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '单位',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '项目单价',
  `original_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
  `expire_time` INT NOT NULL DEFAULT 0 COMMENT '有效期',
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)会员次卡卡项表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_notice
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_notice`;
CREATE TABLE `{{prefix}}home_service_notice`
(
    `notice_id`       INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `user_source`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '来源 technician 师傅  member 会员',
    `notice_source`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '消息来源 system 系统  notice 通知',
    `type`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '类型',
    `uid`             INT(11) NOT NULL DEFAULT 0 COMMENT '会员id  / 师傅id',
    `title`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '消息标题',
    `content`         VARCHAR(400) NOT NULL DEFAULT '' COMMENT '消息内容',
    `order_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `unread_count`    INT(11) NOT NULL DEFAULT 0 COMMENT '未读数',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`notice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='（上门家政）通知消息表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order`;
CREATE TABLE `{{prefix}}home_service_order`
(
    `order_id`                  INT(11) NOT NULL AUTO_INCREMENT COMMENT '订单id',
    `site_id`                   INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_no`                  VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单编号',
    `order_from`                VARCHAR(55) NOT NULL DEFAULT '' COMMENT '订单来源',
    `order_type`                VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单类型',
    `out_trade_no`              VARCHAR(50) NOT NULL DEFAULT '' COMMENT '支付流水号',
    `order_status`              VARCHAR(30) NOT NULL DEFAULT '' COMMENT '订单状态',
    `refund_status`             VARCHAR(30) NOT NULL DEFAULT '' COMMENT '退款状态',
    `refund_apply_time`         INT(11) NOT NULL DEFAULT 0 COMMENT '退款申请时间',
    `member_id`                 INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `ip`                        VARCHAR(30) NOT NULL DEFAULT '' COMMENT '会员ip',
    `member_message`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '会员留言信息',
    `order_money`               DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
    `discount_money`            DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `create_time`               INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `pay_time`                  INT(11) NOT NULL DEFAULT 0 COMMENT '订单支付时间',
    `dispatch_time`             INT(11) NOT NULL DEFAULT 0 COMMENT '派单时间',
    `service_time`              INT(11) NOT NULL DEFAULT 0 COMMENT '服务时间',
    `close_time`                INT(11) NOT NULL DEFAULT 0 COMMENT '订单关闭时间',
    `finish_time`               INT(11) NOT NULL DEFAULT 0 COMMENT '订单完成时间',
    `auto_close_time`           INT(11) NOT NULL DEFAULT 0 COMMENT '自动关闭时间',
    `delete_time`               INT(11) NOT NULL DEFAULT 0 COMMENT '是否删除(针对后台)',
    `is_enable_refund`          INT(11) NOT NULL DEFAULT 0 COMMENT '是否允许退款',
    `remark`                    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '商家留言',
    `pay_money`                 DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
    `order_name`                VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单名称',
    `is_evaluate`               INT(11) NOT NULL DEFAULT 0 COMMENT '是否评论',
    `reserve_service_time`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '客户希望服务时间',
    `reserve_service_time_stamp` INT(11) NOT NULL DEFAULT 0 COMMENT '客户希望服务时间时间戳',
    `technician_id`             INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `taker_name`                VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收货人',
    `taker_mobile`              VARCHAR(50) NOT NULL DEFAULT '' COMMENT '下单人手机号',
    `taker_province`            INT(11) NOT NULL DEFAULT 0 COMMENT '待服务省',
    `taker_city`                INT(11) NOT NULL DEFAULT 0 COMMENT '服务市',
    `taker_district`            INT(11) NOT NULL DEFAULT 0 COMMENT '服务区县',
    `taker_address`             VARCHAR(255) NOT NULL DEFAULT '' COMMENT '服务地址',
    `taker_full_address`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '服务详细地址',
    `taker_longitude`           VARCHAR(50) NOT NULL DEFAULT '' COMMENT '服务地址经度',
    `taker_latitude`            VARCHAR(50) NOT NULL DEFAULT '' COMMENT '服务详细纬度',
    `check_code`                VARCHAR(20) NOT NULL DEFAULT '' COMMENT '上门校验码',
    `take_photos`               LONGTEXT NULL DEFAULT NULL COMMENT '打卡图片',
    `take_photos_time`          INT(11) NOT NULL DEFAULT 0 COMMENT '打卡图片时间',
    `depart_lat_lng`            VARCHAR(200) NOT NULL DEFAULT '' COMMENT '出发经纬度',
    `depart_time`               INT(11) NOT NULL DEFAULT 0 COMMENT '出发时间',
    `is_grab`                   INT(11) NOT NULL DEFAULT 0 COMMENT '是否抢单',
    `buy_type`                  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '预约 和 一口价标识',
    `sub_status`                VARCHAR(255) NOT NULL DEFAULT '' COMMENT '子状态',
    `store_id`                  INT(11) NOT NULL DEFAULT 0 COMMENT '门店',
    `dispatch_timeout_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '派单超时时间',
    `auto_check_time`           INT(11) NOT NULL DEFAULT 0 COMMENT '自动验证的时间',
    `store_ratio`               DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店佣金比例',
    `store_commission`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店佣金',
    `store_additional_commission` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店附加佣金',
    `technician_ratio`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '师傅佣金比例',
    `technician_commission`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '师傅佣金',
    `technician_additional_commission` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '师傅附加佣金',
    `is_card_order`             INT(11) NOT NULL DEFAULT 0 COMMENT '是否是次卡订单0:否  1:是',
    `is_auto_refund`            INT(11) NOT NULL DEFAULT 0 COMMENT '是否自动退款0:否  1:是',
    `is_abnormal`               INT(11) NOT NULL DEFAULT 0 COMMENT '是否异常订单0:否  1:是',
    `category_id`               INT(11) NOT NULL DEFAULT 0 COMMENT '服务类型',
    `check_photos`              VARCHAR(4000) NOT NULL DEFAULT '' COMMENT '服务验收图片',
    `auto_refund_time`          INT(11) NOT NULL DEFAULT 0 COMMENT '售后自动通过时间',
    `is_issue_invoice`          INT(11) NOT NULL DEFAULT 0 COMMENT '是否开具发票0:否  1:是',
    `is_settlement`             INT(11) NOT NULL DEFAULT 0 COMMENT '是否结算',
    `service_finish_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '服务完成时间',
    `label_id`                  INT(11) NOT NULL DEFAULT 0 COMMENT '标签id',
    `follow_id`                 INT(11) NOT NULL DEFAULT 0 COMMENT '回访id',
    PRIMARY KEY (`order_id`),
    KEY `idx_site_id` (`site_id`),
    KEY `idx_order_status` (`order_status`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_technician_id` (`technician_id`),
    KEY `idx_store_id` (`store_id`),
    KEY `idx_order_no` (`order_no`),
    KEY `idx_out_trade_no` (`out_trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_discount
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_discount`;
CREATE TABLE `{{prefix}}home_service_order_discount`
(
    `id`              INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `order_goods_ids` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '参与的订单商品项',
    `type`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '类型 discount 优惠，gift 赠送',
    `num`             INT(11) NOT NULL DEFAULT 0 COMMENT '使用数量',
    `money`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `discount_type`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '优惠类型',
    `discount_type_id` INT(11) NOT NULL DEFAULT 0 COMMENT '优惠类型id',
    `content`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单优惠说明',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `status`          INT(11) NOT NULL DEFAULT 1 COMMENT '状态',
    `member_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `goods_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '商品id',
    `sku_id`          INT(11) NOT NULL DEFAULT 0 COMMENT 'sku_id',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单优惠表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_follow
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_follow`;
CREATE TABLE `{{prefix}}home_service_order_follow`
(
    `follow_id`        INT(11) NOT NULL AUTO_INCREMENT COMMENT '回访id',
    `site_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`         VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单id',
    `follow_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '回访时间',
    `fee_situation`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT '收费情况',
    `result_feedback`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '结果反馈',
    `satisfaction_score` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '满意度',
    `follow_staff`     INT(11) NOT NULL DEFAULT 0 COMMENT '回访人员',
    `follow_summary`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '回访概要',
    `suggestion_content` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '客户建议',
    PRIMARY KEY (`follow_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单回访表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_item
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_item`;
CREATE TABLE `{{prefix}}home_service_order_item`
(
    `order_item_id`            INT(11) NOT NULL AUTO_INCREMENT COMMENT '订单项id',
    `order_id`                 INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `site_id`                  INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`                INT(11) NOT NULL DEFAULT 0 COMMENT '购买会员id',
    `item_id`                  INT(11) NOT NULL DEFAULT 0 COMMENT '项目id',
    `item_type`                VARCHAR(255) NOT NULL DEFAULT '' COMMENT '项目类型reservation-预约 buy-一口价 custom-自定义',
    `item_name`                VARCHAR(400) NOT NULL DEFAULT '' COMMENT '项目名称',
    `item_image`               VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '项目图片',
    `unit`                     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '单位',
    `price`                    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '项目单价',
    `num`                      INT(11) NOT NULL DEFAULT 0 COMMENT '购买数量',
    `item_money`               DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '项目总价',
    `discount_money`           DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `is_refund`                INT(11) NOT NULL DEFAULT 0 COMMENT '是否退款',
    `refund_no`                VARCHAR(255) NOT NULL DEFAULT '' COMMENT '退款编号',
    `refund_status`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '退款状态',
    `out_trade_no`             VARCHAR(50) NOT NULL DEFAULT '' COMMENT '支付流水号',
    `pay_time`                 INT(11) NOT NULL DEFAULT 0 COMMENT '支付时间',
    `technician_id`            INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `is_enable_refund`         INT(11) NOT NULL DEFAULT 0 COMMENT '是否允许退款',
    `goods_id`                 INT(11) NOT NULL DEFAULT 0 COMMENT '商品id',
    `item_images`              VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '项目报单图片',
    `is_force_clock_in`        INT(11) NOT NULL DEFAULT 0 COMMENT '强制拍照',
    `is_force_departure`       INT(11) NOT NULL DEFAULT 0 COMMENT '强制出发',
    `batch_id`                 INT(11) NOT NULL DEFAULT 0 COMMENT '批次id',
    `is_pay`                   INT(11) NOT NULL DEFAULT 0 COMMENT '是否支付0未支付 1已支付',
    `order_itme_commission_ratio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '增项佣金比例',
    `store_ratio`              DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店佣金比例',
    `store_commission`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店佣金',
    `technician_ratio`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '师傅佣金比例',
    `technician_commission`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '师傅佣金',
    `is_finish_photograph`     INT(11) NOT NULL DEFAULT 0 COMMENT '完成拍照',
    `is_service_fee`           TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否服务费0:否  1:是',
    `create_time`              INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `sku_name`                 VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'sku名称',
    PRIMARY KEY (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单商品表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_label
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_label`;
CREATE TABLE `{{prefix}}home_service_order_label`
(
    `label_id`        INT(11) NOT NULL AUTO_INCREMENT COMMENT '标签id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `label_name`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '标签名称',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `label_color`     VARCHAR(120) NOT NULL DEFAULT '' COMMENT '标签颜色',
    PRIMARY KEY (`label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单标签表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_log`;
CREATE TABLE `{{prefix}}home_service_order_log`
(
    `id`                 INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `order_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `site_id`            INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `action`             VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作内容',
    `uid`                INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `nick_name`          VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作人名称',
    `order_status`       VARCHAR(50) NOT NULL DEFAULT '' COMMENT '订单状态，操作后',
    `action_way`         VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作类型 member 买家 user卖家  system系统任务',
    `order_status_name`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单状态名称，操作后',
    `action_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '操作时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单操作记录表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_refund
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_refund`;
CREATE TABLE `{{prefix}}home_service_order_refund`
(
    `refund_id`        INT(11) NOT NULL AUTO_INCREMENT COMMENT '退款id',
    `order_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `refund_no`        VARCHAR(255) NOT NULL DEFAULT '0' COMMENT '退款单号',
    `site_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `money`            DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '总退款',
    `status`           VARCHAR(30) NOT NULL DEFAULT '0' COMMENT '退款状态',
    `create_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `audit_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '审核时间',
    `transfer_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '转账时间',
    `refuse_reason`    VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '拒绝原因',
    `source`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '来源 system 系统 member 会员',
    `reason`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '退款原因 ',
    `apply_money`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '申请退款',
    `remark`           VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '描述',
    `voucher`          VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '凭证',
    `technician_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `store_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '门店',
    PRIMARY KEY (`refund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单退款表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_order_refund_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_order_refund_log`;
CREATE TABLE `{{prefix}}home_service_order_refund_log`
(
    `id`             INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `refund_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '退款id',
    `site_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `action`         VARCHAR(2000) NOT NULL DEFAULT '0' COMMENT '操作内容',
    `action_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `uid`            INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `action_way`     VARCHAR(30) NOT NULL DEFAULT '' COMMENT '操作类型 member 买家 use 卖家 system 系统',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)订单维权日志表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_stat
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_stat`;
CREATE TABLE `{{prefix}}home_service_stat`
(
    `id`              INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `date`            VARCHAR(30) NOT NULL DEFAULT '' COMMENT '年月日',
    `date_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '时间戳',
    `order_money`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '收款金额',
    `order_num`       INT(11) NOT NULL DEFAULT 0 COMMENT '订单数',
    `refund_money`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '退款金额',
    `refund_num`      INT(11) NOT NULL DEFAULT 0 COMMENT '退款数量',
    `item_order_num`  INT(11) NOT NULL DEFAULT 0 COMMENT '报单数',
    `item_order_money` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '报单金额',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)统计表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_store
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_store`;
CREATE TABLE `{{prefix}}home_service_store`
(
    `store_id`         INT(11) NOT NULL AUTO_INCREMENT COMMENT '门店id',
    `site_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `store_name`       VARCHAR(50) NOT NULL DEFAULT '' COMMENT '门店名称',
    `contact_name`     VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人名称',
    `mobile`           VARCHAR(50) NOT NULL DEFAULT '' COMMENT '手机号',
    `order_num`        INT(11) NOT NULL DEFAULT 0 COMMENT '订单数',
    `achievement`      INT(11) NOT NULL DEFAULT 0 COMMENT '订单业绩',
    `service_time`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '服务小时',
    `create_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `headimg`          VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
    `province_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '省份id',
    `city_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '城市id',
    `district_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '区县id',
    `full_address`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址',
    `lng`              VARCHAR(255) NOT NULL DEFAULT '' COMMENT '经度',
    `lat`              VARCHAR(255) NOT NULL DEFAULT '' COMMENT '纬度',
    `source`           VARCHAR(255) NOT NULL DEFAULT 'internal' COMMENT '来源',
    `service_ratio`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '服务比例',
    `id_card_front`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '身份证正面照片URL',
    `id_card_back`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '身份证反面照片URL',
    `id_number`        VARCHAR(20) NOT NULL DEFAULT '' COMMENT '身份证号码',
    `license_img`      TEXT NOT NULL COMMENT '营业执照照片URL',
    `is_default`       INT(11) NOT NULL DEFAULT 0 COMMENT '默认',
    `commission`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '佣金',
    `commission_get`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '累计佣金',
    `evaluate_avg_scores` DECIMAL(10,2) NOT NULL DEFAULT 5.00 COMMENT '评价星',
    `business_hours`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '营业时间',
    PRIMARY KEY (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)门店表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_store_account
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_store_account`;
CREATE TABLE `{{prefix}}home_service_store_account`
(
    `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `store_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '门店id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `account_type`    VARCHAR(255) NOT NULL DEFAULT 'point' COMMENT '账户类型',
    `account_data`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户数据',
    `account_sum`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '变动后的账户余额',
    `from_type`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '来源类型',
    `related_id`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '关联Id',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `memo`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注信息',
    `status`          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '结算状态 0:未结算 1:已结算',
    `payment_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '到账时间',
    `category_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '服务类型',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)门店账单表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_store_application
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_store_application`;
CREATE TABLE `{{prefix}}home_service_store_application`
(
    `id`                INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `site_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `store_name`        VARCHAR(50) NOT NULL DEFAULT '' COMMENT '门店名称',
    `contact_name`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '联系人名称',
    `headimg`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
    `id_card_front`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '身份证正面照片URL',
    `id_card_back`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '身份证反面照片URL',
    `id_number`         VARCHAR(20) NOT NULL DEFAULT '' COMMENT '身份证号码',
    `mobile`            VARCHAR(20) NOT NULL DEFAULT '' COMMENT '手机号码',
    `license_img`       TEXT NOT NULL COMMENT '营业执照照片URL',
    `apply_desc`        TEXT COMMENT '申请说明',
    `province_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '省份ID',
    `city_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '城市ID',
    `district_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '区/县ID',
    `full_address`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址',
    `lng`               VARCHAR(50) NOT NULL DEFAULT '' COMMENT '经度',
    `lat`               VARCHAR(50) NOT NULL DEFAULT '' COMMENT '纬度',
    `audit_status`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '审核状态：0-待审核 1-通过  -1-拒绝',
    `audit_user_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '审核人ID',
    `audit_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '审核时间',
    `audit_remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '审核备注（拒绝原因等）',
    `create_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `source`            VARCHAR(50) NOT NULL DEFAULT '' COMMENT '来源 member 用户  store 门店',
    PRIMARY KEY (`id`),
    KEY `idx_mobile` (`mobile`),
    KEY `idx_id_number` (`id_number`),
    KEY `idx_audit_status` (`audit_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)门店入驻申请表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_store_technician
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_store_technician`;
CREATE TABLE `{{prefix}}home_service_store_technician`
(
    `id`                  INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`             INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `technician_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `store_id`            INT(11) NOT NULL DEFAULT 0 COMMENT '门店id',
    `order_rate`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单佣金比例',
    `create_time`         INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`         INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
    `status`              INT(11) NOT NULL DEFAULT 0 COMMENT '状态',
    `order_num`           INT(11) NOT NULL DEFAULT 0 COMMENT '订单数',
    `evaluate_avg_scores` DECIMAL(10,2) NOT NULL DEFAULT 5.00 COMMENT '评价平均分数',
    `positive_rate`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '好评率',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)门店师傅表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_technician
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_technician`;
CREATE TABLE `{{prefix}}home_service_technician`
(
    `id`                  INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`             INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `real_name`           VARCHAR(50) NOT NULL DEFAULT '' COMMENT '姓名',
    `mobile`              VARCHAR(50) NOT NULL DEFAULT '' COMMENT '手机号',
    `status`              VARCHAR(50) NOT NULL DEFAULT '1' COMMENT '状态1-正常  -1离职',
    `order_num`           INT(11) NOT NULL DEFAULT 0 COMMENT '订单数',
    `achievement`         INT(11) NOT NULL DEFAULT 0 COMMENT '订单业绩',
    `service_time`        DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '服务小时',
    `create_time`         INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `headimg`             VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
    `certificate`         TEXT COMMENT '证件照',
    `province_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '省份id',
    `city_id`             INT(11) NOT NULL DEFAULT 0 COMMENT '城市id',
    `district_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '区县id',
    `full_address`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址',
    `lng`                 VARCHAR(255) NOT NULL DEFAULT '' COMMENT '经度',
    `lat`                 VARCHAR(255) NOT NULL DEFAULT '' COMMENT '纬度',
    `store_id`            INT(11) NOT NULL DEFAULT 0 COMMENT '门店id',
    `category_id`         VARCHAR(300) NOT NULL DEFAULT '' COMMENT '类目id',
    `level_id`            INT(11) NOT NULL DEFAULT 0 COMMENT '师傅等级',
    `source`              VARCHAR(255) NOT NULL DEFAULT 'internal' COMMENT '来源',
    `order_rate`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单佣金比例',
    `distribute_type`     VARCHAR(200) NOT NULL DEFAULT 'default' COMMENT '比率方式',
    `commission`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '佣金',
    `commission_get`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '累计佣金',
    `evaluate_avg_scores` DECIMAL(10,2) NOT NULL DEFAULT 5.00 COMMENT '评价平均分',
    `withdraw_get`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '累计提现',
    `intro`               VARCHAR(500) NOT NULL DEFAULT '' COMMENT '简介',
    `positive_rate`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '好评率',
    `update_time`         INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
    PRIMARY KEY (`id`),
    KEY `mobile` (`mobile`),
    KEY `real_name` (`real_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)师傅表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_technician_account
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_technician_account`;
CREATE TABLE `{{prefix}}home_service_technician_account`
(
    `id`              INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `technician_id`   INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `account_type`    VARCHAR(255) NOT NULL DEFAULT 'point' COMMENT '账户类型',
    `account_data`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户数据',
    `account_sum`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '变动后的账户余额',
    `from_type`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '来源类型',
    `related_id`      VARCHAR(50) NOT NULL DEFAULT '' COMMENT '关联Id',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `memo`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注信息',
    `status`          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '结算状态 0:未结算 1:已结算',
    `payment_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '到账时间',
    `category_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '服务类型',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)师傅账单表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_technician_application
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_technician_application`;
CREATE TABLE `{{prefix}}home_service_technician_application`
(
    `id`                INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `real_name`         VARCHAR(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
    `id_card_front`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '身份证正面照片URL',
    `id_card_back`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '身份证反面照片URL',
    `id_number`         VARCHAR(20) NOT NULL DEFAULT '' COMMENT '身份证号码',
    `mobile`            VARCHAR(20) NOT NULL DEFAULT '' COMMENT '手机号码',
    `certificate`       TEXT NOT NULL COMMENT '技能证书照片URL',
    `notes`             TEXT COMMENT '备注信息',
    `province_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '省份ID',
    `city_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '城市ID',
    `district_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '区/县ID',
    `full_address`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '详细地址',
    `lng`               VARCHAR(50) NOT NULL DEFAULT '' COMMENT '经度',
    `lat`               VARCHAR(50) NOT NULL DEFAULT '' COMMENT '纬度',
    `audit_status`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '审核状态：0-待审核 1-通过  -1-拒绝',
    `audit_user_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '审核人ID',
    `audit_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '审核时间',
    `audit_remark`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '审核备注（拒绝原因等）',
    `create_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `member_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `store_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '门店id',
    `site_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `category_id`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '类目id',
    `headimg`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
    PRIMARY KEY (`id`, `city_id`),
    KEY `idx_mobile` (`mobile`),
    KEY `idx_id_number` (`id_number`),
    KEY `idx_audit_status` (`audit_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)师傅入驻申请表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_technician_collect
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_technician_collect`;
CREATE TABLE `{{prefix}}home_service_technician_collect`
(
    `id`             INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `member_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `technician_id`  INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `create_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `IDX_member_collect_goods` (`technician_id`),
    KEY `IDX_member_collect_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)会员收藏表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_technician_level
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_technician_level`;
CREATE TABLE `{{prefix}}home_service_technician_level`
(
    `level_id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '等级Id',
    `site_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `level_num`        INT(11) NOT NULL DEFAULT 0 COMMENT '等级权重',
    `level_name`       VARCHAR(30) NOT NULL DEFAULT '' COMMENT '等级名称',
    `order_rate`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '订单佣金比例',
    `order_num`        INT(11) NOT NULL DEFAULT 0 COMMENT '订单总数',
    `is_default`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否默认等级1=是，0=否',
    `create_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `achievement`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '业绩',
    PRIMARY KEY (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政)师傅等级配置表';

-- ----------------------------
-- Table structure for {{prefix}}home_service_technician_rest
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}home_service_technician_rest`;
CREATE TABLE `{{prefix}}home_service_technician_rest`
(
    `id`             INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id',
    `site_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `store_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '门店id',
    `technician_id`  INT(11) NOT NULL DEFAULT 0 COMMENT '师傅id',
    `date`           VARCHAR(255) NOT NULL DEFAULT '' COMMENT '日期',
    `hour`           LONGTEXT NULL DEFAULT NULL COMMENT '开始时间',
    `create_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `notes`          VARCHAR(3000) NOT NULL DEFAULT '' COMMENT '备注',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='(上门家政) 师傅休息表';

