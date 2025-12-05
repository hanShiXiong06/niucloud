-- ----------------------------
-- Table structure for {{prefix}}aiimage_card
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_card`;
CREATE TABLE `{{prefix}}aiimage_card`
(
    `id`          int(13) NOT NULL AUTO_INCREMENT,
    `site_id`     int(13) NULL DEFAULT NULL COMMENT '站点ID',
    `member_id`   int(13) NULL DEFAULT NULL COMMENT '会员',
    `card_num`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '卡号',
    `point`       int(13) NULL DEFAULT NULL COMMENT '点数',
    `is_use`      int(1) NULL DEFAULT 0 COMMENT '是否使用',
    `use_time`    int(13) NULL DEFAULT NULL COMMENT '使用时间',
    `is_export`   int(13) NULL DEFAULT NULL COMMENT '是否导出',
    `pid`         int(11) NULL DEFAULT NULL COMMENT '所属用户',
    `expire_time` int(11) NULL DEFAULT NULL COMMENT '到期时间',
    `create_time` int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1614 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '卡密兑换' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_create
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_create`;
CREATE TABLE `{{prefix}}aiimage_create`
(
    `id`           int(13) NOT NULL AUTO_INCREMENT,
    `site_id`      int(13) NULL DEFAULT NULL COMMENT '站点ID',
    `task_id`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '任务id',
    `member_id`    int(13) NULL DEFAULT NULL COMMENT '会员',
    `model_id`     int(13) NULL DEFAULT NULL COMMENT '模型',
    `prompt`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '提示词',
    `image_urls`   text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '待修改图像',
    `aspect_ratio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '比列',
    `images`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '生成图像',
    `status`       int(13) NULL DEFAULT NULL COMMENT '状态',
    `point`        int(13) NULL DEFAULT NULL COMMENT '消耗积分',
    `is_self`      int(13) NULL DEFAULT NULL COMMENT '接口',
    `state`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '状态',
    `msg`          varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '描述',
    `platform`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '渠道',
    `channel`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '模型通道',
    `create_time`  int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 59 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '作品列表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_fenxiao_member
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_fenxiao_member`;
CREATE TABLE `{{prefix}}aiimage_fenxiao_member`
(
    `member_id`         int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`           int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `pid`               int(11) NULL DEFAULT 0 COMMENT '推荐会员id(分销)',
    `fenxiao_member_id` int(11) NULL DEFAULT 0 COMMENT '会员上级分销商会员id',
    `is_fenxiao`        tinyint(4) NULL DEFAULT 0 COMMENT '是否是分销商',
    `bind_time`         int(11) NULL DEFAULT 0 COMMENT '绑定时间',
    `create_time`       int(11) NULL DEFAULT 0 COMMENT '创建时间',
    PRIMARY KEY (`member_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '分销会员' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_fenxiao_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_fenxiao_order`;
CREATE TABLE `{{prefix}}aiimage_fenxiao_order`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
    `member_id`        int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员id',
    `site_id`          int(11) NOT NULL DEFAULT 0 COMMENT '站点id',
    `order_id`         varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '会员id',
    `name`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '名称',
    `type`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '类型',
    `state`            int(11) NULL DEFAULT NULL COMMENT '订单状态',
    `first_commission` decimal(10, 2) NULL DEFAULT NULL COMMENT '一级佣金',
    `two_commission`   decimal(10, 2) NULL DEFAULT NULL COMMENT '二级佣金',
    `status`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '状态',
    `first_member_id`  int(13) NULL DEFAULT NULL COMMENT '一级分销',
    `two_member_id`    int(13) NULL DEFAULT NULL COMMENT '二级分销',
    `is_js`            int(1) NULL DEFAULT 0 COMMENT '是否结算',
    `create_time`      int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
    `update_time`      int(11) NULL DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '分销订单' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_help
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_help`;
CREATE TABLE `{{prefix}}aiimage_help`
(
    `id`          int(13) NOT NULL AUTO_INCREMENT,
    `site_id`     int(13) NULL DEFAULT NULL COMMENT '站点ID',
    `cat_id`      int(13) NULL DEFAULT NULL COMMENT '分类',
    `title`       varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '名称',
    `image`       varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '封面',
    `desc`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '描述',
    `content`     longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '详情',
    `view_num`    int(11) NULL DEFAULT NULL COMMENT '浏览量',
    `sort`        int(11) NULL DEFAULT 0 COMMENT '排序',
    `create_time` int(13) NULL DEFAULT NULL COMMENT '创建时间',
    `update_time` int(13) NULL DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`) USING BTREE,
    INDEX         `id`(`id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '帮助中心' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_model
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_model`;
CREATE TABLE `{{prefix}}aiimage_model`
(
    `id`              int(13) NOT NULL AUTO_INCREMENT,
    `site_id`         int(13) NULL DEFAULT NULL COMMENT '站点ID',
    `name`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '名称',
    `key`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '关键字',
    `logo`            varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '图像',
    `desc`            varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '描述',
    `prompt`          text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '提示词',
    `sort`            int(13) NULL DEFAULT NULL COMMENT '排序',
    `demo_image`      varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '演示图像',
    `status`          int(13) NULL DEFAULT NULL COMMENT '状态',
    `point`           int(13) NULL DEFAULT NULL COMMENT '消耗积分',
    `is_upload_image` int(1) NULL DEFAULT 1 COMMENT '是否需要上传图像',
    `limit_image`     int(13) NULL DEFAULT NULL COMMENT '图像限制',
    `is_prompt`       int(1) NULL DEFAULT NULL COMMENT '允许提示词',
    `is_vip`          int(13) NULL DEFAULT NULL COMMENT 'VIP专享',
    `model`           varchar(255) DEFAULT NULL COMMENT '模型',
    `create_time`     int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '智能体' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_order`;
CREATE TABLE `{{prefix}}aiimage_order`
(
    `id`           int(13) NOT NULL AUTO_INCREMENT,
    `site_id`      int(13) NULL DEFAULT NULL COMMENT '站点ID',
    `member_id`    int(13) NULL DEFAULT NULL COMMENT '会员',
    `package_id`   int(13) NULL DEFAULT NULL COMMENT '套餐id',
    `order_id`     varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单ID',
    `name`         varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '名称',
    `image`        varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '图像',
    `order_money`  decimal(10, 2) NULL DEFAULT NULL COMMENT '价格',
    `point`        int(13) NULL DEFAULT NULL COMMENT '点数',
    `num`          int(13) NULL DEFAULT NULL COMMENT '生成卡密数量',
    `type`         varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '类型',
    `day`          int(13) NULL DEFAULT NULL COMMENT '天数',
    `status`       int(13) NULL DEFAULT NULL COMMENT '状态',
    `out_trade_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '订单交易号',
    `pay_time`     int(13) NULL DEFAULT NULL COMMENT '支付时间',
    `pid`          int(11) NULL DEFAULT NULL COMMENT '邀请者',
    `create_time`  int(13) NULL DEFAULT NULL COMMENT '创建时间',
    `close_time`   int(13) NULL DEFAULT NULL COMMENT '关闭时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 21 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单列表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}aiimage_package
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}aiimage_package`;
CREATE TABLE `{{prefix}}aiimage_package`
(
    `id`          int(13) NOT NULL AUTO_INCREMENT,
    `site_id`     int(13) NULL DEFAULT NULL COMMENT '站点ID',
    `name`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '名称',
    `image`       varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '图像',
    `price`       decimal(10, 2) NULL DEFAULT NULL COMMENT '价格',
    `point`       int(13) NULL DEFAULT NULL COMMENT '点数',
    `num`         int(13) NULL DEFAULT NULL,
    `type`        varchar(13) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '类型',
    `day`         int(13) NULL DEFAULT 0 COMMENT '天数',
    `limit`       int(13) NULL DEFAULT 0 COMMENT '限制',
    `status`      int(1) NULL DEFAULT NULL COMMENT '状态',
    `sort`        int(13) NULL DEFAULT NULL COMMENT '排序',
    `create_time` int(13) NULL DEFAULT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '套餐列表' ROW_FORMAT = Dynamic;

