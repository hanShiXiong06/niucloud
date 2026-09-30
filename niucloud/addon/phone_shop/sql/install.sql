CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_address`
(
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `site_id`             int           NOT NULL DEFAULT 0 COMMENT '站点id',
    `contact_name`        varchar(255)  NOT NULL DEFAULT '' COMMENT '联系人',
    `mobile`              varchar(50)   NOT NULL DEFAULT '' COMMENT '手机号',
    `province_id`         int(11) NOT NULL DEFAULT '0' COMMENT '省',
    `city_id`             int(11) NOT NULL DEFAULT '0' COMMENT '市',
    `district_id`         int(11) NOT NULL DEFAULT '0' COMMENT '区',
    `address`             varchar(255)  NOT NULL DEFAULT '' COMMENT '详细地址',
    `full_address`        varchar(1000) NOT NULL DEFAULT '' COMMENT '地址',
    `lat`                 varchar(50)   NOT NULL DEFAULT '' COMMENT '纬度',
    `lng`                 varchar(50)   NOT NULL DEFAULT '' COMMENT '经度',
    `is_delivery_address` int(11) NOT NULL DEFAULT '0' COMMENT '是否是发货地址',
    `is_refund_address`   int(11) NOT NULL DEFAULT '0' COMMENT '是否是退货地址',
    `is_default_delivery` int(11) NOT NULL DEFAULT '0' COMMENT '默认发货地址',
    `is_default_refund`   int(11) NOT NULL DEFAULT '0' COMMENT '默认收货地址',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商家地址库';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_cart`
