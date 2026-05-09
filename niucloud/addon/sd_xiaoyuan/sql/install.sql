/*
 Navicat Premium Dump SQL

 Source Server         : hostlocal 3307
 Source Server Type    : MySQL
 Source Server Version : 50726 (5.7.26)
 Source Host           : localhost:3307
 Source Schema         : niuaddon8

 Target Server Type    : MySQL
 Target Server Version : 50726 (5.7.26)
 File Encoding         : 65001

 Date: 25/02/2026 22:54:38
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_address
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_address`;
CREATE TABLE `{{prefix}}xiaoyuan_address`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系人',
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '手机号',
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '详细地址',
  `address_type` enum('DORM','TEACHING','EXPRESS','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'OTHER' COMMENT '地址类型:DORM宿舍,TEACHING教学楼,EXPRESS快递点,OTHER其他',
  `building` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '楼栋',
  `room` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '房间号',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '经度',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '纬度',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认:0否,1是',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '常用地址表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_appeal
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_appeal`;
CREATE TABLE `{{prefix}}xiaoyuan_appeal`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '跑腿员ID',
  `appeal_type` enum('CANCEL','REVIEW') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '申诉类型:CANCEL恶意取消,REVIEW恶意差评',
  `content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '申诉内容',
  `images` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '申诉图片',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待处理,1已通过,2已拒绝',
  `reply` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '处理回复',
  `handle_time` int(11) NULL DEFAULT 0 COMMENT '处理时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `runner_id`(`runner_id`, `status`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '申诉表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_campus
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_campus`;
CREATE TABLE `{{prefix}}xiaoyuan_campus`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '学校ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '校区名称',
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '校区地址',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '经度',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '纬度',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `school_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '校区表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_campus_auth
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_campus_auth`;
CREATE TABLE `{{prefix}}xiaoyuan_campus_auth`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `school_id` int(11) NULL DEFAULT 0 COMMENT '学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '校区',
  `campus_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '校园名称',
  `real_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '真实姓名',
  `id_card` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '身份证号',
  `student_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '学号/工号',
  `college` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '学院',
  `major` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '专业',
  `grade` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '年级',
  `class_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '班级',
  `identity_type` enum('STUDENT','TEACHER','STAFF') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'STUDENT' COMMENT '身份类型:STUDENT学生,TEACHER教师,STAFF教职工',
  `cert_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '证件照片',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待审核,1已通过,2已拒绝',
  `refuse_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '拒绝原因',
  `audit_time` int(11) NULL DEFAULT 0 COMMENT '审核时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '校园认证表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_comment
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_comment`;
CREATE TABLE `{{prefix}}xiaoyuan_comment`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '评论者ID',
  `target_type` enum('COMMUNITY','CONFESSION') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '目标类型:COMMUNITY树洞,CONFESSION表白墙',
  `target_id` int(11) NOT NULL DEFAULT 0 COMMENT '目标ID',
  `parent_id` int(11) NOT NULL DEFAULT 0 COMMENT '父评论ID',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '评论内容',
  `images` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图片(JSON数组)',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0待审核,1已发布,2已删除',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `target`(`target_type`, `target_id`, `status`) USING BTREE,
  INDEX `parent_id`(`parent_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '评论表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_community
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_community`;
CREATE TABLE `{{prefix}}xiaoyuan_community`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布者ID',
  `school_id` int(11) NULL DEFAULT 0 COMMENT '学校ID',
  `category_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类ID',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '内容',
  `images` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图片(JSON数组)',
  `video` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '视频地址',
  `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `comment_count` int(11) NOT NULL DEFAULT 0 COMMENT '评论数',
  `is_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶:0否,1是',
  `is_recommend` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否推荐:0否,1是',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0待审核,1已发布,2已拒绝,3已删除',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `category_id`(`category_id`, `status`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`, `is_top`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '树洞帖子表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_community_category
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_community_category`;
CREATE TABLE `{{prefix}}xiaoyuan_community_category`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图标',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '树洞分类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_community_comment
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_community_comment`;
CREATE TABLE `{{prefix}}xiaoyuan_community_comment`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0 COMMENT 'post id',
  `member_id` int(11) NOT NULL DEFAULT 0,
  `parent_id` int(11) NOT NULL DEFAULT 0 COMMENT 'parent comment id',
  `reply_member_id` int(11) NOT NULL DEFAULT 0 COMMENT 'reply to member id',
  `content` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_community`(`post_id`) USING BTREE,
  INDEX `idx_member`(`member_id`) USING BTREE,
  INDEX `idx_site`(`site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'community comments' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_complaint
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_complaint`;
CREATE TABLE `{{prefix}}xiaoyuan_complaint`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `complaint_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '投诉单号',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '投诉人ID',
  `target_member_id` int(11) NOT NULL DEFAULT 0 COMMENT '被投诉人ID',
  `biz_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务类型',
  `biz_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务ID',
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '投诉原因',
  `images` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '证据图片',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待处理,1处理中,2已完结',
  `result` tinyint(1) NULL DEFAULT NULL COMMENT '处理结果:1投诉成立,2投诉不成立',
  `handle_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '处理内容',
  `handle_time` int(11) NULL DEFAULT 0 COMMENT '处理时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `complaint_no`(`complaint_no`) USING BTREE,
  INDEX `member_id`(`member_id`) USING BTREE,
  INDEX `target_member_id`(`target_member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '投诉仲裁表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_confession
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_confession`;
CREATE TABLE `{{prefix}}xiaoyuan_confession`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布者ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '校区',
  `target_type` enum('PERSON','GROUP') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PERSON' COMMENT '目标类型:PERSON个人,GROUP群体',
  `target_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '目标名称',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '表白内容',
  `images` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图片(JSON数组)',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否匿名:0否,1是',
  `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `comment_count` int(11) NOT NULL DEFAULT 0 COMMENT '评论数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0待审核,1已发布,2已拒绝,3已删除',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'ALL' COMMENT '类型:ALL-全部,CRUSH-暗恋,REAL-实名,FIND-寻人,WISH-祝福',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `campus`, `status`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '表白墙表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_confession_comment
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_confession_comment`;
CREATE TABLE `{{prefix}}xiaoyuan_confession_comment`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `confession_id` int(11) NOT NULL DEFAULT 0,
  `member_id` int(11) NOT NULL DEFAULT 0,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_confession`(`confession_id`) USING BTREE,
  INDEX `idx_member`(`member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_coupon
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_coupon`;
CREATE TABLE `{{prefix}}xiaoyuan_coupon`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '优惠券名称',
  `type` enum('REDUCE','DISCOUNT') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'REDUCE' COMMENT '类型:REDUCE满减券,DISCOUNT折扣券',
  `discount_value` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额/折扣',
  `min_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '最低消费',
  `total_count` int(11) NOT NULL DEFAULT 0 COMMENT '发放总数',
  `received_count` int(11) NOT NULL DEFAULT 0 COMMENT '已领取数',
  `used_count` int(11) NOT NULL DEFAULT 0 COMMENT '已使用数',
  `limit_per_user` int(11) NOT NULL DEFAULT 1 COMMENT '每人限领',
  `valid_days` int(11) NOT NULL DEFAULT 7 COMMENT '有效天数',
  `start_time` int(11) NULL DEFAULT 0 COMMENT '开始时间',
  `end_time` int(11) NULL DEFAULT 0 COMMENT '结束时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '优惠券表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_coupon_record
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_coupon_record`;
CREATE TABLE `{{prefix}}xiaoyuan_coupon_record`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `coupon_id` int(11) NOT NULL DEFAULT 0 COMMENT '优惠券ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '使用订单ID',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0未使用,1已使用,2已过期',
  `expire_time` int(11) NULL DEFAULT 0 COMMENT '过期时间',
  `use_time` int(11) NULL DEFAULT 0 COMMENT '使用时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `status`) USING BTREE,
  INDEX `coupon_id`(`coupon_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '优惠券领取记录' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_credit
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_credit`;
CREATE TABLE `{{prefix}}xiaoyuan_credit`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `credit_score` int(11) NOT NULL DEFAULT 100 COMMENT '信誉分(初始100分)',
  `total_complete` int(11) NOT NULL DEFAULT 0 COMMENT '累计完成订单数',
  `total_cancel` int(11) NOT NULL DEFAULT 0 COMMENT '累计取消订单数',
  `total_complaint` int(11) NOT NULL DEFAULT 0 COMMENT '累计被投诉成立次数',
  `is_restricted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否受限:0否,1是(低于60分)',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `member_id`(`member_id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '用户信誉分表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_credit_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_credit_log`;
CREATE TABLE `{{prefix}}xiaoyuan_credit_log`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `type` enum('COMPLETE','CANCEL','COMPLAINT','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '类型:COMPLETE订单完成,CANCEL取消订单,COMPLAINT投诉成立,ADMIN管理员调整',
  `change_score` int(11) NOT NULL DEFAULT 0 COMMENT '变动分数(正数加分,负数扣分)',
  `before_score` int(11) NOT NULL DEFAULT 0 COMMENT '变动前分数',
  `after_score` int(11) NOT NULL DEFAULT 0 COMMENT '变动后分数',
  `biz_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '业务类型',
  `biz_id` int(11) NULL DEFAULT 0 COMMENT '业务ID',
  `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '备注',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '信誉分变动记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_escrow
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_escrow`;
CREATE TABLE `{{prefix}}xiaoyuan_escrow`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `escrow_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '担保单号',
  `biz_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '业务类型:TASK任务,SECONDHAND二手',
  `biz_id` int(11) NOT NULL DEFAULT 0 COMMENT '业务ID',
  `payer_id` int(11) NOT NULL DEFAULT 0 COMMENT '付款人ID',
  `payee_id` int(11) NOT NULL DEFAULT 0 COMMENT '收款人ID',
  `amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '担保金额',
  `platform_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '平台服务费',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待支付,1担保中,2已释放,3已退款,4争议中',
  `pay_time` int(11) NULL DEFAULT 0 COMMENT '支付时间',
  `release_time` int(11) NULL DEFAULT 0 COMMENT '释放时间',
  `refund_time` int(11) NULL DEFAULT 0 COMMENT '退款时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `escrow_no`(`escrow_no`) USING BTREE,
  INDEX `biz`(`biz_type`, `biz_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '担保交易表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_evaluate
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_evaluate`;
CREATE TABLE `{{prefix}}xiaoyuan_evaluate`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '评价用户',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '被评价跑腿员',
  `score` tinyint(1) NOT NULL DEFAULT 5 COMMENT '评分:1-5星',
  `service_score` tinyint(1) NOT NULL DEFAULT 5 COMMENT '服务评分',
  `speed_score` tinyint(1) NOT NULL DEFAULT 5 COMMENT '速度评分',
  `content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '评价内容',
  `images` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '评价图片',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否匿名:0否,1是',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `runner_id`(`runner_id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '评价表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_express_station
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_express_station`;
CREATE TABLE `{{prefix}}xiaoyuan_express_station`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `logo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `express_company` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `business_hours` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '08:00-20:00',
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `school_id`(`school_id`, `site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 52 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '快递站点表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_game_category
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_game_category`;
CREATE TABLE `{{prefix}}xiaoyuan_game_category`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '类型名称',
  `icon` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '类型图标',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '游戏类型分类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_game_companion
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_game_companion`;
CREATE TABLE `{{prefix}}xiaoyuan_game_companion`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `member_id` int(11) NOT NULL DEFAULT 0,
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `game_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'LOL,WZRY,PUBG,CSGO,YS,EGG,OTHER',
  `game_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '游戏昵称/ID',
  `rank_level` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '段位/等级',
  `service_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'PLAY_WITH,BOOST,TEACH,TEAM',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `images` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '价格/小时',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '小时' COMMENT '计价单位',
  `voice_chat` tinyint(1) NULL DEFAULT 1 COMMENT '是否语音',
  `gender` tinyint(1) NULL DEFAULT 0 COMMENT '0不限1男2女',
  `online_time` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '在线时间段',
  `school_id` int(11) NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待审核1上架2下架3拒绝',
  `refuse_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `view_count` int(11) NULL DEFAULT 0,
  `order_count` int(11) NULL DEFAULT 0,
  `score` decimal(3, 1) NULL DEFAULT 5.0,
  `is_top` tinyint(1) NULL DEFAULT 0,
  `create_time` int(11) NULL DEFAULT 0,
  `update_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_site_id`(`site_id`) USING BTREE,
  INDEX `idx_member_id`(`member_id`) USING BTREE,
  INDEX `idx_game_type`(`game_type`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '游戏陪玩' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_group_member
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_group_member`;
CREATE TABLE `{{prefix}}xiaoyuan_group_member`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `group_id` int(11) NOT NULL DEFAULT 0 COMMENT '拼单ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '参与者ID',
  `is_leader` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否团长:0否,1是',
  `order_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '点单内容',
  `amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态:0未支付,1已支付',
  `pay_time` int(11) NULL DEFAULT 0 COMMENT '支付时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待支付,1已参与,2已退出',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `group_id`(`group_id`) USING BTREE,
  INDEX `member_id`(`member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '拼单参与者表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_group_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_group_order`;
CREATE TABLE `{{prefix}}xiaoyuan_group_order`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `group_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '拼单编号',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发起人ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布人学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '发布人校区',
  `group_type` enum('TEA','FOOD','FRUIT','RIDE','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '拼单类型:TEA拼奶茶,FOOD拼外卖,FRUIT拼水果,RIDE拼车,OTHER其他',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '拼单标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '拼单描述',
  `images` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '拼单图片',
  `shop_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商家名称',
  `shop_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商家地址',
  `delivery_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '配送地址',
  `delivery_lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '配送经度',
  `delivery_lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '配送纬度',
  `min_members` int(11) NOT NULL DEFAULT 2 COMMENT '最少人数',
  `max_members` int(11) NOT NULL DEFAULT 10 COMMENT '最多人数',
  `current_members` int(11) NOT NULL DEFAULT 1 COMMENT '当前人数',
  `per_price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '人均价格',
  `total_price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '总价格',
  `delivery_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '配送费',
  `deadline` int(11) NULL DEFAULT 0 COMMENT '截止时间',
  `status` tinyint(2) NOT NULL DEFAULT 0 COMMENT '状态:0拼单中,10已成团,20配送中,30已完成,90已取消,91拼单失败',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `group_no`(`group_no`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `campus`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '拼单好饭表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_house
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_house`;
CREATE TABLE `{{prefix}}xiaoyuan_house`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布者ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '校区',
  `house_type` enum('RENT','SHARE') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'RENT' COMMENT '类型:RENT整租,SHARE合租',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '详细描述',
  `images` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图片(JSON数组)',
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '详细地址',
  `rent_type` enum('MONTH','QUARTER','YEAR') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'MONTH' COMMENT '租期类型:MONTH月租,QUARTER季租,YEAR年租',
  `rent_price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '租金',
  `deposit` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '押金',
  `area` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT '面积(平方米)',
  `rooms` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '房型(如3室1厅)',
  `floor` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '楼层',
  `nearby_schools` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '附近学校ID(逗号分隔)',
  `facilities` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '配套设施(逗号分隔)',
  `cover_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '封面图',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '经度',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '纬度',
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系人',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '联系电话',
  `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0下架,1出租中,2已租出',
  `refuse_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '审核拒绝原因',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `campus`, `status`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '房屋租赁表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_house_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_house_order`;
CREATE TABLE `{{prefix}}xiaoyuan_house_order`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `member_id` int(11) NOT NULL DEFAULT 0,
  `house_id` int(11) NOT NULL DEFAULT 0,
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=pending 1=accepted 2=rejected 3=cancelled',
  `deposit_refunded` tinyint(1) NULL DEFAULT 0 COMMENT '押金是否已退',
  `deposit_refund_time` int(11) NULL DEFAULT 0 COMMENT '押金退款时间',
  `refund_status` tinyint(1) NULL DEFAULT 0 COMMENT '退款状态',
  `refund_time` int(11) NULL DEFAULT 0 COMMENT '退款时间',
  `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `create_time` int(11) NULL DEFAULT 0,
  `update_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_site`(`site_id`) USING BTREE,
  INDEX `idx_member`(`member_id`) USING BTREE,
  INDEX `idx_house`(`house_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'house orders' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_lost_found
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_lost_found`;
CREATE TABLE `{{prefix}}xiaoyuan_lost_found`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布者ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布人学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '发布人校区',
  `type` enum('LOST','FOUND') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '类型:LOST我丢了,FOUND我捡到了',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '物品分类',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '详细描述',
  `images` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '图片',
  `lost_time` int(11) NULL DEFAULT 0 COMMENT '丢失/捡到时间',
  `lost_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '丢失/捡到地点',
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系人',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系电话',
  `reward` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '悬赏金额(仅丢失)',
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否急寻:0否,1是',
  `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0已关闭,1进行中,2已找到/已归还',
  `refuse_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '审核拒绝原因',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `campus`, `type`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '失物招领表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_lost_found_category
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_lost_found_category`;
CREATE TABLE `{{prefix}}xiaoyuan_lost_found_category`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `icon` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '分类图标',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '失物招领分类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_member_relation
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_member_relation`;
CREATE TABLE `{{prefix}}xiaoyuan_member_relation`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT '上级用户ID',
  `pid2` int(11) NOT NULL DEFAULT 0 COMMENT '上上级用户ID',
  `level` tinyint(1) NOT NULL DEFAULT 1 COMMENT '层级',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `pid`(`pid`, `site_id`) USING BTREE,
  INDEX `pid2`(`pid2`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '邀请关系表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_message
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_message`;
CREATE TABLE `{{prefix}}xiaoyuan_message`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '接收用户ID',
  `from_member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发送用户ID(0为系统)',
  `type` enum('SYSTEM','ORDER','COMMUNITY','COMMENT','TASK','GROUP','SECONDHAND','LOSTFOUND','WALLET') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '消息类型',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '消息标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '消息内容',
  `extra` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '扩展数据(JSON)',
  `link_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '跳转类型',
  `link_id` int(11) NULL DEFAULT 0 COMMENT '跳转ID',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已读:0未读,1已读',
  `read_time` int(11) NULL DEFAULT 0 COMMENT '阅读时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `is_read`) USING BTREE,
  INDEX `type`(`type`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '系统消息表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_order`;
CREATE TABLE `{{prefix}}xiaoyuan_order`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '订单编号',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '下单用户',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '接单跑腿员',
  `school_id` int(11) NULL DEFAULT 0 COMMENT '发布人学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '发布人校区',
  `task_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务类型',
  `status` tinyint(2) NOT NULL DEFAULT 0 COMMENT '状态:0待支付,10待接单,20已接单,30取货中,40配送中,50已完成,90已取消,91已退款',
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否加急:0否,1是',
  `is_appointment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否预约:0即时,1预约',
  `appointment_time` int(11) NULL DEFAULT 0 COMMENT '预约时间',
  `pickup_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '取件人姓名',
  `pickup_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '取件人手机',
  `pickup_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '取件地址',
  `pickup_lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件经度',
  `pickup_lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件纬度',
  `receive_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '收件人姓名',
  `receive_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '收件人手机',
  `receive_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '收件地址',
  `receive_lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '收件经度',
  `receive_lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '收件纬度',
  `express_company` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '快递公司',
  `express_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '快递单号',
  `pickup_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件码',
  `goods_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商品名称',
  `goods_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商品图片',
  `task_desc` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '任务描述',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '用户备注',
  `yinsi_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '隐私信息(接单后对接单员可见)',
  `distance` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '距离(公里)',
  `weight` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '重量(公斤)',
  `base_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '基础费用',
  `distance_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '距离费用',
  `weight_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '重量费用',
  `urgent_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '加急费用',
  `tip_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '小费',
  `total_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '订单总额',
  `coupon_discount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '优惠券抵扣',
  `actual_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '实付金额',
  `runner_income` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '跑腿员收益',
  `platform_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '平台抽成',
  `commission_rate` decimal(5, 2) NOT NULL DEFAULT 0.00 COMMENT '抽成比例',
  `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态:0未支付,1已支付',
  `pay_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '支付方式',
  `pay_time` int(11) NULL DEFAULT 0 COMMENT '支付时间',
  `accept_time` int(11) NULL DEFAULT 0 COMMENT '接单时间',
  `pickup_time` int(11) NULL DEFAULT 0 COMMENT '取货时间',
  `delivery_time` int(11) NULL DEFAULT 0 COMMENT '开始配送时间',
  `complete_time` int(11) NULL DEFAULT 0 COMMENT '完成时间',
  `auto_confirm_time` int(11) NOT NULL DEFAULT 0 COMMENT '自动确认时间',
  `confirm_time` int(11) NOT NULL DEFAULT 0 COMMENT '确认完成时间',
  `confirm_source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '确认来源 USER/AUTO/ADMIN',
  `cancel_time` int(11) NULL DEFAULT 0 COMMENT '取消时间',
  `cancel_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取消原因',
  `cancel_role` enum('USER','RUNNER','SYSTEM','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '取消方:USER用户,RUNNER跑腿员,SYSTEM系统,ADMIN管理员',
  `refund_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '退款金额',
  `refund_time` int(11) NULL DEFAULT 0 COMMENT '退款时间',
  `refund_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '退款原因',
  `expire_pay_time` int(11) NULL DEFAULT 0 COMMENT '支付过期时间',
  `expire_accept_time` int(11) NULL DEFAULT 0 COMMENT '接单过期时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  `ext` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `order_no`(`order_no`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `runner_id`(`runner_id`, `site_id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`, `task_type`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '校园帮订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_order_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_order_log`;
CREATE TABLE `{{prefix}}xiaoyuan_order_log`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '订单ID',
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '操作类型',
  `content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '操作内容',
  `role` enum('USER','RUNNER','SYSTEM','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '操作角色',
  `operator_id` int(11) NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '订单日志表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_package_price
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_package_price`;
CREATE TABLE `{{prefix}}xiaoyuan_package_price`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `size` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `price` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '包裹规格价格表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_points_exchange
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_points_exchange`;
CREATE TABLE `{{prefix}}xiaoyuan_points_exchange`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '兑换用户ID',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID',
  `exchange_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '兑换单号',
  `goods_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商品名称',
  `goods_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商品图片',
  `points_used` int(11) NOT NULL DEFAULT 0 COMMENT '使用积分',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '兑换数量',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待领取,1已领取,2已完成,3已取消',
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系人姓名',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系电话',
  `delivery_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '领取地址',
  `delivery_campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '领取校区',
  `delivery_time` int(11) NULL DEFAULT 0 COMMENT '领取时间',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '备注',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `exchange_no`(`exchange_no`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `goods_id`(`goods_id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '积分兑换记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_points_goods
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_points_goods`;
CREATE TABLE `{{prefix}}xiaoyuan_points_goods`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商品名称',
  `image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商品图片',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '商品描述',
  `points_price` int(11) NOT NULL DEFAULT 0 COMMENT '所需积分',
  `market_price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '市场价',
  `category_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类ID',
  `stock` int(11) NOT NULL DEFAULT 0 COMMENT '库存',
  `sold_count` int(11) NOT NULL DEFAULT 0 COMMENT '已售数量',
  `exchange_count` int(11) NOT NULL DEFAULT 0 COMMENT '已兑换数量',
  `exchange_limit` int(11) NOT NULL DEFAULT 0 COMMENT '每人限兑数量(0不限)',
  `is_recommend` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否推荐',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0下架,1上架',
  `start_time` int(11) NULL DEFAULT 0 COMMENT '开始时间',
  `end_time` int(11) NULL DEFAULT 0 COMMENT '结束时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '积分商品表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_points_goods_category
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_points_goods_category`;
CREATE TABLE `{{prefix}}xiaoyuan_points_goods_category`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `icon` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '分类图标',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '积分商品分类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_points_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_points_order`;
CREATE TABLE `{{prefix}}xiaoyuan_points_order`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '订单编号',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID',
  `goods_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商品名称',
  `goods_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '商品图片',
  `points_price` int(11) NOT NULL DEFAULT 0 COMMENT '消耗积分',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '兑换数量',
  `receiver_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '收货人',
  `receiver_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '收货电话',
  `receiver_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '收货地址',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待发货,1已发货,2已完成,9已取消',
  `express_company` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '快递公司',
  `express_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '快递单号',
  `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '备注',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '积分兑换订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_runner
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_runner`;
CREATE TABLE `{{prefix}}xiaoyuan_runner`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `real_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '真实姓名',
  `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '手机号',
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '头像',
  `student_cert` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '学生证照片',
  `level` tinyint(1) NOT NULL DEFAULT 1 COMMENT '等级:1普通,2优先推荐',
  `level_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '新手接单员' COMMENT '等级名称',
  `commission_rate` tinyint(3) NULL DEFAULT 70 COMMENT '佣金比例(%)',
  `completed_orders` int(11) NULL DEFAULT 0 COMMENT '已完成订单数',
  `school_id` int(11) NULL DEFAULT 0 COMMENT '所属学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '所属校区',
  `invite_reward_granted` tinyint(1) NULL DEFAULT 0 COMMENT '邀请奖励是否已发放:0否,1是',
  `total_orders` int(11) NOT NULL DEFAULT 0 COMMENT '总接单数',
  `complete_orders` int(11) NOT NULL DEFAULT 0 COMMENT '完成订单数',
  `score` decimal(3, 1) NOT NULL DEFAULT 5.0 COMMENT '评分',
  `balance` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '余额',
  `freeze_balance` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '冻结金额',
  `total_income` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '累计收益',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0待审核,1已通过,2已拒绝,3已禁用',
  `refuse_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '拒绝原因',
  `audit_time` int(11) NULL DEFAULT 0 COMMENT '审核时间',
  `is_online` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否在线:0离线,1在线',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '经度',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '纬度',
  `last_location_time` int(11) NULL DEFAULT 0 COMMENT '最后定位时间',
  `daily_order_limit` int(11) NOT NULL DEFAULT 20 COMMENT '每日接单上限',
  `today_orders` int(11) NOT NULL DEFAULT 0 COMMENT '今日接单数',
  `accept_types` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '接单服务类型(JSON数组)',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `member_id`(`member_id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`, `is_online`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '跑腿员表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_runner_balance_log
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_runner_balance_log`;
CREATE TABLE `{{prefix}}xiaoyuan_runner_balance_log`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '跑腿员ID',
  `type` enum('INCOME','WITHDRAW','FREEZE','UNFREEZE','REFUND') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '类型:INCOME收入,WITHDRAW提现,FREEZE冻结,UNFREEZE解冻,REFUND退款',
  `amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `balance` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '余额',
  `order_id` int(11) NULL DEFAULT 0 COMMENT '关联订单ID',
  `remark` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '备注',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `runner_id`(`runner_id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '跑腿员余额日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_runner_level
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_runner_level`;
CREATE TABLE `{{prefix}}xiaoyuan_runner_level`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `level` tinyint(2) NOT NULL DEFAULT 1 COMMENT '等级',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '等级名称',
  `min_orders` int(11) NOT NULL DEFAULT 0 COMMENT '最低订单数',
  `commission_rate` tinyint(3) NOT NULL DEFAULT 70 COMMENT '默认佣金比例(%)',
  `rate_express` tinyint(3) NULL DEFAULT NULL COMMENT '代取快递佣金比例(%)',
  `rate_buy` tinyint(3) NULL DEFAULT NULL COMMENT '代买服务佣金比例(%)',
  `rate_errand` tinyint(3) NULL DEFAULT NULL COMMENT '跑腿佣金比例(%)',
  `rate_send` tinyint(3) NULL DEFAULT NULL COMMENT '帮我送佣金比例(%)',
  `rate_print` tinyint(3) NULL DEFAULT NULL COMMENT '代打印佣金比例(%)',
  `rate_queue` tinyint(3) NULL DEFAULT NULL COMMENT '代排队佣金比例(%)',
  `rate_seat` tinyint(3) NULL DEFAULT NULL COMMENT '代占座佣金比例(%)',
  `rate_carry` tinyint(3) NULL DEFAULT NULL COMMENT '帮搬运佣金比例(%)',
  `rate_trash` tinyint(3) NULL DEFAULT NULL COMMENT '扔垃圾佣金比例(%)',
  `rate_clean` tinyint(3) NULL DEFAULT NULL COMMENT '代清洁佣金比例(%)',
  `rate_help` tinyint(3) NULL DEFAULT NULL COMMENT '帮帮忙佣金比例(%)',
  `rate_game` tinyint(3) NULL DEFAULT NULL COMMENT '游戏陪练佣金比例(%)',
  `rate_group` tinyint(3) NULL DEFAULT NULL COMMENT '拼单佣金比例(%)',
  `icon` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '等级图标',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_level`(`site_id`, `level`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '接单员等级配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_runner_location
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_runner_location`;
CREATE TABLE `{{prefix}}xiaoyuan_runner_location`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '接单员ID',
  `latitude` decimal(10, 6) NOT NULL DEFAULT 0.000000 COMMENT '纬度',
  `longitude` decimal(10, 6) NOT NULL DEFAULT 0.000000 COMMENT '经度',
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '地址',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `site_runner`(`site_id`, `runner_id`) USING BTREE,
  INDEX `location`(`latitude`, `longitude`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '接单员位置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_schedule
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_schedule`;
CREATE TABLE `{{prefix}}xiaoyuan_schedule`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '学校ID',
  `class_id` int(11) NULL DEFAULT 0 COMMENT '关联班级ID',
  `semester` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '学期(如2024-2025-1)',
  `week_start` date NULL DEFAULT NULL COMMENT '学期开始日期',
  `total_weeks` int(11) NOT NULL DEFAULT 20 COMMENT '总周数',
  `schedule_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '课表数据(JSON)',
  `is_custom` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0使用班级课表 1已自定义',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `semester`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '课表表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_schedule_setting
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_schedule_setting`;
CREATE TABLE `{{prefix}}xiaoyuan_schedule_setting`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `start_date` date NULL DEFAULT NULL COMMENT '学期开始日期',
  `end_date` date NULL DEFAULT NULL COMMENT '学期结束日期',
  `total_weeks` int(11) NOT NULL DEFAULT 20 COMMENT '总周数',
  `sections` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '上课时间段JSON',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `member_id`(`member_id`, `site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '课表设置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_class
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_class`;
CREATE TABLE `{{prefix}}xiaoyuan_class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `department_id` int(11) NOT NULL DEFAULT 0 COMMENT '院系ID(预留)',
  `major_id` int(11) NOT NULL DEFAULT 0 COMMENT '专业ID(预留)',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '班级名称',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '班级代码(bjdm)',
  `grade` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '年级(如2024)',
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0禁用1启用',
  `create_time` int(11) NULL DEFAULT 0,
  `update_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `school_grade`(`school_id`, `grade`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='班级表';

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_class_schedule
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_class_schedule`;
CREATE TABLE `{{prefix}}xiaoyuan_class_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0,
  `school_id` int(11) NOT NULL DEFAULT 0,
  `class_id` int(11) NOT NULL DEFAULT 0,
  `semester` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '学期(2024-2025-1)',
  `week_day` tinyint(1) NOT NULL DEFAULT 1 COMMENT '星期(1-7)',
  `section_start` tinyint(2) NOT NULL DEFAULT 1 COMMENT '开始节次',
  `section_end` tinyint(2) NOT NULL DEFAULT 1 COMMENT '结束节次',
  `course_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '课程代码',
  `course_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '课程名称',
  `teacher` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '教师',
  `classroom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '教室',
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '校区/地点',
  `credit` decimal(3,1) NULL DEFAULT 0.0 COMMENT '学分',
  `start_week` int(11) NOT NULL DEFAULT 1,
  `end_week` int(11) NOT NULL DEFAULT 16,
  `week_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0全部1单周2双周',
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '',
  `weeks_text` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '原始周次文本(如1-14周)',
  `create_time` int(11) NULL DEFAULT 0,
  `update_time` int(11) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `class_semester`(`class_id`, `semester`) USING BTREE,
  INDEX `site_id`(`site_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='班级课表';

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_school
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_school`;
CREATE TABLE `{{prefix}}xiaoyuan_school`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '学校名称',
  `short_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '学校简称',
  `logo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '学校Logo',
  `province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '省份',
  `city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '城市',
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '学校地址',
  `campus_list` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '校区列表(JSON)',
  `lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '经度',
  `lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '纬度',
  `semester_start` date NULL DEFAULT NULL COMMENT '开学日期',
  `semester_end` date NULL DEFAULT NULL COMMENT '结束日期',
  `sections` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '课节时间段(JSON)',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 22 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '学校表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_secondhand
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_secondhand`;
CREATE TABLE `{{prefix}}xiaoyuan_secondhand`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布者ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布人学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '发布人校区',
  `category_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类ID',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商品标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '商品描述',
  `images` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '商品图片',
  `original_price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '原价',
  `price` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '售价',
  `condition_level` tinyint(1) NOT NULL DEFAULT 9 COMMENT '成色:1-10',
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系人',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系电话',
  `trade_method` enum('FACE','EXPRESS','BOTH') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'FACE' COMMENT '交易方式:FACE面交,EXPRESS快递,BOTH都可以',
  `trade_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '交易地址',
  `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `want_count` int(11) NOT NULL DEFAULT 0 COMMENT '想要数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0下架,1在售,2已售出,3已删除',
  `refuse_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '审核拒绝原因',
  `is_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶:0否,1是',
  `top_expire_time` int(11) NULL DEFAULT 0 COMMENT '置顶过期时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `campus`, `status`) USING BTREE,
  INDEX `category_id`(`category_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '二手交易表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_secondhand_category
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_secondhand_category`;
CREATE TABLE `{{prefix}}xiaoyuan_secondhand_category`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `icon` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '分类图标',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `site_id`(`site_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '二手交易分类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_secondhand_want
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_secondhand_want`;
CREATE TABLE `{{prefix}}xiaoyuan_secondhand_want`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '留言',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `goods_id`(`goods_id`) USING BTREE,
  INDEX `member_id`(`member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '二手交易想要记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_sign
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_sign`;
CREATE TABLE `{{prefix}}xiaoyuan_sign`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `sign_date` date NOT NULL COMMENT '签到日期',
  `sign_time` int(11) NULL DEFAULT 0 COMMENT '签到时间',
  `reward_points` int(11) NOT NULL DEFAULT 0 COMMENT '奖励积分',
  `continuous_days` int(11) NOT NULL DEFAULT 0 COMMENT '连续签到天数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0补签,1正常签到',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `member_date`(`member_id`, `sign_date`) USING BTREE,
  INDEX `site_id`(`site_id`, `sign_date`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '签到记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_sign_config
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_sign_config`;
CREATE TABLE `{{prefix}}xiaoyuan_sign_config`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `base_reward` int(11) NOT NULL DEFAULT 10 COMMENT '基础奖励积分',
  `continuous_rewards` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '连续签到奖励配置(JSON)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用,1启用',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `site_id`(`site_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '签到配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_task
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_task`;
CREATE TABLE `{{prefix}}xiaoyuan_task`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `task_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '任务编号',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布用户ID',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '接单员ID',
  `school_id` int(11) NOT NULL DEFAULT 0 COMMENT '发布人学校ID',
  `campus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '发布人校区',
  `task_type` enum('EXPRESS','TAKEOUT','BUY','QUEUE','PRINT','SEAT','ERRAND','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '任务类型:EXPRESS代取快递,TAKEOUT代拿外卖,BUY代买物品,QUEUE代排队,PRINT代打印,SEAT代占座,ERRAND跑腿,OTHER其他',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '任务描述',
  `images` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '任务图片',
  `pickup_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件地址',
  `pickup_lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件经度',
  `pickup_lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件纬度',
  `delivery_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '送达地址',
  `delivery_lng` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '送达经度',
  `delivery_lat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '送达纬度',
  `contact_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系人',
  `contact_mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '联系电话',
  `express_company` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '快递公司',
  `express_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '快递单号',
  `pickup_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取件码',
  `reward` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '悬赏金额',
  `tip` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '小费',
  `total_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '总金额',
  `platform_fee` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '平台服务费',
  `runner_income` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '接单员收益',
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否加急:0否,1是',
  `deadline` int(11) NULL DEFAULT 0 COMMENT '截止时间',
  `status` tinyint(2) NOT NULL DEFAULT 0 COMMENT '状态:0待支付,10待接单,20已接单,30进行中,40待确认,50已完成,90已取消,91已退款',
  `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态:0未支付,1已支付',
  `pay_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '支付方式',
  `pay_time` int(11) NULL DEFAULT 0 COMMENT '支付时间',
  `accept_time` int(11) NULL DEFAULT 0 COMMENT '接单时间',
  `complete_time` int(11) NULL DEFAULT 0 COMMENT '完成时间',
  `cancel_time` int(11) NULL DEFAULT 0 COMMENT '取消时间',
  `cancel_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '取消原因',
  `cancel_role` enum('USER','RUNNER','SYSTEM','ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '取消方',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `task_no`(`task_no`) USING BTREE,
  INDEX `member_id`(`member_id`, `site_id`) USING BTREE,
  INDEX `runner_id`(`runner_id`, `site_id`) USING BTREE,
  INDEX `school_id`(`school_id`, `campus`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '任务悬赏表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_tip_order
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_tip_order`;
CREATE TABLE `{{prefix}}xiaoyuan_tip_order`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `order_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '打赏用户ID',
  `runner_id` int(11) NOT NULL DEFAULT 0 COMMENT '接单员ID',
  `amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '打赏金额',
  `pay_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态:0未支付,1已支付',
  `pay_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '支付方式',
  `pay_time` int(11) NULL DEFAULT 0 COMMENT '支付时间',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_order`(`order_id`) USING BTREE,
  INDEX `idx_member`(`member_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '打赏订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_vip
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_vip`;
CREATE TABLE `{{prefix}}xiaoyuan_vip`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID',
  `vip_type` enum('MONTH','SEASON','YEAR') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'VIP类型:MONTH月卡,SEASON季卡,YEAR年卡',
  `start_time` int(11) NULL DEFAULT 0 COMMENT '开始时间',
  `expire_time` int(11) NULL DEFAULT 0 COMMENT '过期时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0已过期,1生效中',
  `create_time` int(11) NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `member_id`(`member_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = 'VIP会员表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_community_like
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_community_like`;
CREATE TABLE `{{prefix}}xiaoyuan_community_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '点赞用户ID',
  `post_id` int(11) NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '点赞时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_like` (`site_id`, `member_id`, `post_id`) USING BTREE,
  KEY `idx_post_id` (`post_id`) USING BTREE,
  KEY `idx_member_id` (`member_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='社区帖子点赞记录表';

-- ----------------------------
-- Table structure for {{prefix}}xiaoyuan_confession_like
-- ----------------------------
DROP TABLE IF EXISTS `{{prefix}}xiaoyuan_confession_like`;
CREATE TABLE `{{prefix}}xiaoyuan_confession_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` int(11) NOT NULL DEFAULT 0 COMMENT '点赞用户ID',
  `confession_id` int(11) NOT NULL DEFAULT 0 COMMENT '表白ID',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '点赞时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_like` (`site_id`, `member_id`, `confession_id`) USING BTREE,
  KEY `idx_confession_id` (`confession_id`) USING BTREE,
  KEY `idx_member_id` (`member_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='表白墙点赞记录表';

SET FOREIGN_KEY_CHECKS = 1;
