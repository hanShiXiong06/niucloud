DROP TABLE IF EXISTS `{{prefix}}wj_books_cart`;
CREATE TABLE `{{prefix}}wj_books_cart`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL COMMENT '站点ID',
  `member_id` int(11) NOT NULL COMMENT '用户ID',
  `isbn` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'ISBN码',
  `book_id` int(11) NULL DEFAULT NULL COMMENT '书籍ID',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '数量',
  `add_time` datetime NULL DEFAULT NULL COMMENT '添加时间',
  `create_time` datetime NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_member_isbn`(`member_id`, `isbn`) USING BTREE,
  INDEX `idx_member_id`(`member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '回收车表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_config`;
CREATE TABLE `{{prefix}}wj_books_config`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `site_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '站点ID',
  `platform_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '二手书回收平台' COMMENT '平台名称',
  `min_book_count` int(11) UNSIGNED NULL DEFAULT 5 COMMENT '上门回收最低图书数量',
  `rejected_book_retrieve_days` int(11) UNSIGNED NULL DEFAULT 7 COMMENT '拒收书籍可取回的期限天数',
  `price_adjust_rate` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT '回收价调整比例，单位%',
  `book_api_enabled` tinyint(1) UNSIGNED NULL DEFAULT 1 COMMENT '是否启用图书API(0:不启用只内部查询,1:启用调用外部API)',
  `book_api_provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '0' COMMENT '图书数据API来源【\'0:juhe 1:likeapi\'】',
  `book_api_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图书API密钥',
  `yunyang_appid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '云洋物流AppID',
  `yunyang_app_secret` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '云洋物流AppSecret',
  `yunyang_callback_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '接收物流状态推送的回调地址',
  `yunyang_channel_subtag` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '京东' COMMENT '指定快递类型(京东、德邦、顺丰、申通、极兔、圆通、中通、韵达、菜鸟等)',
  `yunyang_auto_order` tinyint(1) UNSIGNED NULL DEFAULT 1 COMMENT '是否自动下单云洋快递(0:不自动下单,1:自动下单)',
  `express_receiver_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件人姓名',
  `express_receiver_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件人电话',
  `express_receiver_province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件省份',
  `express_receiver_city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件城市',
  `express_receiver_county` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件区县',
  `express_receiver_town` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件街道',
  `express_receiver_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '默认收件详细地址',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_site_id`(`site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '二手书回收系统配置表' ROW_FORMAT = DYNAMIC;

DROP TABLE IF EXISTS `{{prefix}}wj_books_express_channel`;
CREATE TABLE `{{prefix}}wj_books_express_channel`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `channel_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '渠道ID',
  `channel` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '渠道名称',
  `channel_logo_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '渠道logo',
  `tag_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '渠道类别：京东、德邦、顺丰等',
  `tag_code` int(10) UNSIGNED NOT NULL COMMENT '渠道类别代码',
  `original_price` decimal(10, 2) NULL DEFAULT NULL COMMENT '官方原价',
  `discount` decimal(10, 2) NULL DEFAULT NULL COMMENT '折扣率',
  `freight` decimal(10, 2) NOT NULL COMMENT '运费',
  `insured_rate` decimal(10, 4) NULL DEFAULT NULL COMMENT '保价费比率',
  `paozhong` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '抛重比率',
  `channel_explain` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '渠道政策说明',
  `price_comments` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '渠道提示信息',
  `is_enabled` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否启用：0否，1是',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_site_channel`(`site_id`, `channel_id`) USING BTREE,
  INDEX `idx_site_tag`(`site_id`, `tag_code`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '物流渠道缓存表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_express_log`;
CREATE TABLE `{{prefix}}wj_books_express_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `order_id` bigint(20) UNSIGNED NOT NULL COMMENT '订单ID',
  `waybill` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '运单号',
  `shopbill` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家单号',
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '运单状态描述',
  `type_code` tinyint(1) NULL DEFAULT NULL COMMENT '状态码：1待揽收，2运输中，3已签收，4拒收退回，99已取消',
  `weight` decimal(10, 2) NULL DEFAULT NULL COMMENT '下单重量',
  `real_weight` decimal(10, 2) NULL DEFAULT NULL COMMENT '站点称重',
  `transfer_weight` decimal(10, 2) NULL DEFAULT NULL COMMENT '分拣称重',
  `cal_weight` decimal(10, 2) NULL DEFAULT NULL COMMENT '计费重量',
  `volume` decimal(10, 2) NULL DEFAULT NULL COMMENT '体积',
  `parse_weight` decimal(10, 2) NULL DEFAULT NULL COMMENT '体积换算重量',
  `total_freight` decimal(10, 2) NULL DEFAULT NULL COMMENT '运单总扣款费用',
  `freight` decimal(10, 2) NULL DEFAULT NULL COMMENT '快递费',
  `freight_insured` decimal(10, 2) NULL DEFAULT NULL COMMENT '保价费',
  `freight_haocai` decimal(10, 2) NULL DEFAULT NULL COMMENT '增值费用',
  `change_bill` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '换单号',
  `change_bill_freight` decimal(10, 2) NULL DEFAULT NULL COMMENT '逆向费',
  `fee_over` tinyint(1) NULL DEFAULT NULL COMMENT '订单扣费状态：1已扣费，0冻结',
  `courier_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '快递员姓名',
  `courier_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '快递员电话',
  `pickup_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '取件码',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '回调原始内容',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_site_order`(`site_id`, `order_id`) USING BTREE,
  INDEX `idx_waybill`(`waybill`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '物流回调日志表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_info`;
CREATE TABLE `{{prefix}}wj_books_info`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL COMMENT '站点ID',
  `isbn` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '13位ISBN码',
  `isbn10` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '10位ISBN码',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '书名',
  `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '作者信息',
  `publisher` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '出版社',
  `pub_date` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '出版日期',
  `price` decimal(10, 2) NULL DEFAULT NULL COMMENT '定价',
  `binding` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '装帧信息',
  `page` int(11) NULL DEFAULT NULL COMMENT '页数',
  `edition` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '版次',
  `img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '封面大图链接',
  `small_img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '封面小图链接',
  `gist` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '内容简介',
  `recycle_price` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '回收价格',
  `can_recycle` tinyint(1) NULL DEFAULT 1 COMMENT '是否可回收(0:不可回收,1:可回收)',
  `recycle_count` int(11) NULL DEFAULT 0 COMMENT '回收次数',
  `api_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'API返回的原始JSON',
  `api_query_time` datetime NULL DEFAULT NULL COMMENT 'API查询时间',
  `create_time` datetime NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_isbn`(`isbn`) USING BTREE,
  INDEX `idx_title`(`title`) USING BTREE,
  INDEX `idx_author`(`author`) USING BTREE,
  INDEX `idx_publisher`(`publisher`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '图书信息表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_order`;
CREATE TABLE `{{prefix}}wj_books_order`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `order_no` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '订单编号',
  `member_id` bigint(20) UNSIGNED NOT NULL COMMENT '会员ID',
  `address_id` bigint(20) UNSIGNED NOT NULL COMMENT '回收地址ID',
  `book_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '书籍总数量',
  `total_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '预估总金额',
  `final_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '最终回收金额',
  `final_book_count` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '最终回收书籍数量',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '订单状态：1待上门，2已取件，3审核中，4已完成，5已取消',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单备注',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `pickup_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '预约上门时间',
  `pickup_actual_time` datetime NULL DEFAULT NULL COMMENT '实际取件时间',
  `complete_time` datetime NULL DEFAULT NULL COMMENT '完成时间',
  `cancel_time` datetime NULL DEFAULT NULL COMMENT '取消时间',
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '取消原因',
  `completion_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '回收完成说明',
  `retrieve_deadline` datetime NULL DEFAULT NULL COMMENT '不合格书籍取回截止时间',
  `retrieve_applied` tinyint(1) UNSIGNED NULL DEFAULT 0 COMMENT '是否已申请取回：0否，1是',
  `express_channel_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '云洋物流渠道ID',
  `express_channel` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '物流渠道名称',
  `express_waybill` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '物流运单号',
  `express_shopbill` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商家单号',
  `express_status` tinyint(1) NULL DEFAULT NULL COMMENT '物流状态：1待揽收，2运输中，3已签收，4拒收退回，99已取消',
  `express_weight` decimal(10, 2) NULL DEFAULT NULL COMMENT '物流称重',
  `express_freight` decimal(10, 2) NULL DEFAULT NULL COMMENT '物流费用',
  `express_courier_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '快递员姓名',
  `express_courier_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '快递员电话',
  `express_pickup_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '取件码',
  `audit_progress` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '审核进度：0-100',
  `deleted` tinyint(1) UNSIGNED NULL DEFAULT 0 COMMENT '是否删除：0否，1是',
  `update_time` datetime NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_site_order_no`(`site_id`, `order_no`) USING BTREE,
  INDEX `idx_site_member`(`site_id`, `member_id`) USING BTREE,
  INDEX `idx_site_status`(`site_id`, `status`) USING BTREE,
  INDEX `idx_create_time`(`create_time`) USING BTREE,
  INDEX `idx_express_waybill`(`express_waybill`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '二手书籍回收订单表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_order_book`;
CREATE TABLE `{{prefix}}wj_books_order_book`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `order_id` bigint(20) UNSIGNED NOT NULL COMMENT '订单ID',
  `book_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '书籍ID',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '书籍标题',
  `author` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '作者',
  `img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '书籍封面图',
  `isbn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'ISBN编号',
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT '总数量',
  `accepted_quantity` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '实际接收数量',
  `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '预估回收单价',
  `final_price` decimal(10, 2) NULL DEFAULT NULL COMMENT '最终回收单价',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_site_order`(`site_id`, `order_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单书籍明细表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_rejected_book`;
CREATE TABLE `{{prefix}}wj_books_rejected_book`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `order_id` bigint(20) UNSIGNED NOT NULL COMMENT '订单ID',
  `order_book_id` bigint(20) UNSIGNED NOT NULL COMMENT '订单书籍ID',
  `book_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '书籍ID',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '书籍标题',
  `author` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '作者',
  `img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '书籍封面图',
  `isbn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'ISBN编号',
  `rejected_quantity` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT '拒收数量',
  `reject_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '拒收原因',
  `can_retrieve` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否可取回：0否，1是',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_site_order`(`site_id`, `order_id`) USING BTREE,
  INDEX `idx_order_book`(`order_book_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '拒收书籍表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_rejected_images`;
CREATE TABLE `{{prefix}}wj_books_rejected_images`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `rejected_book_id` bigint(20) UNSIGNED NOT NULL COMMENT '拒收书籍ID',
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '审核图片URL',
  `image_type` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '图片类型：1封面，2破损，3污渍，4其他',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_rejected_book`(`rejected_book_id`) USING BTREE,
  INDEX `idx_site_id`(`site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '拒收书籍审核图片表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_retrieve_apply`;
CREATE TABLE `{{prefix}}wj_books_retrieve_apply`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `site_id` int(10) UNSIGNED NOT NULL COMMENT '站点ID',
  `order_id` bigint(20) UNSIGNED NOT NULL COMMENT '订单ID',
  `member_id` bigint(20) UNSIGNED NOT NULL COMMENT '会员ID',
  `address_id` bigint(20) UNSIGNED NOT NULL COMMENT '收货地址ID',
  `rejected_book_ids` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '申请取回的拒收书籍ID，多个用逗号分隔',
  `express_waybill` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '退回物流单号',
  `express_company` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '退回物流公司',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态：0申请中，1已发货，2已完成，3已取消',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '备注',
  `create_time` datetime NOT NULL COMMENT '申请时间',
  `ship_time` datetime NULL DEFAULT NULL COMMENT '发货时间',
  `complete_time` datetime NULL DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_site_order`(`site_id`, `order_id`) USING BTREE,
  INDEX `idx_site_member`(`site_id`, `member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '不合格书籍取回申请表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `{{prefix}}wj_books_scan_records`;
CREATE TABLE `{{prefix}}wj_books_scan_records`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `site_id` int(11) NOT NULL COMMENT '站点ID',
  `member_id` int(11) NOT NULL COMMENT '用户ID',
  `isbn` varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'ISBN码',
  `scan_time` datetime NULL DEFAULT NULL COMMENT '扫描时间',
  `scan_type` tinyint(1) NULL DEFAULT 1 COMMENT '扫描方式(1:扫码,2:手动输入)',
  `status` tinyint(1) NULL DEFAULT 0 COMMENT '状态(0:仅扫描,1:已加入回收车,2:已提交回收)',
  `create_time` datetime NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_user_isbn`(`member_id`, `isbn`) USING BTREE,
  INDEX `idx_scan_time`(`scan_time`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '图书扫描记录表' ROW_FORMAT = Dynamic;