(
    `id`             bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '购物车表ID',
    `site_id`        int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`      int(11) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
    `goods_id`       int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品ID',
    `sku_id`         int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'sku id',
    `num`            int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品数量',
    `market_type`    int(11) unsigned NOT NULL DEFAULT '0' COMMENT '活动类型',
    `market_type_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '活动id',
    `create_time`    int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
    `status`         tinyint(4) NOT NULL DEFAULT '1' COMMENT '购物车商品状态',
    `invalid_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '失效原因',
    PRIMARY KEY (`id`),
    KEY              `goods_id` (`goods_id`),
    KEY              `member_id` (`member_id`),
    KEY              `sku_id` (`sku_id`),
    KEY              `type` (`market_type`),
    KEY              `type_id` (`market_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='购物车表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_coupon`
(
    `id`                  int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id`             int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `title`               varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
    `start_time`          int(11) NOT NULL DEFAULT '0' COMMENT '活动开启时间',
    `end_time`            int(11) NOT NULL DEFAULT '0' COMMENT '活动结束时间',
    `remain_count`        int(11) NOT NULL DEFAULT '0' COMMENT '剩余数量',
    `receive_count`       int(11) NOT NULL DEFAULT '0' COMMENT '已领取数量',
    `give_count`          INT(11) NOT NULL DEFAULT 0 COMMENT '已发放数量',
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
    `is_non_vip_only`     tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否仅普通用户可领取和使用 0否 1是',
    PRIMARY KEY (`id`),
    KEY                   `status` (`status`),
    KEY                   `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='优惠券表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_coupon_goods`
(
    `id`          int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
    `site_id`     int NOT NULL DEFAULT 0 COMMENT '站点id',
    `coupon_id`   int(11) NOT NULL DEFAULT '0' COMMENT '优惠券模板id',
    `goods_id`    int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
    `category_id` int(11) NOT NULL DEFAULT '0' COMMENT '分类id',
    PRIMARY KEY (`id`),
    KEY           `index_category_id` (`category_id`),
    KEY           `index_coupon_id` (`coupon_id`),
    KEY           `index_goods_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='优惠券商品或品类关联表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_coupon_member`
(
    `id`                  int(11) NOT NULL AUTO_INCREMENT COMMENT '优惠券发放记录id',
    `site_id`             int            NOT NULL DEFAULT 0 COMMENT '站点id',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='优惠券会员领取记录表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_coupon_send_records`
(
    `id`             INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `coupon_id`      INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '优惠券id',
    `send_num`       INT(11) NOT NULL DEFAULT 0 COMMENT '每位会员发放数量',
    `range_type`     VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '发券范围',
    `range_param`    TEXT                  DEFAULT NULL COMMENT '范围对应参数',
    `success_num`    INT(11) NOT NULL DEFAULT 0 COMMENT '发放成功数',
    `status`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT '状态 wait-待发送 process-发送中 finish-结束',
    `member_num`     INT(11) NOT NULL DEFAULT 0 COMMENT '发放会员数',
    `end_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '发放结束时间',
    `admin_uid`      INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `admin_username` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人名称',
    `create_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='优惠券发券记录表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_company`
(
    `company_id`                        int(11) NOT NULL AUTO_INCREMENT,
    `site_id`                           int           NOT NULL DEFAULT 0 COMMENT '站点id',
    `company_name`                      varchar(255)  NOT NULL DEFAULT '' COMMENT '物流公司名称',
    `logo`                              varchar(255)  NOT NULL DEFAULT '' COMMENT '物流公司logo',
    `url`                               varchar(255)  NOT NULL DEFAULT '' COMMENT '物流公司网站',
    `express_no`                        VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '快递鸟:物流公司编号(用于物流跟踪)',
    `express_no_electronic_sheet`       VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '快递鸟:物流公司编号(用于电子面单)',
    `electronic_sheet_switch`           TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否支持电子面单（0：不支持，1：支持）',
    `print_style`                       VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '电子面单打印模板样式，json字符串',
    `exp_type`                          VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '物流公司业务类型，json字符串',
    `create_time`                       int(11) NOT NULL DEFAULT '0',
    `update_time`                       int(11) NOT NULL DEFAULT '0',
    `kd100_express_no`                  VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '快递100:物流公司编号(用于物流跟踪)',
    `kd100_express_no_electronic_sheet` VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '快递100:物流公司编号(用于电子面单)',
    PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='物流公司表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_deliver`
(
    `deliver_id`     int(11) NOT NULL AUTO_INCREMENT COMMENT '配送员id',
    `site_id`        int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `deliver_name`   varchar(255) NOT NULL DEFAULT '' COMMENT '配送员名称',
    `deliver_mobile` varchar(20)  NOT NULL DEFAULT '' COMMENT '配送员手机号',
    `create_time`    int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `modify_time`    int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
    `store_id`       int(11) NOT NULL DEFAULT '0' COMMENT '门店id',
    PRIMARY KEY (`deliver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='配送员表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_electronic_sheet`
(
    `id`                 INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_id`            INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `template_name`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '模板名称',
    `express_company_id` INT(11) NOT NULL DEFAULT 0 COMMENT '物流公司id',
    `customer_name`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单客户账号（CustomerName）',
    `customer_pwd`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单密码（CustomerPwd）',
    `send_site`          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'SendSite',
    `send_staff`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'SendStaff',
    `month_code`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'MonthCode',
    `pay_type`           TINYINT(4) NOT NULL DEFAULT 0 COMMENT '邮费支付方式（1：现付，2：到付，3：月结）',
    `is_notice`          TINYINT(4) NOT NULL DEFAULT 0 COMMENT '快递员上门揽件（0：否，1：是）',
    `status`             TINYINT(4) NOT NULL DEFAULT 0 COMMENT '状态（1：开启，0：关闭）',
    `exp_type`           INT(11) NOT NULL DEFAULT 0 COMMENT '物流公司业务类型',
    `print_style`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '电子面单打印模板样式',
    `is_default`         TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否默认（1：是，0：否）',
    `create_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
    `interface_type`     CHAR(20)     NOT NULL DEFAULT '' COMMENT '快递公司类型 kdbird:快递鸟 kd100:快递100',
    `exp_type_name`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '物流公司业务类型名称',
    `temp_id`            VARCHAR(255) NOT NULL DEFAULT '' COMMENT '主模版:快递100用',
    `child_temp_id`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '子模版:快递100用',
    `back_temp_id`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '回单模版:快递100用',
    `interface_data`     TEXT                  DEFAULT NULL COMMENT '接口参数',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='电子面单';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_local_delivery`
(
    `local_id`      int(11) NOT NULL AUTO_INCREMENT,
    `site_id`       int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `fee_type`      varchar(30)    NOT NULL DEFAULT '' COMMENT '费用类型',
    `base_dist`     decimal(10, 1) NOT NULL DEFAULT '0.0' COMMENT '多少km内',
    `base_price`    decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '配送费用',
    `grad_dist`     decimal(10, 1) NOT NULL DEFAULT '0.0' COMMENT '每超出多少km内',
    `grad_price`    decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '配送费用',
    `weight_start`  decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '重量多少内不额外收费',
    `weight_unit`   decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '每超出多少kg',
    `weight_price`  decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '价格',
    `delivery_type` varchar(2000)  NOT NULL DEFAULT '' COMMENT '配送类型',
    `area`          longtext       NOT NULL COMMENT '配送区域',
    `center`        varchar(255)   NOT NULL DEFAULT '' COMMENT '发货地址中心点',
    `time_is_open`  TINYINT(4) NOT NULL DEFAULT 0 COMMENT '配送时间设置 0 关闭 1 开启',
    `time_type`     TINYINT(4) NOT NULL DEFAULT 0 COMMENT '时间选取类型 0 每天  1 自定义',
    `time_week`     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '营业时间  周一 周二.......',
    `time_interval` INT(11) NOT NULL DEFAULT 30 COMMENT '时段设置单位分钟',
    `advance_day`   INT(11) NOT NULL DEFAULT 0 COMMENT '时间选择需提前多少天',
    `most_day`      INT(11) NOT NULL DEFAULT 7 COMMENT '最多可预约多少天',
    `start_time`    INT(11) NOT NULL DEFAULT 0 COMMENT '当日的起始时间',
    `end_time`      INT(11) NOT NULL DEFAULT 0 COMMENT '当日的营业结束时间',
    `delivery_time` TEXT                    DEFAULT NULL COMMENT '配送时间段',
    PRIMARY KEY (`local_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='自提点表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_shipping_template`
(
    `template_id`      int(11) NOT NULL AUTO_INCREMENT,
    `site_id`          int         NOT NULL DEFAULT 0 COMMENT '站点id',
    `template_name`    varchar(50) NOT NULL DEFAULT '' COMMENT '模板名称',
    `fee_type`         varchar(20) NOT NULL DEFAULT '' COMMENT '运费计算方式1.重量2体积3按件',
    `create_time`      int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time`      int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
    `is_free_shipping` smallint(6) NOT NULL DEFAULT '0' COMMENT '该区域是否包邮',
    `no_delivery`      smallint(6) NOT NULL DEFAULT '0' COMMENT '是否指定该区域不配送',
    PRIMARY KEY (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='运费模板';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_shipping_template_item`
(
    `item_id`                  int(11) NOT NULL AUTO_INCREMENT,
    `site_id`                  int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `template_id`              int(11) NOT NULL DEFAULT '0' COMMENT '模板id',
    `city_id`                  int(11) NOT NULL DEFAULT '0' COMMENT '市id',
    `snum`                     int(11) NOT NULL DEFAULT '0' COMMENT '起步计算标准',
    `sprice`                   decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '起步计算价格',
    `xnum`                     int(11) NOT NULL DEFAULT '0' COMMENT '续步计算标准',
    `xprice`                   decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '续步计算价格',
    `fee_type`                 varchar(20)    NOT NULL DEFAULT '1' COMMENT '运费计算方式',
    `fee_area_ids`             text           NOT NULL COMMENT '运费设置区域id集',
    `fee_area_names`           text           NOT NULL COMMENT '运费设置区域名称集',
    `no_delivery`              smallint(6) NOT NULL DEFAULT '0' COMMENT '是否指定该区域不配送',
    `no_delivery_area_ids`     text           NOT NULL COMMENT '不配送的区域id集',
    `no_delivery_area_names`   text           NOT NULL COMMENT '不配送的区域名称集',
    `is_free_shipping`         smallint(6) NOT NULL DEFAULT '0' COMMENT '该区域是否包邮',
    `free_shipping_area_ids`   text           NOT NULL COMMENT '包邮的区域id集',
    `free_shipping_area_names` text           NOT NULL COMMENT '包邮的区域名称集',
    `free_shipping_price`      decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '满足包邮的条件',
    `free_shipping_num`        int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`item_id`),
    KEY                        `express_template_item_city_id` (`city_id`),
    KEY                        `express_template_item_fee_type` (`fee_type`),
    KEY                        `express_template_item_template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='运费模板细节';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_discount`
(
    `discount_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '活动id',
    `site_id`     INT(11)            NOT NULL DEFAULT 0 COMMENT '站点id',
    `name`        VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '活动名称',
    `remark`      TEXT                    DEFAULT NULL COMMENT '活动说明',
    `start_time`  INT(11)            NOT NULL DEFAULT 0 COMMENT '活动开始时间',
    `end_time`    INT(11)            NOT NULL DEFAULT 0 COMMENT '活动结束时间',
    `status`      VARCHAR(50)    NOT NULL DEFAULT '' COMMENT '活动状态',
    `order_money` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '活动累计金额',
    `order_num`   INT(11)            NOT NULL DEFAULT 0 COMMENT '活动累计订单数',
    `member_num`  INT(11)            NOT NULL DEFAULT 0 COMMENT '活动参与会员数',
    `success_num` INT(11)            NOT NULL DEFAULT 0 COMMENT '活动成功参与会员数',
    `create_time` INT(11)            NOT NULL DEFAULT 0 COMMENT '添加时间',
    `update_time` INT(11)            NOT NULL DEFAULT 0 COMMENT '修改时间',
    PRIMARY KEY (`discount_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='限时折扣表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_discount_goods`
(
    `discount_goods_id` INT(11)  NOT NULL AUTO_INCREMENT COMMENT '活动商品id',
    `site_id`           INT(11)            NOT NULL DEFAULT 0 COMMENT '站点id',
    `discount_id`       INT(11)            NOT NULL DEFAULT 0 COMMENT '活动id',
    `goods_id`          INT(11)            NOT NULL DEFAULT 0 COMMENT '商品id',
    `sku_id`            INT(11)            NOT NULL DEFAULT 0 COMMENT '商品规格id',
    `status`            VARCHAR(50)    NOT NULL DEFAULT '' COMMENT '商品状态',
    `type`              VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '折扣类型',
    `rate`              DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '折扣',
    `reduce_money`      DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '减钱',
    `discount_price`    DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '活动商品价格（展示，搜索）',
    `order_money`       DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '活动累计金额',
    `order_num`         INT(11)            NOT NULL DEFAULT 0 COMMENT '活动累计订单数',
    `member_num`        INT(11)            NOT NULL DEFAULT 0 COMMENT '活动参与会员数',
    `success_num`       INT(11)            NOT NULL DEFAULT 0 COMMENT '活动成功参与会员数',
    `is_enabled`        INT(11)            NOT NULL DEFAULT 1 COMMENT '是否参与活动',
    PRIMARY KEY (`discount_goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='限时折扣商品表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods`
(
    `goods_id`              int(11)  NOT NULL AUTO_INCREMENT COMMENT '商品id',
    `site_id`               int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `goods_name`            varchar(255)   NOT NULL DEFAULT '' COMMENT '商品名称',
    `goods_type`            varchar(50)    NOT NULL DEFAULT 'real' COMMENT '商品类型',
    `sub_title`             varchar(255)   NOT NULL DEFAULT '' COMMENT '副标题',
    `goods_cover`           varchar(2000)  NOT NULL DEFAULT '' COMMENT '商品封面',
    `goods_image`           text COMMENT '商品图片',
    `goods_image_width`     INT(11) NOT NULL DEFAULT 800 COMMENT '商品详情首图宽',
    `goods_image_height`    INT(11) NOT NULL DEFAULT 800 COMMENT '商品详情首图高',
    `goods_video`           VARCHAR(555)            DEFAULT '' COMMENT '商品视频',
    `goods_category`        varchar(255)   NOT NULL DEFAULT '' COMMENT '商品分类',
    `goods_desc`            text COMMENT '商品介绍',
    `brand_id`              int(11) NOT NULL DEFAULT '0' COMMENT '商品品牌id',
    `label_ids`             varchar(255)   NOT NULL DEFAULT '' COMMENT '标签组',
    `service_ids`           varchar(255)   NOT NULL DEFAULT '' COMMENT '商品服务',
    `unit`                  varchar(255)   NOT NULL DEFAULT '件' COMMENT '单位',
    `stock`                 int(11) NOT NULL DEFAULT '0' COMMENT '商品库存（总和）',
    `sale_num`              int(11) NOT NULL DEFAULT '0' COMMENT '销量',
    `virtual_sale_num`      int(11) NOT NULL DEFAULT '0' COMMENT '虚拟销量',
    `status`                tinyint(4) NOT NULL DEFAULT '1' COMMENT '商品状态（1.正常0下架）',
    `sort`                  int(11) NOT NULL DEFAULT '0' COMMENT '排序',
    `delivery_type`         varchar(255)   NOT NULL DEFAULT '' COMMENT '支持的配送方式',
    `is_free_shipping`      tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否免邮',
    `fee_type`              varchar(255)   NOT NULL DEFAULT '' COMMENT '运费设置，选择模板：template，固定运费：fixed',
    `delivery_money`        decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '固定运费',
    `delivery_template_id`  int(11) NOT NULL DEFAULT '0' COMMENT '运费模板',
    `virtual_auto_delivery` tinyint(4) NOT NULL DEFAULT '0' COMMENT '虚拟商品是否自动发货',
    `virtual_receive_type`  varchar(255)   NOT NULL DEFAULT 'artificial' COMMENT '虚拟商品收货方式，auto：自动收货，artificial：买家确认收货，verify：到店核销',
    `virtual_verify_type`   tinyint(4) NOT NULL DEFAULT '0' COMMENT '虚拟商品核销有效期类型，0：不限，1：购买后几日有效，2：指定过期日期',
    `virtual_indate`        int(11) NOT NULL DEFAULT '0' COMMENT '虚拟到期时间',
    `supplier_id`           int(11) NOT NULL DEFAULT '0' COMMENT '供应商id',
    `attr_ids`              TEXT                    DEFAULT NULL COMMENT '商品参数id，支持多个',
    `attr_format`           TEXT                    DEFAULT NULL COMMENT '商品参数内容，json格式',
    `qc_report`             TEXT                    DEFAULT NULL COMMENT '结构化质检报告json(二手机:result_items含severity/abnormal_items/summary_fields),供质检报告低代码组件渲染',
    `is_discount`           int(11) NOT NULL DEFAULT '0' COMMENT '是否参与限时折扣',
    `member_discount`       varchar(255)   NOT NULL DEFAULT '' COMMENT '会员等级折扣，不参与：空，会员折扣：discount，指定会员价：fixed_price',
    `poster_id`             int(11) NOT NULL DEFAULT '0' COMMENT '海报id',
    `form_id`               INT(11) NOT NULL DEFAULT 0 COMMENT '万能表单id',
    `diy_detail_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '自定义详情id',
    `is_limit`              TINYINT(4) NOT NULL DEFAULT 0 COMMENT '商品是否限购(0:否 1:是)',
    `limit_type`            TINYINT(4) NOT NULL DEFAULT 1 COMMENT '限购类型，1：单次限购，2：单人限购',
    `max_buy`               INT(11) NOT NULL DEFAULT 0 COMMENT '限购数',
    `min_buy`               INT(11) NOT NULL DEFAULT 0 COMMENT '起购数',
    `is_gift`               TINYINT(4) NOT NULL DEFAULT 0 COMMENT '商品是否赠品(0:否 1:是)',
    `access_num`            INT(11) NOT NULL DEFAULT 0 COMMENT '访问次数（浏览量）',
    `cart_num`              INT(11) NOT NULL DEFAULT 0 COMMENT '加入购物车数量',
    `pay_num`               INT(11) NOT NULL DEFAULT 0 COMMENT '支付件数',
    `pay_money`             DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '支付总金额',
    `collect_num`           INT(11) NOT NULL DEFAULT 0 COMMENT '收藏数量',
    `evaluate_num`          INT(11) NOT NULL DEFAULT 0 COMMENT '评论数量',
    `refund_num`            INT(11) NOT NULL DEFAULT 0 COMMENT '退款件数',
    `refund_money`          DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '退款总额',
    `create_time`           int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time`           int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
    `delete_time`           int(11) NOT NULL DEFAULT '0' COMMENT '删除时间',
    `memory_group`          varchar(255)   NOT NULL DEFAULT '' COMMENT '内存分组(二手机)',
    `device_color`          varchar(50)    NOT NULL DEFAULT '' COMMENT '设备颜色(结构化筛选事实)',
    `battery_health`        smallint(4)    NOT NULL DEFAULT -1 COMMENT '电池健康度0-100，-1未知',
    `warranty_expire_time`  int(11)        NOT NULL DEFAULT 0 COMMENT '保修到期时间(当天23:59:59时间戳，0未知)',
    `source`                varchar(30)    NOT NULL DEFAULT '' COMMENT '来源站点id(空=本站自营,如100005=代理主站的货)',
    `source_goods_id`       int(11)        DEFAULT NULL COMMENT '来源站商品ID(仅代理副本使用,系统内部精确联动)',
    `goods_no`              varchar(64)    NOT NULL DEFAULT '' COMMENT '商品跨站公共键(同一台真机跨站共享,铺货/联动按此关联)',
    `goods_url`             varchar(255)   NOT NULL DEFAULT '' COMMENT '商品链接',
    `is_proxy`              tinyint(4)     NOT NULL DEFAULT 0 COMMENT '是否代理商品(代理作废,休眠保留)',
    `condition_grade`       varchar(50)    NOT NULL DEFAULT '' COMMENT '成色等级(二手机)',
    `is_online_sellable`    tinyint(4)     NOT NULL DEFAULT 1 COMMENT '是否允许上用户端自行购买(1是0否)',
    `sale_status`           varchar(20)    NOT NULL DEFAULT 'available' COMMENT '售卖状态:available在售/locked锁定(挂账预订中)/sold已售(终态)',
    PRIMARY KEY (`goods_id`),
    KEY                     `idx_goods_category` (`goods_category`),
    KEY                     `idx_goods_create_time` (`create_time`),
    KEY                     `idx_goods_delete_time` (`delete_time`),
    KEY                     `idx_goods_name` (`goods_name`),
    KEY                     `idx_goods_sort` (`sort`),
    KEY                     `idx_goods_status` (`status`),
    KEY                     `idx_goods_sale_status` (`sale_status`),
    KEY                     `idx_goods_site_sale` (`site_id`, `status`, `sale_status`),
    KEY                     `idx_goods_site_color` (`site_id`, `device_color`),
    KEY                     `idx_goods_site_battery` (`site_id`, `battery_health`),
    KEY                     `idx_goods_site_warranty` (`site_id`, `warranty_expire_time`),
    KEY                     `idx_goods_site_no` (`site_id`, `goods_no`),
    UNIQUE KEY              `uk_goods_source_goods` (`site_id`, `source`, `source_goods_id`, `delete_time`),
    KEY                     `idx_goods_sub_title` (`sub_title`),
    KEY                     `IDX_ns_goods_goods_class` (`goods_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_attr`
(
    `attr_id`           int(11) NOT NULL AUTO_INCREMENT COMMENT '商品参数id',
    `site_id`           int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `attr_no`           int(11) NOT NULL DEFAULT 0 COMMENT '跨站参数公共键(代理同步按此关联)',
    `source_site_id`    int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
    `attr_name`         varchar(255) NOT NULL DEFAULT '' COMMENT '参数名称',
    `attr_value_format` text                  DEFAULT NULL COMMENT '参数值，json格式',
    `sort`              int(11) NOT NULL DEFAULT '0' COMMENT '参数排序号',
    PRIMARY KEY (`attr_id`),
    KEY `idx_attr_no` (`site_id`,`attr_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品参数表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_brand`
(
    `brand_id`    int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '品牌ID',
    `site_id`     int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `brand_no`       int(11) NOT NULL DEFAULT 0 COMMENT '跨站品牌公共键(代理同步按此关联)',
    `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
    `brand_name`  varchar(100) NOT NULL DEFAULT '' COMMENT '品牌名称',
    `logo`        varchar(255) NOT NULL DEFAULT '' COMMENT '品牌logo',
    `desc`        text         NOT NULL COMMENT '品牌介绍',
    `color_json`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '自定义颜色（文字、背景、边框），json格式',
    `sort`        int(11) NOT NULL DEFAULT '0' COMMENT '排序',
    `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
    `delete_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间',
    PRIMARY KEY (`brand_id`),
    KEY `idx_brand_no` (`site_id`,`brand_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品品牌表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_category`
(
    `category_id`        int(11) NOT NULL AUTO_INCREMENT COMMENT '商品分类id',
    `site_id`            int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `category_no`        int(11) NOT NULL DEFAULT 0 COMMENT '跨站分类公共键(代理同步按此关联)',
    `source_site_id`     int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
    `category_name`      varchar(255) NOT NULL DEFAULT '' COMMENT '分类名称',
    `image`              varchar(255) NOT NULL DEFAULT '' COMMENT '分类图片',
    `level`              int(11) NOT NULL DEFAULT '0' COMMENT '层级',
    `pid`                int(11) NOT NULL DEFAULT '0' COMMENT '上级分类id',
    `category_full_name` varchar(255) NOT NULL DEFAULT '' COMMENT '组装分类名称',
    `is_show`            tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否显示（1：显示，0：不显示）',
    `sort`               int(11) NOT NULL DEFAULT '0' COMMENT '排序号',
    `create_time`        int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time`        int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
    PRIMARY KEY (`category_id`),
    KEY `idx_category_no` (`site_id`,`category_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品分类表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_collect`
(
    `id`          int(11) unsigned NOT NULL AUTO_INCREMENT,
    `site_id`     int NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`   int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
    `goods_id`    int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
    `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '收藏时间',
    PRIMARY KEY (`id`),
    KEY           `IDX_member_collect_goods` (`goods_id`),
    KEY           `IDX_member_collect_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品收藏记录表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_subscription`
(
    `subscription_id`   int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '订阅ID',
    `site_id`           int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `member_id`         int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
    `subscription_name` varchar(100) NOT NULL DEFAULT '' COMMENT '订阅名称',
    `rule_json`         text NOT NULL COMMENT '规范化筛选规则JSON',
    `rule_hash`         char(64) NOT NULL DEFAULT '' COMMENT '筛选规则摘要',
    `status`            tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1订阅中，0已取消',
    `match_count`       int(11) NOT NULL DEFAULT 0 COMMENT '累计命中次数',
    `last_notify_time`  int(11) NOT NULL DEFAULT 0 COMMENT '最近通知时间',
    `create_time`       int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`       int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`subscription_id`),
    UNIQUE KEY `uk_site_member_rule` (`site_id`,`member_id`,`rule_hash`),
    KEY `idx_site_status` (`site_id`,`status`),
    KEY `idx_member_status` (`member_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品筛选订阅';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_subscription_match`
(
    `match_id`          bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '命中ID',
    `site_id`           int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `subscription_id`   int(11) NOT NULL DEFAULT 0 COMMENT '订阅ID',
    `member_id`         int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
    `goods_id`          int(11) NOT NULL DEFAULT 0 COMMENT '商品ID',
    `sku_id`            int(11) NOT NULL DEFAULT 0 COMMENT '默认规格ID',
    `goods_fingerprint` char(64) NOT NULL DEFAULT '' COMMENT '商品可售状态指纹',
    `price_snapshot`    decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '本次命中售价快照',
    `stock_snapshot`    int(11) NOT NULL DEFAULT 0 COMMENT '本次命中库存快照',
    `change_type`       varchar(30) NOT NULL DEFAULT 'new_listing' COMMENT '变化类型：new_listing/price_changed',
    `change_summary`    varchar(255) NOT NULL DEFAULT '' COMMENT '面向会员的变化摘要',
    `notify_status`     tinyint(1) NOT NULL DEFAULT 0 COMMENT '通知状态：0待发送，1成功，2失败',
    `error_message`     varchar(500) NOT NULL DEFAULT '' COMMENT '通知失败原因',
    `create_time`       int(11) NOT NULL DEFAULT 0 COMMENT '命中时间',
    `notify_time`       int(11) NOT NULL DEFAULT 0 COMMENT '通知时间',
    PRIMARY KEY (`match_id`),
    UNIQUE KEY `uk_subscription_goods_fingerprint` (`subscription_id`,`goods_id`,`goods_fingerprint`),
    KEY `idx_site_notify` (`site_id`,`notify_status`),
    KEY `idx_member_create` (`member_id`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品订阅命中与通知留痕';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_evaluate`
(
    `evaluate_id`    int(11) NOT NULL AUTO_INCREMENT,
    `site_id`        int           NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`       int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
    `order_goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单项ID',
    `goods_id`       int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
    `category_id`    int(11) NOT NULL DEFAULT '0' COMMENT '评价归属末级分类ID，同型号商品共享',
    `category_name`  varchar(255) NOT NULL DEFAULT '' COMMENT '评价归属分类名称快照',
    `category_path`  varchar(500) NOT NULL DEFAULT '' COMMENT '评价归属分类路径快照',
    `goods_name`     varchar(400) NOT NULL DEFAULT '' COMMENT '成交商品名称快照',
    `sku_name`       varchar(400) NOT NULL DEFAULT '' COMMENT '成交规格名称快照',
    `goods_image`    varchar(2000) NOT NULL DEFAULT '' COMMENT '成交商品图片快照',
    `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0 COMMENT '真实购买评价:1是0否',
    `member_id`      int(11) NOT NULL DEFAULT '0' COMMENT '会员ID',
    `member_head`    varchar(255)  NOT NULL DEFAULT '' COMMENT '会员头像',
    `member_name`    varchar(255)  NOT NULL DEFAULT '' COMMENT '会员名称',
    `content`        varchar(3000) NOT NULL DEFAULT '' COMMENT '评价内容',
    `images`         varchar(3000) NOT NULL DEFAULT '' COMMENT '评价图片',
    `is_anonymous`   tinyint(4) NOT NULL DEFAULT '1' COMMENT '1匿名  2不匿名',
    `scores`         tinyint(4) NOT NULL DEFAULT '1' COMMENT '评论分数 1-5',
    `is_audit`       tinyint(4) NOT NULL DEFAULT '1' COMMENT '审核状态 1待审 2通过 3拒绝',
    `explain_first`  varchar(3000) NOT NULL DEFAULT '' COMMENT '解释内容',
    `topping`        int(11) NOT NULL DEFAULT '0' COMMENT '排序 置顶',
    `create_time`    int(11) NOT NULL DEFAULT '0' COMMENT '评论时间',
    `update_time`    int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
    PRIMARY KEY (`evaluate_id`),
    KEY              `idx_shop_goods_evaluate_create_time` (`create_time`),
    KEY              `idx_shop_goods_evaluate_goods_id` (`goods_id`),
    KEY              `idx_shop_goods_evaluate_category` (`site_id`,`category_id`,`is_audit`),
    KEY              `idx_shop_goods_evaluate_is_anonymous` (`is_anonymous`),
    KEY              `idx_shop_goods_evaluate_is_audit` (`is_audit`),
    KEY              `idx_shop_goods_evaluate_member_id` (`member_id`),
    KEY              `idx_shop_goods_evaluate_order_id` (`order_id`),
    KEY              `idx_shop_goods_evaluate_scores` (`scores`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品评价表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_label`
(
    `label_id`    int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '标签ID',
    `site_id`     int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `label_name`  varchar(255) NOT NULL DEFAULT '' COMMENT '标签名称',
    `group_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '标签分组id',
    `style_type`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '效果设置，diy：自定义，icon：图片',
    `color_json`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '自定义颜色（文字、背景、边框），json格式',
    `icon`        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '图标',
    `status`      INT(11) NOT NULL DEFAULT 0 COMMENT '状态，1：启用，0；关闭',
    `memo`        varchar(255) NOT NULL DEFAULT '' COMMENT '标签说明',
    `sort`        int(11) NOT NULL DEFAULT '0' COMMENT '排序',
    `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`label_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品标签表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_label_group`
(
    `group_id`    INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分组ID',
    `site_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `group_name`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '分组名称',
    `sort`        INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品标签分组表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_rank`
(
    `rank_id`      INT(11) NOT NULL AUTO_INCREMENT,
    `site_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `name`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT '榜单名称',
    `rank_type`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '排行周期 day=天，week=周，month=月, quarter=季度',
    `goods_source` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '来源类型 goods=指定商品，category=指定分类，brand=指定品牌, label=指定标签',
    `rule_type`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '排序规则 sale=按照销量，collect=按收藏数，evaluate=按评价数, access=按照浏览量',
    `goods_json`   TEXT                  DEFAULT NULL COMMENT '商品信息',
    `category_ids` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '商品分类id',
    `brand_ids`    VARCHAR(255) NOT NULL DEFAULT '0' COMMENT '商品品牌id',
    `label_ids`    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '商品标签id，多个逗号隔开',
    `sort`         INT(11) NOT NULL DEFAULT 0 COMMENT '排序号',
    `status`       INT(11) NOT NULL DEFAULT 1 COMMENT '显示状态（0不显示 1显示）',
    `create_time`  INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`  INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
    PRIMARY KEY (`rank_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品排行榜';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_service`
(
    `service_id`   int(11) NOT NULL AUTO_INCREMENT,
    `site_id`      int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `service_name` varchar(255) NOT NULL DEFAULT '' COMMENT '服务名称',
    `image`        varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
    `desc`         varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
    `create_time`  int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time`  int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品服务表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_sku`
(
    `sku_id`          int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '商品sku_id',
    `site_id`         int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `sku_name`        varchar(255)   NOT NULL DEFAULT '' COMMENT '商品sku名称',
    `sku_image`       varchar(2000)  NOT NULL DEFAULT '' COMMENT 'sku主图',
    `sku_no`          varchar(255)   NOT NULL DEFAULT '' COMMENT '商品sku编码',
    `goods_id`        int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
    `sku_spec_format` text COMMENT 'sku规格格式',
    `price`           decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT 'sku单价',
    `market_price`    decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '划线价',
    `sale_price`      decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '实际卖价（有活动显示活动价，默认原价）',
    `cost_price`      decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT 'sku成本价',
    `stock`           int(11) NOT NULL DEFAULT '0' COMMENT '商品sku库存',
    `weight`          decimal(10, 3) NOT NULL DEFAULT '0.000' COMMENT '重量（单位kg）',
    `volume`          decimal(10, 3) NOT NULL DEFAULT '0.000' COMMENT '体积（单位立方米）',
    `sale_num`        int(11) NOT NULL DEFAULT '0' COMMENT '销量',
    `is_default`      tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否默认',
    `member_price`    text COMMENT '会员价，json格式，指定会员价，数据结构为：{"level_1":"10.00","level_2":"10.00"}',
    `erp_asset_id`    int(11)        NOT NULL DEFAULT 0 COMMENT '关联ERP设备资产唯一ID(一物一码关联键,0=非整机)',
    `is_unique`       tinyint(4)     NOT NULL DEFAULT 0 COMMENT '一物一码标记(1=整机单台库存恒1,0=标品)',
    `condition_grade` varchar(50)    NOT NULL DEFAULT '' COMMENT '成色等级(整机SKU)',
    `device_snapshot` text COMMENT '设备质检/成色快照json(下架留痕)',
    PRIMARY KEY (`sku_id`),
    KEY               `idx_goods_sku_erp_asset_id` (`erp_asset_id`),
    KEY               `idx_goods_sku_is_default` (`is_default`),
    KEY               `idx_goods_sku_price` (`price`),
    KEY               `idx_goods_sku_sale_price` (`sale_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品规格表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_spec`
(
    `spec_id`     int(11) NOT NULL AUTO_INCREMENT COMMENT '规格id',
    `site_id`     int          NOT NULL DEFAULT 0 COMMENT '站点id',
    `goods_id`    int(11) NOT NULL DEFAULT '0' COMMENT '关联商品id',
    `spec_name`   varchar(255) NOT NULL DEFAULT '' COMMENT '规格项名称',
    `spec_values` text COMMENT '规格值名称，多个逗号隔开',
    PRIMARY KEY (`spec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品规格项/值表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_transfer_task`
(
    `id`              int unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
    `site_id`         int NOT NULL DEFAULT 0 COMMENT '站点ID',
    `task_type`       varchar(20) NOT NULL DEFAULT 'import' COMMENT 'import/export',
    `operator_uid`    int NOT NULL DEFAULT 0 COMMENT '操作人UID',
    `operator_name`   varchar(60) NOT NULL DEFAULT '' COMMENT '操作人',
    `original_name`   varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
    `source_file`     varchar(500) NOT NULL DEFAULT '' COMMENT '导入源文件',
    `result_file`     varchar(500) NOT NULL DEFAULT '' COMMENT '导出或错误明细文件',
    `status`          varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/queued/processing/completed/partial/failed',
    `queue_enabled`   tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已进入队列',
    `total_rows`      int NOT NULL DEFAULT 0,
    `processed_rows`  int NOT NULL DEFAULT 0,
    `success_count`   int NOT NULL DEFAULT 0,
    `updated_count`   int NOT NULL DEFAULT 0,
    `skipped_count`   int NOT NULL DEFAULT 0,
    `error_count`     int NOT NULL DEFAULT 0,
    `request_json`    longtext NULL COMMENT '导出筛选条件',
    `result_json`     longtext NULL COMMENT '任务统计与错误样例',
    `message`         varchar(500) NOT NULL DEFAULT '',
    `error_message`   varchar(1000) NOT NULL DEFAULT '',
    `start_time`      int NOT NULL DEFAULT 0,
    `finish_time`     int NOT NULL DEFAULT 0,
    `create_time`     int NOT NULL DEFAULT 0,
    `update_time`     int NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_site_type_status` (`site_id`,`task_type`,`status`),
    KEY `idx_site_create` (`site_id`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商城商品批量导入导出任务';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_stat`
(
    `id`                       INT(11) NOT NULL AUTO_INCREMENT,
    `site_id`                  INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `date`                     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '日期',
    `date_time`                INT(11) NOT NULL DEFAULT 0 COMMENT '时间戳',
    `goods_id`                 INT(11) NOT NULL DEFAULT 0 COMMENT '商品id',
    `cart_num`                 INT(11) NOT NULL DEFAULT 0 COMMENT '加入购物车数量',
    `sale_num`                 INT(11) NOT NULL DEFAULT 0 COMMENT '商品销量（下单数）',
    `pay_num`                  INT(11) NOT NULL DEFAULT 0 COMMENT '支付件数',
    `pay_money`                DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '支付总金额',
    `refund_num`               INT(11) NOT NULL DEFAULT 0 COMMENT '退款件数',
    `refund_money`             DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '退款总额',
    `access_num`               INT(11) NOT NULL DEFAULT 0 COMMENT '访问次数（浏览量）',
    `collect_num`              INT(11) NOT NULL DEFAULT 0 COMMENT '收藏数量',
    `evaluate_num`             INT(11) NOT NULL DEFAULT 0 COMMENT '评论数量',
    `goods_visit_member_count` INT(11) NOT NULL DEFAULT 0 COMMENT '商品访客数',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品数据统计';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_invoice`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT COMMENT '发票id',
    `site_id`          int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`        int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
    `trade_type`       varchar(10)    NOT NULL DEFAULT 'order' COMMENT '开票分类 order:订单',
    `trade_id`         varchar(1000)  NOT NULL DEFAULT '' COMMENT '业务id集',
    `header_type`      tinyint(4) NOT NULL DEFAULT '1' COMMENT '抬头类型',
    `header_name`      varchar(100)   NOT NULL DEFAULT '' COMMENT '名称（发票抬头）',
    `type`             tinyint(4) NOT NULL DEFAULT '1' COMMENT '发票类型',
    `name`             varchar(255)   NOT NULL DEFAULT '' COMMENT '发票内容',
    `tax_number`       varchar(50)    NOT NULL DEFAULT '' COMMENT '公司税号',
    `mobile`           varchar(30)    NOT NULL DEFAULT '' COMMENT '开票人手机号',
    `email`            varchar(100)   NOT NULL DEFAULT '' COMMENT '开票人邮箱',
    `telephone`        varchar(30)    NOT NULL DEFAULT '' COMMENT '注册电话',
    `address`          varchar(255)   NOT NULL DEFAULT '' COMMENT '注册地址',
    `bank_name`        varchar(50)    NOT NULL DEFAULT '' COMMENT '开户银行',
    `bank_card_number` varchar(50)    NOT NULL DEFAULT '' COMMENT '银行账号',
    `money`            decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '开票金额',
    `is_invoice`       tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否开票',
    `invoice_number`   varchar(50)    NOT NULL DEFAULT '' COMMENT '发票代码',
    `invoice_voucher`  varchar(1000)  NOT NULL DEFAULT '' COMMENT '发票凭证',
    `remark`           varchar(255)   NOT NULL DEFAULT '' COMMENT '备注',
    `create_time`      int(11) NOT NULL DEFAULT '0' COMMENT '申请时间',
    `invoice_time`     int(11) NOT NULL DEFAULT '0' COMMENT '开票时间',
    `status`           int(11) NOT NULL DEFAULT '0' COMMENT '是否生效',
    `pay_voucher`      text NULL  COMMENT '支付凭证',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='发票表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_manjian`
(
    `manjian_id`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '满减活动id',
    `site_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `manjian_name`      VARCHAR(50)    NOT NULL DEFAULT '' COMMENT '名称',
    `condition_type`    VARCHAR(255)   NOT NULL DEFAULT 'over_n_yuan' COMMENT '条件类型 over_n_yuan:满N元  over_n_piece:满N件',
    `goods_type`        VARCHAR(255)   NOT NULL DEFAULT 'all_goods' COMMENT '参与商品 all_goods:全部商品参与  selected_goods:指定商品 selected_goods_not:指定商品不参与 ',
    `join_member_type`  VARCHAR(255)   NOT NULL DEFAULT 'all_member' COMMENT '参与会员 all_member:所有会员参与  selected_member_level:指定会员等级  selected_member_label:指定会员标签 ',
    `rule_type`         VARCHAR(255)   NOT NULL DEFAULT 'ladder' COMMENT '优惠规格 ladder:阶梯优惠  cycle:循环优惠',
    `rule_json`         TEXT                    DEFAULT NULL COMMENT '优惠规则json',
    `goods_ids`         TEXT                    DEFAULT NULL COMMENT '商品id集',
    `level_ids`         TEXT                    DEFAULT NULL COMMENT '会员等级id集',
    `label_ids`         TEXT                    DEFAULT NULL COMMENT '会员标签id集',
    `status`            INT(11) NOT NULL DEFAULT 0 COMMENT '状态（0未开始1进行中2已结束-1已关闭）',
    `start_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '开始时间',
    `end_time`          INT(11) NOT NULL DEFAULT 0 COMMENT '结束时间',
    `create_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`       INT(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
    `remark`            VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '备注',
    `total_order_money` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '活动累计金额',
    `total_order_num`   INT(11) NOT NULL DEFAULT 0 COMMENT '活动累计订单数',
    `total_member_num`  INT(11) NOT NULL DEFAULT 0 COMMENT '活动参与会员数',
    `total_point`       INT(11) NOT NULL DEFAULT 0 COMMENT '活动累计赠送积分',
    `total_balance`     DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '活动累计赠送余额',
    `total_coupon_num`  INT(11) NOT NULL DEFAULT 0 COMMENT '活动累计赠送优惠券数',
    `total_goods_num`   INT(11) NOT NULL DEFAULT 0 COMMENT '活动累计赠送商品数',
    PRIMARY KEY (`manjian_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='满减活动表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_manjian_goods`
(
    `manjian_goods_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '满减商品活动id',
    `manjian_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '满减活动id',
    `site_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `goods_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '商品id',
    `sku_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '规格id',
    `goods_type`       VARCHAR(255) NOT NULL DEFAULT 'all_goods' COMMENT '参与商品 all_goods:全部商品参与  selected_goods:指定商品 selected_goods_not:指定商品不参与 ',
    `status`           TINYINT(4) NOT NULL DEFAULT 0 COMMENT '状态（0未开始1进行中2已结束-1已关闭）',
    PRIMARY KEY (`manjian_goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='满减商品表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_manjian_give_records`
(
    `record_id`   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '赠送记录id',
    `site_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `manjian_id`  INT(11) NOT NULL DEFAULT 0 COMMENT '满减送活动id',
    `order_id`    INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    `member_id`   INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `level`       INT(11) NOT NULL DEFAULT 0 COMMENT '优惠层级',
    `point`       INT(11) NOT NULL DEFAULT 0 COMMENT '赠送积分数量',
    `balance`     DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '赠送余额',
    `coupon_json` TEXT                    DEFAULT NULL COMMENT '赠送优惠券',
    `goods_json`  TEXT                    DEFAULT NULL COMMENT '赠送商品',
    `sku_ids`     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '满足条件的商品规格id',
    `create_time` int            NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='满减送记录表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_newcomer_member_records`
(
    `record_id`     INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
    `site_id`       INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `member_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `validity_time` INT(11) NOT NULL DEFAULT 0 COMMENT '有效期',
    `create_time`   INT(11) NOT NULL DEFAULT 0 COMMENT '参与时间',
    `update_time`   INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    `is_join`       TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否参与',
    `order_id`      INT(11) NOT NULL DEFAULT 0 COMMENT '参与订单id',
    `goods_ids`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '参与商品id集合',
    `sku_ids`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '参与商品规格id集合',
    PRIMARY KEY (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='新人专享会员参与记录表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order`
(
    `order_id`                int(11) NOT NULL AUTO_INCREMENT,
    `site_id`                 int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `order_no`                varchar(50)    NOT NULL DEFAULT '' COMMENT '订单编号',
    `body`                    varchar(1000)  NOT NULL DEFAULT '' COMMENT '订单内容',
    `order_type`              varchar(55)    NOT NULL DEFAULT '' COMMENT '订单类型',
    `order_from`              varchar(55)    NOT NULL DEFAULT '' COMMENT '订单来源',
    `out_trade_no`            varchar(50)    NOT NULL DEFAULT '' COMMENT '支付流水号',
    `status`                  varchar(55)    NOT NULL DEFAULT '' COMMENT '订单状态',
    `member_id`               int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
    `ip`                      varchar(20)    NOT NULL DEFAULT '' COMMENT 'ip',
    `goods_money`             decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '商品金额',
    `delivery_money`          decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '配送金额',
    `discount_money`          decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
    `order_money`             decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
    `pay_money`               decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '支付金额',
    `base_order_money`        decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '未追加支付手续费前的订单金额',
    `pricing_identity`        varchar(20)    NOT NULL DEFAULT 'retail' COMMENT '计价身份:retail零售/peer同行',
    `payment_fee_rate`        decimal(8, 6)  NOT NULL DEFAULT '0.000000' COMMENT '订单创建时的支付手续费率快照',
    `payment_fee_bearer`      varchar(20)    NOT NULL DEFAULT 'merchant' COMMENT '手续费承担方:merchant商家/customer客户',
    `payment_fee_amount`      decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '预计支付手续费',
    `merchant_net_amount`     decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '预计商家净入账',
    `invoice_id`              int(11) NOT NULL DEFAULT '0' COMMENT '发票id，0表示不开发票',
    `create_time`             int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `pay_time`                int(11) NOT NULL DEFAULT '0' COMMENT '订单支付时间',
    `delivery_time`           int(11) NOT NULL DEFAULT '0' COMMENT '订单发货时间/自提订单自提时间',
    `buyer_ask_delivery_time` VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '购买人要求的配送/发货/自提时间（文本）',
    `take_time`               int(11) NOT NULL DEFAULT '0' COMMENT '订单收货时间',
    `finish_time`             int(11) NOT NULL DEFAULT '0' COMMENT '订单完成时间',
    `close_time`              int(11) NOT NULL DEFAULT '0' COMMENT '订单关闭时间',
    `delete_time`             int(11) NOT NULL DEFAULT '0' COMMENT '是否删除(针对后台)',
    `timeout`                 int(11) NOT NULL DEFAULT '0' COMMENT '通用业务超时时间记录',
    `delivery_type`           varchar(255)   NOT NULL DEFAULT '' COMMENT '配送方式',
    `take_store_id`           int(11) NOT NULL DEFAULT '0' COMMENT '自提点',
    `taker_name`              varchar(500)   NOT NULL DEFAULT '' COMMENT '收货人',
    `taker_mobile`            varchar(50)    NOT NULL DEFAULT '' COMMENT '收货人手机号',
    `taker_province`          int(11) NOT NULL DEFAULT '0' COMMENT '收货省',
    `taker_city`              int(11) NOT NULL DEFAULT '0' COMMENT '收货市',
    `taker_district`          int(11) NOT NULL DEFAULT '0' COMMENT '收货区县',
    `taker_address`           varchar(1000)  NOT NULL DEFAULT '' COMMENT '收货地址',
    `taker_full_address`      varchar(1000)  NOT NULL DEFAULT '' COMMENT '收货详细地址',
    `taker_longitude`         varchar(50)    NOT NULL DEFAULT '' COMMENT '收货地址经度',
    `taker_latitude`          varchar(50)    NOT NULL DEFAULT '' COMMENT '收货详细纬度',
    `taker_store_id`          varchar(50)    NOT NULL DEFAULT '' COMMENT '收货门店',
    `is_enable_refund`        int(11) NOT NULL DEFAULT '0' COMMENT '是否允许退款',
    `member_remark`           varchar(50)    NOT NULL DEFAULT '' COMMENT '会员留言信息',
    `shop_remark`             varchar(255)   NOT NULL DEFAULT '' COMMENT '商家留言',
    `close_remark`            varchar(255)   NOT NULL DEFAULT '' COMMENT '关闭原因',
    `close_type`              varchar(255)   NOT NULL DEFAULT '' COMMENT '关闭来源(未支付自动关闭   手动关闭  退款关闭)',
    `refund_status`           int(11) NOT NULL DEFAULT '1' COMMENT '退款状态  1不存在退款  2 部分退款  3 全部退款',
    `has_goods_types`         varchar(255)   NOT NULL DEFAULT '' COMMENT '包含的商品类型 json',
    `is_evaluate`             int(11) NOT NULL DEFAULT '0' COMMENT '是否评论',
    `relate_id`               int(11) NOT NULL DEFAULT '0' COMMENT '关联id',
    `point`                   int(11) NOT NULL DEFAULT '0' COMMENT '积分兑换',
    `activity_type`           varchar(255)   NOT NULL DEFAULT '' COMMENT '营销类型',
    `form_record_id`          INT(11) NOT NULL DEFAULT 0 COMMENT '万能表单记录id',
    `relate_order_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '关联活动来源订单id',
    `relate_source`           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT 'seckill 秒杀系统',
    `user_delete_time`        INT(11)   NOT NULL DEFAULT 0 COMMENT 'seckill 秒杀系统',
    `payment_mode`            varchar(20)    NOT NULL DEFAULT 'online' COMMENT '支付方式:online线上微信/offline_credit线下挂账/offline_cash线下现结',
    `is_credit`               tinyint(4)     NOT NULL DEFAULT 0 COMMENT '是否挂账单(1是0否)',
    `credit_status`           varchar(20)    NOT NULL DEFAULT '' COMMENT '挂账状态:created成立/partial部分收款/settled已结清/reversed已冲销退货',
    `settle_status`           tinyint(4)     NOT NULL DEFAULT 0 COMMENT '结清状态(0未结清1已结清,应收核销/折账用)',
    `buyer_type`              varchar(10)    NOT NULL DEFAULT 'c' COMMENT '买家类型:c散客/b同行',
    `source_device_id`        int(11)        NOT NULL DEFAULT 0 COMMENT '关联ERP设备资产ID(整机订单精确到机)',
    `staff_id`                int(11)        NOT NULL DEFAULT 0 COMMENT '开单销售员uid(极速开单留痕)',
    PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_batch_delivery`
(
    `id`          INT(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `site_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `main_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `status`      INT(11) NOT NULL DEFAULT 1 COMMENT '状态 进行中  已完成  已失败',
    `type`        VARCHAR(255)  NOT NULL DEFAULT '' COMMENT '操作类型 批量发货  批量打单 ....',
    `total_num`   INT(11) NOT NULL DEFAULT 0 COMMENT '总发货单数',
    `success_num` INT(11) NOT NULL DEFAULT 0 COMMENT '成功发货单数',
    `fail_num`    INT(11) NOT NULL DEFAULT 0 COMMENT '失败发货单数',
    `data`        VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '导入文件的路径',
    `output`      VARCHAR(500)  NOT NULL DEFAULT '' COMMENT '对外输出记录',
    `fail_output` VARCHAR(500)  NOT NULL DEFAULT '' COMMENT '失败记录',
    `fail_remark` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
    `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time` INT(11) NOT NULL DEFAULT 0 COMMENT '操作时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单批量发货表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_offline_record`
(
    `id`              INT(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
    `site_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
    `order_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '商城订单ID',
    `handler_uid`     INT(11) NOT NULL DEFAULT 0 COMMENT '当前负责人UID',
    `handler_name`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '负责人名称快照',
    `handler_mobile`  VARCHAR(30) NOT NULL DEFAULT '' COMMENT '负责人联系电话快照',
    `status`          VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/contacted/paid/credit/delivered/closed',
    `contact_at`      INT(11) NOT NULL DEFAULT 0 COMMENT '确认联系时间',
    `voucher_urls`    TEXT NULL COMMENT '收款凭证图片JSON',
    `close_reason`    VARCHAR(500) NOT NULL DEFAULT '' COMMENT '无法联系等关闭原因',
    `remark`          VARCHAR(500) NOT NULL DEFAULT '' COMMENT '处理备注',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_site_order` (`site_id`,`order_id`),
    KEY `idx_site_handler_status` (`site_id`,`handler_uid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商城线下订单责任流转记录';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_delivery`
(
    `id`                 int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `site_id`            int           NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`           int(11) NOT NULL DEFAULT '0',
    `name`               varchar(50)   NOT NULL DEFAULT '' COMMENT '包裹名称',
    `delivery_type`      varchar(50)   NOT NULL DEFAULT '' COMMENT '配送方式',
    `sub_delivery_type`  varchar(50)   NOT NULL DEFAULT '' COMMENT '详细配送方式',
    `express_company_id` int(11) NOT NULL DEFAULT '0' COMMENT '快递公司id',
    `express_number`     varchar(50)   NOT NULL DEFAULT '' COMMENT '配送单号',
    `local_deliver_id`   int(11) NOT NULL DEFAULT '0' COMMENT '同城配送员',
    `status`             int(11) NOT NULL DEFAULT '0' COMMENT '配送状态',
    `third_delivery`     VARCHAR(50)   NOT NULL DEFAULT '' COMMENT '配送方（三方配送）',
    `remark`             VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '备注',
    `create_time`        int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单发货表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_discount`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `site_id`          int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`         int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
    `order_goods_ids`  varchar(255)   NOT NULL DEFAULT '' COMMENT '参与的订单商品项',
    `type`             varchar(255)   NOT NULL DEFAULT '' COMMENT '类型 discount 优惠，gift 赠送',
    `num`              int(11) NOT NULL DEFAULT '0' COMMENT '使用数量',
    `money`            decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
    `discount_type`    varchar(255)   NOT NULL DEFAULT '' COMMENT '优惠类型',
    `discount_type_id` int(11) NOT NULL DEFAULT '0' COMMENT '优惠类型id',
    `content`          varchar(255)   NOT NULL DEFAULT '' COMMENT '订单优惠说明',
    `create_time`      int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `status`           int(11) NOT NULL DEFAULT '1' COMMENT '状态',
    `member_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '会员id',
    `goods_id`         INT(11) NOT NULL DEFAULT 0 COMMENT '商品id',
    `sku_id`           INT(11) NOT NULL DEFAULT 0 COMMENT 'sku_id',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单优惠表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_discount_goods`
(
    `id`                int(11) NOT NULL AUTO_INCREMENT,
    `site_id`           int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_discount_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单优惠id',
    `order_id`          int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
    `order_goods_id`    varchar(255)   NOT NULL DEFAULT '' COMMENT '参与的订单商品项',
    `type`              varchar(255)   NOT NULL DEFAULT '' COMMENT '类型 discount 优惠，gift 赠送',
    `num`               int(11) NOT NULL DEFAULT '0' COMMENT '使用数量',
    `money`             decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单项优惠表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_goods`
(
    `order_goods_id`           int(11) NOT NULL AUTO_INCREMENT,
    `site_id`                  int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`                 int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
    `member_id`                int(11) NOT NULL DEFAULT '0' COMMENT '购买会员id',
    `goods_id`                 int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
    `sku_id`                   int(11) NOT NULL DEFAULT '0' COMMENT '商品规格id',
    `cost_price_snapshot`      decimal(12, 2) NOT NULL DEFAULT '0.00' COMMENT '下单时单件成本快照',
    `total_cost_snapshot`      decimal(12, 2) NOT NULL DEFAULT '0.00' COMMENT '下单时总成本快照',
    `supplier_id_snapshot`     int(11) NOT NULL DEFAULT '0' COMMENT '下单时供应商ID快照，0为自有/期初商品',
    `inventory_source`         varchar(30) NOT NULL DEFAULT 'self_owned' COMMENT 'supplier/self_owned/opening/erp_asset',
    `goods_name`               varchar(400)   NOT NULL DEFAULT '' COMMENT '商品名称',
    `sku_name`                 varchar(400)   NOT NULL DEFAULT '' COMMENT '商品规格名称',
    `goods_image`              varchar(2000)  NOT NULL DEFAULT '' COMMENT '商品图片',
    `sku_image`                varchar(1000)  NOT NULL DEFAULT '' COMMENT 'sku规格图片',
    `price`                    decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '商品单价',
    `num`                      int(11) NOT NULL DEFAULT '0' COMMENT '购买数量',
    `goods_money`              decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '商品总价',
    `is_enable_refund`         int(11) NOT NULL DEFAULT '0' COMMENT '是否允许退款',
    `goods_type`               varchar(255)   NOT NULL DEFAULT '' COMMENT '商品类型',
    `delivery_status`          varchar(255)   NOT NULL DEFAULT '' COMMENT '配送状态',
    `delivery_id`              int(11) NOT NULL DEFAULT '0' COMMENT '发货单号',
    `discount_money`           decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
    `status`                   int(11) NOT NULL DEFAULT '0' COMMENT '状态',
    `order_refund_no`          varchar(50)    NOT NULL DEFAULT '' COMMENT '退款单号',
    `order_goods_money`        decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '订单项实付金额',
    `original_price`           decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '商品原价',
    `extend`                   varchar(1000)  NOT NULL DEFAULT '' COMMENT '数据项扩展',
    `verify_count`             int(11) NOT NULL DEFAULT '0' COMMENT '已核销次数',
    `verify_expire_time`       int(11) NOT NULL DEFAULT '0' COMMENT '过期时间 0 为永久',
    `is_verify`                int(11) NOT NULL DEFAULT '0' COMMENT '是否需要核销',
    `shop_active_refund`       TINYINT(4) NOT NULL DEFAULT 0 COMMENT '商家主动退款（0否  1是）',
    `shop_active_refund_money` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '商家主动退款金额',
    `is_gift`                  TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否是赠品（0否  1是）',
    `form_record_id`           INT(11) NOT NULL DEFAULT 0 COMMENT '万能表单记录id',
    `delete_time`              INT(11) NOT NULL DEFAULT 0 COMMENT '是否删除(针对后台)',
    PRIMARY KEY (`order_goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单项表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_log`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `order_id`    int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
    `main_type`   VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人类型',
    `main_id`     INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `status`      INT(11) NOT NULL DEFAULT 0 COMMENT '订单状态',
    `type`        VARCHAR(255) NOT NULL DEFAULT '',
    `content`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '日志内容',
    `create_time` INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单日志表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_refund`
(
    `refund_id`          int(11) NOT NULL AUTO_INCREMENT,
    `site_id`            int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`           int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
    `order_goods_id`     int(11) NOT NULL DEFAULT '0' COMMENT '订单项id',
    `order_refund_no`    varchar(255)   NOT NULL DEFAULT '0' COMMENT '退款单号',
    `refund_type`        varchar(255)   NOT NULL DEFAULT '0' COMMENT '退款方式 ',
    `reason`             varchar(255)   NOT NULL DEFAULT '0' COMMENT '退款原因 ',
    `member_id`          int(11) NOT NULL DEFAULT '0' COMMENT '会员id',
    `apply_money`        decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '申请退款',
    `money`              decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '实际退款',
    `status`             varchar(30)    NOT NULL DEFAULT '0' COMMENT '退款状态',
    `create_time`        int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `transfer_time`      int(11) NOT NULL DEFAULT '0' COMMENT '转账时间',
    `remark`             varchar(2000)  NOT NULL DEFAULT '描述' COMMENT '描述',
    `voucher`            varchar(2000)  NOT NULL DEFAULT '凭证' COMMENT '凭证',
    `source`             varchar(255)   NOT NULL DEFAULT '' COMMENT '来源 system 系统 member 会员',
    `timeout`            int(11) NOT NULL DEFAULT '0' COMMENT '操作超时时间',
    `refund_no`          varchar(255)   NOT NULL DEFAULT '' COMMENT '退款交易号',
    `delivery`           varchar(3000)  NOT NULL DEFAULT '' COMMENT '退货配送信息',
    `shop_reason`        varchar(255)   NOT NULL DEFAULT '' COMMENT '上架拒绝原因',
    `refund_address`     varchar(1000)  NOT NULL DEFAULT '' COMMENT '商家退货地址',
    `is_refund_delivery` INT(11) NOT NULL DEFAULT 0 COMMENT '是否退运费',
    `delete_time`        INT(11) NOT NULL DEFAULT 0 COMMENT '是否删除(针对后台)',
    PRIMARY KEY (`refund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单退款表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_order_refund_log`
(
    `id`              int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `order_refund_no` varchar(100) NOT NULL DEFAULT '' COMMENT '退款编号',
    `main_type`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人类型',
    `main_id`         int(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    `status`          INT(11) NOT NULL DEFAULT 0 COMMENT '退款状态',
    `type`            VARCHAR(255) NOT NULL DEFAULT '',
    `content`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT '日志内容',
    `create_time`     INT(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='订单退款日志表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_point_exchange`
(
    `id`                 int(11) NOT NULL AUTO_INCREMENT COMMENT '兑换活动主键id',
    `site_id`            int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `type`               varchar(255)   NOT NULL DEFAULT '' COMMENT '兑换类型（商品、优惠券、红包）',
    `names`              varchar(255)   NOT NULL DEFAULT '' COMMENT '兑换标题',
    `title`              varchar(255)   NOT NULL DEFAULT '' COMMENT '副标题',
    `image`              text COMMENT '图片',
    `status`             int(11) NOT NULL DEFAULT '0' COMMENT '兑换状态 0 下架  1上架  -1 删除',
    `product_detail`     TEXT                    DEFAULT NULL COMMENT '兑换产品信息',
    `point`              int(11) NOT NULL DEFAULT '0' COMMENT '兑换所需积分',
    `price`              decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '兑换所需金额',
    `limit_num`          int(11) NOT NULL DEFAULT '0' COMMENT '限制数量',
    `content`            text COMMENT '产品介绍',
    `sort`               int(11) NOT NULL DEFAULT '0' COMMENT '排序',
    `total_point_num`    int(11) NOT NULL DEFAULT '0' COMMENT '积分消费总额',
    `total_price_num`    decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '总支付金额',
    `total_order_num`    int(11) DEFAULT '0' COMMENT '订单笔数',
    `total_member_num`   int(11) DEFAULT '0' COMMENT '参与会员数',
    `update_time`        int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
    `create_time`        int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `stock`              int(11) NOT NULL DEFAULT '0' COMMENT '库存',
    `total_exchange_num` int(11) NOT NULL DEFAULT '0' COMMENT '兑换数量',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='积分兑换表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_point_exchange_order`
(
    `order_id`          int(11) NOT NULL AUTO_INCREMENT COMMENT '兑换记录id',
    `order_no`          varchar(255)   NOT NULL DEFAULT '' COMMENT '订单编号',
    `out_trade_no`      varchar(255)   NOT NULL DEFAULT '' COMMENT '支付流水表',
    `site_id`           int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `exchange_id`       int(11) NOT NULL DEFAULT '0' COMMENT '兑换活动id',
    `exchange_name`     varchar(255)   NOT NULL DEFAULT '' COMMENT '兑换商品名称',
    `exchange_image`    varchar(600)   NOT NULL DEFAULT '' COMMENT '兑换商品图片',
    `type`              varchar(50)    NOT NULL DEFAULT '' COMMENT '兑换类型',
    `member_id`         int(11) NOT NULL DEFAULT '0' COMMENT '消费会员id',
    `member_address_id` int(11) NOT NULL DEFAULT '0' COMMENT '会员地址id',
    `relate_id`         int(11) NOT NULL DEFAULT '0' COMMENT '关联业务id',
    `relate_order_id`   int(11) NOT NULL DEFAULT '0' COMMENT '关联订单id',
    `point`             int(11) NOT NULL DEFAULT '0' COMMENT '使用积分',
    `price`             decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '支付金额',
    `balance`           decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '赠送余额',
    `create_time`       int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
    `pay_time`          int(11) NOT NULL DEFAULT '0' COMMENT '兑换时间',
    `close_time`        int(11) NOT NULL DEFAULT '0' COMMENT '关闭时间',
    `delete_time`       int(11) NOT NULL DEFAULT '0' COMMENT '订单删除',
    `num`               int(11) NOT NULL DEFAULT '0' COMMENT '兑换数量',
    `status`            varchar(50)    NOT NULL DEFAULT '' COMMENT '订单状态',
    `order_money`       decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
    PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='积分兑换订单表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_stat`
(
    `id`           int(11) NOT NULL AUTO_INCREMENT,
    `site_id`      int            NOT NULL DEFAULT 0 COMMENT '站点id',
    `date`         varchar(255)   NOT NULL DEFAULT '' COMMENT '日期',
    `date_time`    int(11) NOT NULL DEFAULT '0' COMMENT '时间戳',
    `order_num`    int(11) NOT NULL DEFAULT '0' COMMENT '订单总数',
    `sale_money`   decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '销售总额',
    `refund_money` decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '退款总额',
    `access_sum`   int(11) NOT NULL DEFAULT '0' COMMENT '访问数',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_store`
(
    `store_id`        int(11) NOT NULL AUTO_INCREMENT,
    `site_id`         int           NOT NULL DEFAULT 0 COMMENT '站点id',
    `store_name`      varchar(255)  NOT NULL DEFAULT '' COMMENT '门店名称',
    `store_desc`      varchar(3000) NOT NULL DEFAULT '' COMMENT '简介',
    `store_logo`      varchar(255)  NOT NULL DEFAULT '' COMMENT '门店logo',
    `store_mobile`    varchar(255)  NOT NULL DEFAULT '' COMMENT '手机号',
    `province_id`     int(11) NOT NULL DEFAULT '0' COMMENT '省id',
    `city_id`         int(11) NOT NULL DEFAULT '0' COMMENT '市',
    `district_id`     int(11) NOT NULL DEFAULT '0' COMMENT '县（区）',
    `address`         varchar(255)  NOT NULL DEFAULT '' COMMENT '详细地址',
    `full_address`    varchar(255)  NOT NULL DEFAULT '' COMMENT '完整地址',
    `longitude`       varchar(255)  NOT NULL DEFAULT '' COMMENT '经度',
    `latitude`        varchar(255)  NOT NULL DEFAULT '' COMMENT '纬度',
    `trade_time`      varchar(255)  NOT NULL DEFAULT '' COMMENT '营业时间(文本展示使用)',
    `time_week`       TEXT                   DEFAULT NULL COMMENT '自定义的营业时间["0","1","2","3","4","5","6"]周日-周六',
    `trade_time_json` TEXT                   DEFAULT NULL COMMENT '营业时间',
    `time_interval`   INT(11) NOT NULL DEFAULT 0 COMMENT '时段设置（分钟）',
    `create_time`     int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
    `update_time`     int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='自提门店表';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_active`
(
    `active_id`             int(11) NOT NULL AUTO_INCREMENT COMMENT '活动id',
    `site_id`               int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `active_name`           varchar(255)   NOT NULL DEFAULT '' COMMENT '活动名称',
    `active_desc`           text COMMENT '活动说明',
    `active_type`           varchar(255)   NOT NULL DEFAULT '' COMMENT '活动类型(店铺活动，会员活动，商品活动)',
    `active_goods_type`     varchar(255)   NOT NULL DEFAULT '' COMMENT '商品活动类型（单品，独立商品，店铺整体商品）',
    `active_goods_info`     text COMMENT '参与活动商品信息',
    `active_class`          varchar(255)   NOT NULL DEFAULT '' COMMENT '活动类别',
    `active_class_category` varchar(255)   NOT NULL DEFAULT '' COMMENT '活动类别子分类（活动管理）',
    `relate_member`         varchar(1000)  NOT NULL DEFAULT '' COMMENT '参与会员条件(默认全部)',
    `active_value`          text COMMENT '活动扩展信息数据',
    `start_time`            int(11) NOT NULL DEFAULT '0' COMMENT '活动开始时间',
    `end_time`              int(11) NOT NULL DEFAULT '0' COMMENT '活动结束时间',
    `active_status`         varchar(50)    NOT NULL DEFAULT '' COMMENT '活动状态',
    `create_time`           int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
    `update_time`           int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
    `active_order_money`    decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '活动累计金额',
    `active_order_num`      int(11) NOT NULL DEFAULT '0' COMMENT '活动累计订单数',
    `active_member_num`     int(11) NOT NULL DEFAULT '0' COMMENT '活动参与会员数',
    `active_success_num`    int(11) NOT NULL DEFAULT '0' COMMENT '活动成功参与会员数',
    PRIMARY KEY (`active_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='店铺营销活动表（整体活动）';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_active_goods`
(
    `active_goods_id`          int(11) NOT NULL AUTO_INCREMENT COMMENT '活动商品id',
    `active_id`                int(11) NOT NULL DEFAULT '0' COMMENT '活动id',
    `site_id`                  int(11) NOT NULL DEFAULT '0' COMMENT '站点id',
    `goods_id`                 int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
    `sku_id`                   INT(11) DEFAULT 0 COMMENT '商品规格id',
    `active_goods_type`        varchar(255)   NOT NULL DEFAULT '' COMMENT '商品活动类型（单品，独立商品，店铺整体商品）',
    `active_class`             varchar(255)   NOT NULL DEFAULT '' COMMENT '商品活动类别',
    `active_goods_label`       varchar(1000)  NOT NULL DEFAULT '' COMMENT '活动商品标签（针对活动有标签）',
    `active_goods_category`    varchar(1000)  NOT NULL DEFAULT '' COMMENT '活动商品分类（针对活动有分类）',
    `active_goods_value`       text COMMENT '活动商品信息数据',
    `active_goods_status`      varchar(50)    NOT NULL DEFAULT '' COMMENT '活动状态',
    `active_goods_point`       decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '活动商品积分（展示，搜索）',
    `active_goods_price`       decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '活动商品价格（展示，搜索）',
    `active_goods_stock`       int(11) NOT NULL DEFAULT '0' COMMENT '活动商品库存（针对参与库存）',
    `active_goods_order_money` decimal(10, 2) NOT NULL DEFAULT '0.00' COMMENT '活动累计金额',
    `active_goods_order_num`   int(11) NOT NULL DEFAULT '0' COMMENT '活动累计订单数',
    `active_goods_member_num`  int(11) NOT NULL DEFAULT '0' COMMENT '活动参与会员数',
    `active_goods_success_num` int(11) NOT NULL DEFAULT '0' COMMENT '活动成功参与会员数',
    PRIMARY KEY (`active_goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='店铺营销活动';


CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_browse`
(
    `id`          int UNSIGNED NOT NULL AUTO_INCREMENT,
    `site_id`     int           NOT NULL DEFAULT 0,
    `member_id`   int           NOT NULL DEFAULT 0 COMMENT '浏览人',
    `sku_id`      int           NOT NULL DEFAULT 0 COMMENT 'sku_id',
    `goods_id`    int           NOT NULL DEFAULT 0 COMMENT '商品id',
    `browse_time` int           NOT NULL DEFAULT 0 COMMENT '浏览时间',
    `goods_cover` varchar(2000) NOT NULL DEFAULT '' COMMENT '商品图片',
    `goods_name`  varchar(255)  NOT NULL DEFAULT '' COMMENT '商品名称',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商品浏览历史';
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN store_no VARCHAR(255) NOT NULL DEFAULT '' COMMENT '门店编号' AFTER site_id;;
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN contact_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT '联系人' AFTER store_mobile;
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN support_delivery TEXT DEFAULT NULL COMMENT '支持的配送服务商';
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN extend_data TEXT DEFAULT NULL COMMENT '配送服务商扩展数据';
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN time_is_open TINYINT(4) NOT NULL DEFAULT 0 COMMENT '配送时间设置 0 关闭1 开启';
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN support_local_delivery TINYINT(4) NOT NULL DEFAULT 0 COMMENT '支持同城配送';
ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN support_store TINYINT(4) NOT NULL DEFAULT 0 COMMENT '支持门店自提';

ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN status TINYINT(4) NOT NULL DEFAULT 0 COMMENT '开启状态（1开启，0关闭）';

ALTER TABLE `{{prefix}}phone_shop_store`
    ADD COLUMN area LONGTEXT DEFAULT NULL COMMENT '配送区域';

ALTER TABLE `{{prefix}}phone_shop_order_delivery`
    ADD COLUMN local_delivery_order_id VARCHAR(255) NOT NULL DEFAULT '' COMMENT '同城配送单号';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_shop_delivery_order_log`
(
    id           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    main_id      INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    main_type    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人类型',
    main_name    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人名称',
    operate      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作类型',
    operate_desc VARCHAR(500) NOT NULL DEFAULT '' COMMENT '操作描述',
    status       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作后配送状态',
    create_time  INT(11) NOT NULL DEFAULT 0 COMMENT '配送时间',
    remark       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
    PRIMARY KEY (id)
) ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '商家配送服务订单操作表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_shop_delivery_order`
(
    id                         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id                    INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    delivery_no                VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送单号',
    out_delivery_no            VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '三方配送单号',
    trade_type                 VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '交易类型，mall商城 supply供货商',
    trade_no                   VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '交易单号',
    trade_id                   INT(11) NOT NULL DEFAULT 0 COMMENT '交易id',
    delivery_money             DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '配送费用',
    delivery_start             VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送起点（发货点）',
    delivery_start_province_id INT(11) NOT NULL DEFAULT 0 COMMENT '配送起点省',
    delivery_start_city_id     INT(11) NOT NULL DEFAULT 0 COMMENT '配送起点市',
    delivery_start_district_id INT(11) NOT NULL DEFAULT 0 COMMENT '配送起点区',
    delivery_start_lng         VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送起点经度',
    delivery_start_lat         VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送起点纬度',
    delivery_end               VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送终点（收货点）',
    delivery_end_province_id   INT(11) NOT NULL DEFAULT 0 COMMENT '配送终点省',
    delivery_end_city_id       INT(11) NOT NULL DEFAULT 0 COMMENT '配送终点市',
    delivery_end_district_id   INT(11) NOT NULL DEFAULT 0 COMMENT '配送终点区',
    delivery_end_lng           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送终点经度',
    delivery_end_lat           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送终点纬度',
    delivery_distance          DECIMAL(10, 4) NOT NULL DEFAULT 0.0000 COMMENT '配送距离',
    deliver_id                 INT(11) NOT NULL DEFAULT 0 COMMENT '配送员id',
    deliver_name               VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送员姓名',
    deliver_mobile             VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送员手机号',
    status                     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送状态',
    create_time                INT(11) NOT NULL DEFAULT 0 COMMENT '配送时间',
    finish_time                INT(11) NOT NULL DEFAULT 0 COMMENT '送达时间',
    cancel_time                INT(11) NOT NULL DEFAULT 0 COMMENT '取消时间',
    remark                     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '备注',
    body                       VARCHAR(1000)  NOT NULL DEFAULT '' COMMENT '商品信息',
    PRIMARY KEY (id)
) ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '商家配送服务订单表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_local_delivery_service`
(
    id          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id     INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `key`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '三方配送key',
    name        VARCHAR(255) NOT NULL DEFAULT '' COMMENT '三方配送名称',
    config      TEXT                  DEFAULT NULL COMMENT '三方配送接入参数',
    is_merchant TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否商家自配送',
    is_use      TINYINT(4) NOT NULL DEFAULT 0 COMMENT '是否启用（0否，1是）',
    PRIMARY KEY (id)
) ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '同城配送服务商表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_local_delivery_order_log`
(
    id           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT(11) NOT NULL DEFAULT 0 COMMENT '订单id',
    main_id      INT(11) NOT NULL DEFAULT 0 COMMENT '操作人id',
    main_type    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人类型',
    main_name    VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作人名称',
    operate      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作类型',
    operate_desc VARCHAR(500) NOT NULL DEFAULT '' COMMENT '操作描述',
    status       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '操作后配送状态',
    create_time  INT(11) NOT NULL DEFAULT 0 COMMENT '配送时间',
    remark       VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
    PRIMARY KEY (id)
) ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '同城配送服务订单操作表';
CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_delivery_local_delivery_order`
(
    id                         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id                    INT(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    delivery_service           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送方标识',
    delivery_no                VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送单号',
    out_delivery_no            VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '三方配送单号',
    trade_type                 VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '交易类型，mall商城',
    trade_no                   VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '交易单号',
    trade_id                   INT(11) NOT NULL DEFAULT 0 COMMENT '交易id',
    delivery_money             DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '配送费用',
    delivery_start             VARCHAR(1000)  NOT NULL DEFAULT '' COMMENT '配送起点（发货点）',
    delivery_start_province_id INT(11) NOT NULL DEFAULT 0 COMMENT '配送起点省',
    delivery_start_city_id     INT(11) NOT NULL DEFAULT 0 COMMENT '配送起点市',
    delivery_start_district_id INT(11) NOT NULL DEFAULT 0 COMMENT '配送起点区',
    delivery_start_lng         VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送起点经度',
    delivery_start_lat         VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送起点纬度',
    delivery_end               VARCHAR(1000)  NOT NULL DEFAULT '' COMMENT '配送终点（收货点）',
    delivery_end_province_id   INT(11) NOT NULL DEFAULT 0 COMMENT '配送终点省',
    delivery_end_city_id       INT(11) NOT NULL DEFAULT 0 COMMENT '配送终点市',
    delivery_end_district_id   INT(11) NOT NULL DEFAULT 0 COMMENT '配送终点区',
    delivery_end_lng           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送终点经度',
    delivery_end_lat           VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送终点纬度',
    delivery_distance          DECIMAL(10, 4) NOT NULL DEFAULT 0.0000 COMMENT '配送距离',
    status                     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送状态',
    out_status                 VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '三方配送状态',
    create_time                INT(11) NOT NULL DEFAULT 0 COMMENT '配送时间',
    finish_time                INT(11) NOT NULL DEFAULT 0 COMMENT '送达时间',
    cancel_time                INT(11) NOT NULL DEFAULT 0 COMMENT '取消时间',
    rider_name                 VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '骑手姓名',
    rider_mobile               VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '骑手电话',
    deduct_money               DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '违约金',
    remark                     VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '备注',
    body                       VARCHAR(255)   NOT NULL DEFAULT '' COMMENT '配送产品信息',
    actual_money               DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '实际消费费用(订单取消或完成后实际定义)',
    PRIMARY KEY (id)
) ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci,
COMMENT = '同城配送服务订单表';
CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_memory_group` (
  `group_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '分组ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `memory_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站内存分组公共键(代理同步按此关联)',
  `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
  `group_name` varchar(50) NOT NULL DEFAULT '' COMMENT '分组名称,如iPhone系列、安卓系列等',
  `memory_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '内存规格id集合',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`group_id`),
  KEY `idx_memory_no` (`site_id`,`memory_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='内存规格分组表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_memory_spec` (
  `spec_id` int(11) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT COMMENT '规格ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `spec_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站内存规格公共键(代理同步按此关联)',
  `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
  `spec_name` varchar(50) NOT NULL DEFAULT '' COMMENT '规格名称,如64G、8+128G等',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  KEY `idx_spec_no` (`site_id`,`spec_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='内存规格表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_agent` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `master_site_id` int NOT NULL DEFAULT 0 COMMENT '主站id(被代理方,如100005)',
  `agent_site_id` int NOT NULL DEFAULT 0 COMMENT '子站id(代理方,接收铺货)',
  `markup_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '子站固定加价(主站零售价上叠加,默认0)',
  `subscribe_category` tinyint NOT NULL DEFAULT 1 COMMENT '是否订阅主站引用数据(分类/品牌/内存/参数)同步 1是0否',
  `ref_sync_mode` varchar(16) NOT NULL DEFAULT 'realtime' COMMENT '基础资料同步方式 manual/realtime/interval',
  `ref_sync_interval` int NOT NULL DEFAULT 60 COMMENT '基础资料定时同步周期(分钟)',
  `ref_auto_create_category` tinyint NOT NULL DEFAULT 0 COMMENT '是否自动补建缺失分类，已有映射始终跟随主站',
  `ref_sync_next_time` int NOT NULL DEFAULT 0 COMMENT '下次基础资料定时同步时间',
  `ref_sync_last_time` int NOT NULL DEFAULT 0 COMMENT '最近基础资料同步结束时间',
  `ref_sync_status` varchar(20) NOT NULL DEFAULT 'idle' COMMENT 'idle/queued/running/success/partial/failed',
  `ref_sync_token` varchar(32) NOT NULL DEFAULT '' COMMENT '当前基础资料同步批次令牌',
  `ref_sync_started_at` int NOT NULL DEFAULT 0 COMMENT '同步执行心跳时间',
  `ref_sync_result` text COMMENT '当前批次进度、分类型统计和最多20条错误(JSON)',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_master_agent` (`master_site_id`,`agent_site_id`),
  KEY `idx_ref_sync_due` (`master_site_id`,`status`,`ref_sync_mode`,`ref_sync_next_time`),
  KEY `idx_agent_site` (`agent_site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='商城站点代理订阅关系表';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_category_mapping` (
  `mapping_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '分类映射ID',
  `master_site_id` int NOT NULL DEFAULT 0 COMMENT '主站ID',
  `agent_site_id` int NOT NULL DEFAULT 0 COMMENT '子站ID',
  `master_category_id` int NOT NULL DEFAULT 0 COMMENT '主站分类ID',
  `agent_category_id` int NOT NULL DEFAULT 0 COMMENT '子站分类ID，待处理时为0',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending待处理/mapped已映射/broken失效/ignored忽略/source_deleted来源删除',
  `match_type` varchar(30) NOT NULL DEFAULT '' COMMENT 'legacy_copy/auto_exact/manual/manual_created',
  `candidate_ids` text COMMENT '系统建议的子站分类ID(JSON数组)',
  `problem_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '待处理或失效原因',
  `master_snapshot` text COMMENT '主站分类快照(JSON)',
  `resolved_by` int NOT NULL DEFAULT 0 COMMENT '最后处理管理员UID',
  `last_check_time` int NOT NULL DEFAULT 0 COMMENT '最后检查时间',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uk_category_mapping_source` (`master_site_id`,`agent_site_id`,`master_category_id`),
  KEY `idx_category_mapping_status` (`agent_site_id`,`status`,`update_time`),
  KEY `idx_category_mapping_target` (`agent_site_id`,`agent_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手商城跨站分类映射与待处理台账';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_agent_sync_run` (
  `run_id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '同步批次ID',
  `master_site_id` int NOT NULL DEFAULT 0 COMMENT '主站ID',
  `agent_site_id` int NOT NULL DEFAULT 0 COMMENT '子站ID',
  `trigger_type` varchar(30) NOT NULL DEFAULT 'manual' COMMENT 'manual/relation/category/auto',
  `status` varchar(20) NOT NULL DEFAULT 'queued' COMMENT 'queued/running/success/partial/failed',
  `scanned_count` int NOT NULL DEFAULT 0 COMMENT '已扫描主站商品数',
  `success_count` int NOT NULL DEFAULT 0 COMMENT '成功同步数',
  `failed_count` int NOT NULL DEFAULT 0 COMMENT '失败数',
  `failure_summary` text COMMENT '失败原因汇总(JSON)',
  `error_samples` text COMMENT '失败商品样例(JSON，最多20条)',
  `error_message` varchar(1000) NOT NULL DEFAULT '' COMMENT '批次级错误',
  `started_at` int NOT NULL DEFAULT 0 COMMENT '开始时间',
  `finished_at` int NOT NULL DEFAULT 0 COMMENT '完成时间',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`run_id`),
  KEY `idx_agent_sync_run` (`agent_site_id`,`create_time`),
  KEY `idx_agent_sync_status` (`agent_site_id`,`status`,`update_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手商城主从站商品同步批次台账';

CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_device_intake` (
  `intake_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '货源记录ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `erp_asset_id` int(11) NOT NULL DEFAULT 0 COMMENT 'ERP设备资产ID(仅在同站点当前ERP安装周期内唯一)',
  `device_id` int(11) NOT NULL DEFAULT 0 COMMENT '回收设备ID',
  `model_name` varchar(100) NOT NULL DEFAULT '' COMMENT '型号',
  `brand_name` varchar(100) NOT NULL DEFAULT '' COMMENT '品牌',
  `memory` varchar(50) NOT NULL DEFAULT '' COMMENT '内存',
  `color` varchar(50) NOT NULL DEFAULT '' COMMENT '颜色',
  `battery_health` smallint(4) NOT NULL DEFAULT -1 COMMENT '电池健康度0-100，-1未知',
  `warranty_expire_time` int(11) NOT NULL DEFAULT 0 COMMENT '保修到期时间(当天23:59:59时间戳，0未知)',
  `condition_grade` varchar(50) NOT NULL DEFAULT '' COMMENT '成色',
  `imei` varchar(50) NOT NULL DEFAULT '' COMMENT 'IMEI',
  `images` text COMMENT '设备图片(json数组)',
  `sale_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '销售价',
  `peer_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '同行价',
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '成本价',
  `qc_info` text COMMENT '质检信息(json)',
  `hidden_check_keys` text COMMENT '定价员隐藏的质检项字段名(json数组);建品上架时据此从 qc_report 过滤,买家不可见',
  `media` text COMMENT '带分类(scene=正面/反面/侧面/瑕疵)的图片[{url,scene}],供商品详情按分类分组展示',
  `raw_payload` text COMMENT '原始事件payload(备查)',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '状态:0待建品/1已建品/2忽略',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '建品后回填的商品id',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`intake_id`),
  UNIQUE KEY `uk_site_erp_asset` (`site_id`,`erp_asset_id`),
  KEY `idx_site_status` (`site_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='待上架货源(中台定价设备)暂存表';

-- 二手商城·上架属性参考数据：规格分组(绑分类，如苹果内存/手表表盘尺寸) + 规格子项 + 成色等级(扁平)。
-- 供"待上架货源→建品"时按分类选规格、选成色，做筛选依赖。

-- 规格分组（绑商品分类；标签可变：内存 / 表盘尺寸 / 规格）
CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_spec_group` (
  `group_id`    int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '规格分组ID',
  `site_id`     int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `spec_group_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站规格分组公共键(代理同步按此关联)',
  `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
  `category_id` int(11) NOT NULL DEFAULT 0 COMMENT '绑定的商品分类id(三级分类任意级)',
  `category_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '绑定的商品分类id，多个以逗号分隔',
  `label`       varchar(50) NOT NULL DEFAULT '内存' COMMENT '规格标签名(内存/表盘尺寸/规格)',
  `sort`        int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`group_id`),
  KEY `idx_site_cat` (`site_id`,`category_id`),
  KEY `idx_spec_group_no` (`site_id`,`spec_group_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手商城-规格分组';

-- 规格子项（如 128G / 8+128 / 46MM）
CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_spec_item` (
  `item_id`     int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '规格子项ID',
  `site_id`     int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `spec_item_no` int(11) NOT NULL DEFAULT 0 COMMENT '跨站规格子项公共键(代理同步按此关联)',
  `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
  `group_id`    int(11) NOT NULL DEFAULT 0 COMMENT '所属规格分组id',
  `item_value`  varchar(50) NOT NULL DEFAULT '' COMMENT '规格值(128G/8+128/46MM)',
  `sort`        int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`),
  KEY `idx_site_group` (`site_id`,`group_id`),
  KEY `idx_spec_item_no` (`site_id`,`spec_item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手商城-规格子项';

-- 成色等级（扁平，如 九九新靓机 / 95新花机 / 九八新）
CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_goods_grade` (
  `grade_id`    int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '成色等级ID',
  `site_id`     int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
  `grade_no`    int(11) NOT NULL DEFAULT 0 COMMENT '跨站成色等级公共键(代理同步按此关联)',
  `source_site_id` int(11) NOT NULL DEFAULT 0 COMMENT '来源主站id(0=本站自建,非0=从主站同步)',
  `grade_name`  varchar(50) NOT NULL DEFAULT '' COMMENT '成色名(九九新靓机/95新花机)',
  `grade_desc`  varchar(500) NOT NULL DEFAULT '' COMMENT '成色等级描述',
  `grade_image` varchar(1000) NOT NULL DEFAULT '' COMMENT '成色等级图片',
  `sort`        int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status`      tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用/0停用',
  `create_time` int(11) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`grade_id`),
  KEY `idx_site` (`site_id`),
  KEY `idx_grade_no` (`site_id`,`grade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手商城-成色等级';

-- 同行商品转发权益申请。会员身份仍以系统 member_level 为唯一真相，本表只保存申请与审核留痕。
CREATE TABLE IF NOT EXISTS `{{prefix}}phone_shop_forward_application` (
  `application_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '申请会员ID',
  `target_level_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核通过后设置的会员等级',
  `target_level_name` varchar(100) NOT NULL DEFAULT '' COMMENT '目标等级名称快照',
  `form_id` int(11) NOT NULL DEFAULT 0 COMMENT '万能表单ID',
  `form_record_id` int(11) NOT NULL DEFAULT 0 COMMENT '万能表单填写记录ID',
  `reviewer_uid` int(11) NOT NULL DEFAULT 0 COMMENT '指定审核负责人UID',
  `reviewer_name` varchar(100) NOT NULL DEFAULT '' COMMENT '审核负责人名称快照',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected/cancelled',
  `apply_message` varchar(500) NOT NULL DEFAULT '' COMMENT '申请补充说明',
  `review_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '审核意见',
  `reviewed_uid` int(11) NOT NULL DEFAULT 0 COMMENT '实际审核人UID',
  `reviewed_name` varchar(100) NOT NULL DEFAULT '' COMMENT '实际审核人名称',
  `reviewed_at` int(11) NOT NULL DEFAULT 0 COMMENT '审核时间',
  `create_time` int(11) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `uk_site_form_record` (`site_id`,`form_record_id`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `idx_site_member` (`site_id`,`member_id`),
  KEY `idx_reviewer_status` (`site_id`,`reviewer_uid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='二手商城-同行转发权益申请';

