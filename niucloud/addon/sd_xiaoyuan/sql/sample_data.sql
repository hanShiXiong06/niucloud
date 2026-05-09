-- 校园帮插件测试数据
-- site_id = 100000, member_id = 1
-- 随机图片使用 picsum.photos 接口

-- ========================================
-- 1. 学校数据 (10所学校，校区用逗号分隔)
-- ========================================
INSERT INTO `niu_xiaoyuan_school` (`site_id`, `name`, `short_name`, `province`, `city`, `address`, `campus_list`, `lng`, `lat`, `sort`, `status`, `create_time`, `update_time`) VALUES
(100000, '清华大学', '清华', '北京市', '北京市', '北京市海淀区清华园1号', '主校区,昌平校区,雁栖湖校区', '116.326551', '40.003025', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '北京大学', '北大', '北京市', '北京市', '北京市海淀区颐和园路5号', '主校区,昌平校区,深圳研究生院', '116.316651', '39.999025', 99, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '复旦大学', '复旦', '上海市', '上海市', '上海市杨浦区邯郸路220号', '邯郸校区,江湾校区,枫林校区,张江校区', '121.503651', '31.299025', 98, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '上海交通大学', '交大', '上海市', '上海市', '上海市闵行区东川路800号', '闵行校区,徐汇校区,黄浦校区', '121.443651', '31.029025', 97, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '浙江大学', '浙大', '浙江省', '杭州市', '浙江省杭州市西湖区余杭塘路866号', '紫金港校区,玉泉校区,西溪校区', '120.093651', '30.309025', 96, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '南京大学', '南大', '江苏省', '南京市', '江苏省南京市鼓楼区汉口路22号', '仙林校区,鼓楼校区', '118.963651', '32.059025', 95, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '中山大学', '中大', '广东省', '广州市', '广东省广州市海珠区新港西路135号', '南校区,北校区,东校区,珠海校区', '113.303651', '23.099025', 94, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '华中科技大学', '华科', '湖北省', '武汉市', '湖北省武汉市洪山区珞喻路1037号', '主校区,东校区,同济校区', '114.333651', '30.529025', 93, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '西安交通大学', '西交大', '陕西省', '西安市', '陕西省西安市咸宁西路28号', '兴庆校区,雁塔校区,曲江校区', '108.993651', '34.259025', 92, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '哈尔滨工业大学', '哈工大', '黑龙江省', '哈尔滨市', '黑龙江省哈尔滨市南岗区西大直街92号', '一校区,二校区,深圳校区', '126.633651', '45.739025', 91, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 2. 快递驿站数据 (每个学校5个驿站)
-- ========================================
INSERT INTO `niu_xiaoyuan_express_station` (`site_id`, `school_id`, `name`, `address`, `express_company`, `contact_name`, `contact_mobile`, `business_hours`, `sort`, `status`, `create_time`) VALUES
-- 清华大学
(100000, 1, '菜鸟驿站(清华东门)', '清华大学东门菜鸟驿站', '菜鸟', '张三', '13800138001', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 1, '丰巢快递柜(紫荆公寓)', '紫荆公寓1号楼', '丰巢', '李四', '13800138002', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 1, '顺丰速运(清华西门)', '清华大学西门顺丰营业点', '顺丰', '王五', '13800138003', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 1, '京东快递(学生服务中心)', '学生服务中心一层', '京东', '赵六', '13800138004', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 1, '中通快递(南门)', '清华大学南门中通代收点', '中通', '钱七', '13800138005', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 北京大学
(100000, 2, '菜鸟驿站(北大东门)', '北京大学东门菜鸟驿站', '菜鸟', '孙八', '13800138006', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 2, '丰巢快递柜(畅春园)', '畅春园宿舍区', '丰巢', '周九', '13800138007', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 2, '顺丰速运(北大西门)', '北京大学西门顺丰营业点', '顺丰', '吴十', '13800138008', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 2, '京东快递(百年讲堂)', '百年讲堂旁京东代收点', '京东', '郑一', '13800138009', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 2, '圆通快递(南门)', '北京大学南门圆通代收点', '圆通', '王二', '13800138010', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 复旦大学
(100000, 3, '菜鸟驿站(复旦邯郸)', '复旦大学邯郸校区菜鸟驿站', '菜鸟', '陈三', '13800138011', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 3, '丰巢快递柜(南区宿舍)', '南区宿舍楼下', '丰巢', '林四', '13800138012', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 3, '顺丰速运(国权路)', '国权路顺丰营业点', '顺丰', '黄五', '13800138013', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 3, '韵达快递(北区)', '北区食堂旁韵达代收点', '韵达', '刘六', '13800138014', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 3, '申通快递(正门)', '复旦大学正门申通代收点', '申通', '杨七', '13800138015', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 上海交大
(100000, 4, '菜鸟驿站(交大闵行)', '上海交大闵行校区菜鸟驿站', '菜鸟', '赵八', '13800138016', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 4, '丰巢快递柜(东区)', '东区宿舍楼下', '丰巢', '钱九', '13800138017', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 4, '顺丰速运(思源门)', '思源门顺丰营业点', '顺丰', '孙十', '13800138018', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 4, '中通快递(西区)', '西区食堂旁中通代收点', '中通', '李一', '13800138019', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 4, '极兔快递(南门)', '交大南门极兔代收点', '极兔', '王二', '13800138020', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 浙江大学
(100000, 5, '菜鸟驿站(浙大紫金港)', '浙大紫金港校区菜鸟驿站', '菜鸟', '张三', '13800138021', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 5, '丰巢快递柜(蓝田学园)', '蓝田学园宿舍区', '丰巢', '李四', '13800138022', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 5, '顺丰速运(玉泉)', '玉泉校区顺丰营业点', '顺丰', '王五', '13800138023', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 5, '京东快递(西溪)', '西溪校区京东代收点', '京东', '赵六', '13800138024', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 5, '圆通快递(东门)', '浙大东门圆通代收点', '圆通', '钱七', '13800138025', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 南京大学
(100000, 6, '菜鸟驿站(南大仙林)', '南大仙林校区菜鸟驿站', '菜鸟', '孙八', '13800138026', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 6, '丰巢快递柜(敬文书院)', '敬文书院宿舍区', '丰巢', '周九', '13800138027', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 6, '顺丰速运(鼓楼)', '鼓楼校区顺丰营业点', '顺丰', '吴十', '13800138028', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 6, '韵达快递(北园)', '北园食堂旁韵达代收点', '韵达', '郑一', '13800138029', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 6, '申通快递(正门)', '南大正门申通代收点', '申通', '王二', '13800138030', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 中山大学
(100000, 7, '菜鸟驿站(中大南校)', '中大南校区菜鸟驿站', '菜鸟', '陈三', '13800138031', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 7, '丰巢快递柜(至善园)', '至善园宿舍区', '丰巢', '林四', '13800138032', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 7, '顺丰速运(北校)', '北校区顺丰营业点', '顺丰', '黄五', '13800138033', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 7, '中通快递(东校)', '东校区中通代收点', '中通', '刘六', '13800138034', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 7, '极兔快递(珠海)', '珠海校区极兔代收点', '极兔', '杨七', '13800138035', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 华中科技大学
(100000, 8, '菜鸟驿站(华科主校)', '华科主校区菜鸟驿站', '菜鸟', '赵八', '13800138036', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 8, '丰巢快递柜(韵苑)', '韵苑宿舍区', '丰巢', '钱九', '13800138037', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 8, '顺丰速运(东校)', '东校区顺丰营业点', '顺丰', '孙十', '13800138038', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 8, '京东快递(西区)', '西区食堂旁京东代收点', '京东', '李一', '13800138039', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 8, '圆通快递(南门)', '华科南门圆通代收点', '圆通', '王二', '13800138040', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 西安交大
(100000, 9, '菜鸟驿站(西交大兴庆)', '西交大兴庆校区菜鸟驿站', '菜鸟', '张三', '13800138041', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 9, '丰巢快递柜(东区宿舍)', '东区宿舍楼下', '丰巢', '李四', '13800138042', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 9, '顺丰速运(雁塔)', '雁塔校区顺丰营业点', '顺丰', '王五', '13800138043', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 9, '韵达快递(曲江)', '曲江校区韵达代收点', '韵达', '赵六', '13800138044', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 9, '申通快递(北门)', '西交大北门申通代收点', '申通', '钱七', '13800138045', '08:00-20:00', 5, 1, UNIX_TIMESTAMP()),
-- 哈工大
(100000, 10, '菜鸟驿站(哈工大一校)', '哈工大一校区菜鸟驿站', '菜鸟', '孙八', '13800138046', '08:00-21:00', 1, 1, UNIX_TIMESTAMP()),
(100000, 10, '丰巢快递柜(学生公寓)', '学生公寓楼下', '丰巢', '周九', '13800138047', '24小时', 2, 1, UNIX_TIMESTAMP()),
(100000, 10, '顺丰速运(二校)', '二校区顺丰营业点', '顺丰', '吴十', '13800138048', '09:00-18:00', 3, 1, UNIX_TIMESTAMP()),
(100000, 10, '中通快递(科学园)', '科学园中通代收点', '中通', '郑一', '13800138049', '08:30-20:00', 4, 1, UNIX_TIMESTAMP()),
(100000, 10, '极兔快递(正门)', '哈工大正门极兔代收点', '极兔', '王二', '13800138050', '08:00-20:00', 5, 1, UNIX_TIMESTAMP());

-- ========================================
-- 3. 分类数据
-- ========================================
-- 二手交易分类
INSERT INTO `niu_xiaoyuan_secondhand_category` (`site_id`, `name`, `icon`, `sort`, `status`, `create_time`) VALUES
(100000, '数码产品', 'https://picsum.photos/100/100?random=101', 1, 1, UNIX_TIMESTAMP()),
(100000, '书籍教材', 'https://picsum.photos/100/100?random=102', 2, 1, UNIX_TIMESTAMP()),
(100000, '生活用品', 'https://picsum.photos/100/100?random=103', 3, 1, UNIX_TIMESTAMP()),
(100000, '服饰鞋包', 'https://picsum.photos/100/100?random=104', 4, 1, UNIX_TIMESTAMP()),
(100000, '运动健身', 'https://picsum.photos/100/100?random=105', 5, 1, UNIX_TIMESTAMP()),
(100000, '美妆护肤', 'https://picsum.photos/100/100?random=106', 6, 1, UNIX_TIMESTAMP()),
(100000, '乐器设备', 'https://picsum.photos/100/100?random=107', 7, 1, UNIX_TIMESTAMP()),
(100000, '其他物品', 'https://picsum.photos/100/100?random=108', 8, 1, UNIX_TIMESTAMP());

-- 树洞分类
INSERT INTO `niu_xiaoyuan_community_category` (`site_id`, `name`, `icon`, `sort`, `status`, `create_time`) VALUES
(100000, '日常吐槽', '', 1, 1, UNIX_TIMESTAMP()),
(100000, '学习交流', '', 2, 1, UNIX_TIMESTAMP()),
(100000, '情感树洞', '', 3, 1, UNIX_TIMESTAMP()),
(100000, '求助问答', '', 4, 1, UNIX_TIMESTAMP()),
(100000, '校园趣事', '', 5, 1, UNIX_TIMESTAMP()),
(100000, '美食分享', '', 6, 1, UNIX_TIMESTAMP());

-- 游戏类型分类
INSERT INTO `niu_xiaoyuan_game_category` (`site_id`, `name`, `icon`, `sort`, `status`, `create_time`) VALUES
(100000, '王者荣耀', 'https://picsum.photos/100/100?random=201', 1, 1, UNIX_TIMESTAMP()),
(100000, '英雄联盟', 'https://picsum.photos/100/100?random=202', 2, 1, UNIX_TIMESTAMP()),
(100000, '和平精英', 'https://picsum.photos/100/100?random=203', 3, 1, UNIX_TIMESTAMP()),
(100000, '原神', 'https://picsum.photos/100/100?random=204', 4, 1, UNIX_TIMESTAMP()),
(100000, '永劫无间', 'https://picsum.photos/100/100?random=205', 5, 1, UNIX_TIMESTAMP()),
(100000, 'CSGO', 'https://picsum.photos/100/100?random=206', 6, 1, UNIX_TIMESTAMP()),
(100000, 'DOTA2', 'https://picsum.photos/100/100?random=207', 7, 1, UNIX_TIMESTAMP()),
(100000, '其他游戏', 'https://picsum.photos/100/100?random=208', 8, 1, UNIX_TIMESTAMP());

-- 失物招领分类
INSERT INTO `niu_xiaoyuan_lost_found_category` (`site_id`, `name`, `icon`, `sort`, `status`, `create_time`) VALUES
(100000, '证件卡类', 'https://picsum.photos/100/100?random=301', 1, 1, UNIX_TIMESTAMP()),
(100000, '电子产品', 'https://picsum.photos/100/100?random=302', 2, 1, UNIX_TIMESTAMP()),
(100000, '钥匙钱包', 'https://picsum.photos/100/100?random=303', 3, 1, UNIX_TIMESTAMP()),
(100000, '书籍文具', 'https://picsum.photos/100/100?random=304', 4, 1, UNIX_TIMESTAMP()),
(100000, '衣物配饰', 'https://picsum.photos/100/100?random=305', 5, 1, UNIX_TIMESTAMP()),
(100000, '其他物品', 'https://picsum.photos/100/100?random=306', 6, 1, UNIX_TIMESTAMP());

-- ========================================
-- 4. 任务数据 (每个学校每种类型10条)
-- ========================================
-- 代取快递任务
INSERT INTO `niu_xiaoyuan_task` (`site_id`, `task_no`, `member_id`, `runner_id`, `school_id`, `campus`, `task_type`, `title`, `content`, `images`, `pickup_address`, `delivery_address`, `contact_name`, `contact_mobile`, `express_company`, `express_no`, `pickup_code`, `reward`, `tip`, `total_amount`, `status`, `pay_status`, `create_time`, `update_time`) VALUES
(100000, 'TASK202402240001', 1, 0, 1, '主校区', 'EXPRESS', '帮取顺丰快递', '快递在菜鸟驿站，取件码1234', '["https://picsum.photos/400/300?random=1001"]', '清华大学东门菜鸟驿站', '紫荆公寓3号楼201', '张同学', '13800000001', '顺丰', 'SF1234567890', '1234', 5.00, 0.00, 5.00, 10, 1, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'TASK202402240002', 1, 0, 1, '主校区', 'EXPRESS', '帮取京东快递', '京东快递比较大件', '["https://picsum.photos/400/300?random=1002"]', '学生服务中心京东代收点', '紫荆公寓5号楼302', '李同学', '13800000002', '京东', 'JD9876543210', '5678', 6.00, 1.00, 7.00, 10, 1, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'TASK202402240003', 1, 0, 2, '主校区', 'EXPRESS', '帮取圆通快递', '快递在南门圆通代收点', '["https://picsum.photos/400/300?random=1003"]', '北京大学南门圆通代收点', '畅春园宿舍区A栋', '王同学', '13800000003', '圆通', 'YT1122334455', '9012', 5.00, 0.00, 5.00, 10, 1, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'TASK202402240004', 1, 0, 3, '邯郸校区', 'EXPRESS', '帮取中通快递', '两个快递一起取', '["https://picsum.photos/400/300?random=1004"]', '复旦大学正门申通代收点', '南区宿舍12号楼', '赵同学', '13800000004', '中通', 'ZT5566778899', '3456', 8.00, 2.00, 10.00, 10, 1, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'TASK202402240005', 1, 0, 4, '闵行校区', 'EXPRESS', '帮取韵达快递', '快递比较重', '["https://picsum.photos/400/300?random=1005"]', '交大南门极兔代收点', '东区宿舍8号楼', '钱同学', '13800000005', '韵达', 'YD1357924680', '7890', 7.00, 0.00, 7.00, 10, 1, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
-- 代买服务任务
(100000, 'TASK202402240006', 1, 0, 1, '主校区', 'BUY', '帮买瑞幸咖啡', '要一杯生椰拿铁，少冰少糖', '["https://picsum.photos/400/300?random=1006"]', '清华大学瑞幸咖啡店', '图书馆门口', '孙同学', '13800000006', '', '', '', 15.00, 3.00, 18.00, 10, 1, UNIX_TIMESTAMP()-21600, UNIX_TIMESTAMP()),
(100000, 'TASK202402240007', 1, 0, 2, '主校区', 'BUY', '帮买奶茶', '一点点波霸奶茶，正常糖正常冰', '["https://picsum.photos/400/300?random=1007"]', '北大西门一点点', '百年讲堂', '周同学', '13800000007', '', '', '', 12.00, 2.00, 14.00, 10, 1, UNIX_TIMESTAMP()-25200, UNIX_TIMESTAMP()),
(100000, 'TASK202402240008', 1, 0, 3, '邯郸校区', 'BUY', '帮买文具', '需要一支0.5黑色中性笔和一本A4笔记本', '["https://picsum.photos/400/300?random=1008"]', '校内文具店', '北区宿舍6号楼', '吴同学', '13800000008', '', '', '', 10.00, 0.00, 10.00, 10, 1, UNIX_TIMESTAMP()-28800, UNIX_TIMESTAMP()),
-- 代打印任务
(100000, 'TASK202402240009', 1, 0, 1, '主校区', 'PRINT', '帮打印论文', '30页论文，双面打印，A4纸', '["https://picsum.photos/400/300?random=1009"]', '清华打印店', '紫荆公寓1号楼', '郑同学', '13800000009', '', '', '', 8.00, 0.00, 8.00, 10, 1, UNIX_TIMESTAMP()-32400, UNIX_TIMESTAMP()),
(100000, 'TASK202402240010', 1, 0, 2, '主校区', 'PRINT', '帮打印PPT', '50页PPT，彩色打印', '["https://picsum.photos/400/300?random=1010"]', '北大打印中心', '理科楼', '冯同学', '13800000010', '', '', '', 25.00, 5.00, 30.00, 10, 1, UNIX_TIMESTAMP()-36000, UNIX_TIMESTAMP()),
-- 帮搬运任务
(100000, 'TASK202402240011', 1, 0, 5, '紫金港校区', 'OTHER', '帮搬行李', '两个行李箱，从宿舍搬到校门口', '["https://picsum.photos/400/300?random=1011"]', '蓝田学园5号楼', '浙大东门', '陈同学', '13800000011', '', '', '', 20.00, 5.00, 25.00, 10, 1, UNIX_TIMESTAMP()-39600, UNIX_TIMESTAMP()),
(100000, 'TASK202402240012', 1, 0, 6, '仙林校区', 'OTHER', '帮搬书籍', '一箱书，大概20本', '["https://picsum.photos/400/300?random=1012"]', '南大图书馆', '敬文书院', '林同学', '13800000012', '', '', '', 15.00, 0.00, 15.00, 10, 1, UNIX_TIMESTAMP()-43200, UNIX_TIMESTAMP()),
-- 代排队任务
(100000, 'TASK202402240013', 1, 0, 7, '南校区', 'QUEUE', '帮排队取餐', '食堂人太多，帮忙排队取餐', '["https://picsum.photos/400/300?random=1013"]', '中大南校食堂', '至善园宿舍', '黄同学', '13800000013', '', '', '', 10.00, 2.00, 12.00, 10, 1, UNIX_TIMESTAMP()-46800, UNIX_TIMESTAMP()),
(100000, 'TASK202402240014', 1, 0, 8, '主校区', 'QUEUE', '帮排队办事', '帮忙在教务处排队', '["https://picsum.photos/400/300?random=1014"]', '华科教务处', '韵苑宿舍', '刘同学', '13800000014', '', '', '', 15.00, 0.00, 15.00, 10, 1, UNIX_TIMESTAMP()-50400, UNIX_TIMESTAMP()),
-- 代占座任务
(100000, 'TASK202402240015', 1, 0, 9, '兴庆校区', 'SEAT', '帮占自习室座位', '图书馆三楼自习室，靠窗位置', '["https://picsum.photos/400/300?random=1015"]', '西交大图书馆', '东区宿舍', '杨同学', '13800000015', '', '', '', 5.00, 0.00, 5.00, 10, 1, UNIX_TIMESTAMP()-54000, UNIX_TIMESTAMP()),
(100000, 'TASK202402240016', 1, 0, 10, '一校区', 'SEAT', '帮占图书馆座位', '图书馆二楼，需要有插座的位置', '["https://picsum.photos/400/300?random=1016"]', '哈工大图书馆', '学生公寓', '赵同学', '13800000016', '', '', '', 5.00, 1.00, 6.00, 10, 1, UNIX_TIMESTAMP()-57600, UNIX_TIMESTAMP());

-- ========================================
-- 5. 二手交易数据（分类ID随机1-8，图片完整）
-- ========================================
INSERT INTO `niu_xiaoyuan_secondhand` (`site_id`, `member_id`, `school_id`, `campus`, `category_id`, `title`, `content`, `images`, `original_price`, `price`, `condition_level`, `contact_name`, `contact_mobile`, `trade_method`, `trade_address`, `view_count`, `want_count`, `status`, `create_time`, `update_time`) VALUES
(100000, 1, 1, '主校区', 1, '出iPad Pro 2022', '99新iPad Pro 11寸，256G，带笔和键盘', '["https://picsum.photos/400/300?random=2001","https://picsum.photos/400/300?random=2002","https://picsum.photos/400/300?random=2003"]', 8999.00, 6500.00, 9, '张同学', '13800000001', 'FACE', '紫荆公寓', 156, 12, 1, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()),
(100000, 1, 2, '主校区', 2, '出高数教材全套', '同济版高数上下册+习题集，9成新', '["https://picsum.photos/400/300?random=2004","https://picsum.photos/400/300?random=2005"]', 120.00, 50.00, 8, '李同学', '13800000002', 'FACE', '图书馆门口', 89, 5, 1, UNIX_TIMESTAMP()-172800, UNIX_TIMESTAMP()),
(100000, 1, 3, '邯郸校区', 3, '出MacBook Air M2', '2023款MacBook Air M2，8+256，星光色', '["https://picsum.photos/400/300?random=2006","https://picsum.photos/400/300?random=2007","https://picsum.photos/400/300?random=2008"]', 9499.00, 7800.00, 9, '王同学', '13800000003', 'BOTH', '畅春园', 234, 18, 1, UNIX_TIMESTAMP()-259200, UNIX_TIMESTAMP()),
(100000, 1, 4, '闵行校区', 4, '出宿舍小冰箱', '小型冰箱，用了一年，制冷正常', '["https://picsum.photos/400/300?random=2009","https://picsum.photos/400/300?random=2010"]', 599.00, 200.00, 7, '赵同学', '13800000004', 'FACE', '北大宿舍区', 67, 3, 1, UNIX_TIMESTAMP()-345600, UNIX_TIMESTAMP()),
(100000, 1, 5, '紫金港校区', 5, '出Nike运动鞋', 'Nike Air Max 270，42码，穿过几次', '["https://picsum.photos/400/300?random=2011","https://picsum.photos/400/300?random=2012"]', 899.00, 400.00, 8, '钱同学', '13800000005', 'FACE', '南区宿舍', 112, 8, 1, UNIX_TIMESTAMP()-432000, UNIX_TIMESTAMP()),
(100000, 1, 6, '仙林校区', 6, '出瑜伽垫', '全新瑜伽垫，买来没用过', '["https://picsum.photos/400/300?random=2013","https://picsum.photos/400/300?random=2014"]', 99.00, 50.00, 10, '孙同学', '13800000006', 'FACE', '复旦体育馆', 45, 2, 1, UNIX_TIMESTAMP()-518400, UNIX_TIMESTAMP()),
(100000, 1, 7, '南校区', 7, '出索尼降噪耳机', 'Sony WH-1000XM4，黑色，带盒', '["https://picsum.photos/400/300?random=2015","https://picsum.photos/400/300?random=2016","https://picsum.photos/400/300?random=2017"]', 2499.00, 1500.00, 8, '周同学', '13800000007', 'BOTH', '东区宿舍', 178, 15, 1, UNIX_TIMESTAMP()-604800, UNIX_TIMESTAMP()),
(100000, 1, 8, '主校区', 8, '出护肤品套装', '兰蔻小黑瓶套装，全新未拆', '["https://picsum.photos/400/300?random=2018","https://picsum.photos/400/300?random=2019"]', 1200.00, 800.00, 10, '吴同学', '13800000008', 'EXPRESS', '蓝田学园', 98, 6, 1, UNIX_TIMESTAMP()-691200, UNIX_TIMESTAMP()),
(100000, 1, 9, '兴庆校区', 1, '出吉他', '雅马哈F310，带琴包和调音器', '["https://picsum.photos/400/300?random=2020","https://picsum.photos/400/300?random=2021","https://picsum.photos/400/300?random=2022"]', 899.00, 500.00, 7, '郑同学', '13800000009', 'FACE', '敬文书院', 134, 9, 1, UNIX_TIMESTAMP()-777600, UNIX_TIMESTAMP()),
(100000, 1, 10, '一校区', 2, '出考研资料', '考研政治+英语全套资料，带笔记', '["https://picsum.photos/400/300?random=2023","https://picsum.photos/400/300?random=2024"]', 500.00, 150.00, 8, '冯同学', '13800000010', 'FACE', '至善园', 201, 22, 1, UNIX_TIMESTAMP()-864000, UNIX_TIMESTAMP()),
(100000, 1, 1, '昌平校区', 3, '出机械键盘', 'Cherry MX红轴机械键盘，87键', '["https://picsum.photos/400/300?random=2025","https://picsum.photos/400/300?random=2026"]', 699.00, 350.00, 8, '陈同学', '13800000011', 'FACE', '学生公寓', 89, 7, 1, UNIX_TIMESTAMP()-950400, UNIX_TIMESTAMP()),
(100000, 1, 2, '深圳研究生院', 4, '出台灯', '护眼台灯，LED可调光', '["https://picsum.photos/400/300?random=2027","https://picsum.photos/400/300?random=2028"]', 199.00, 80.00, 9, '林同学', '13800000012', 'FACE', '研究生宿舍', 56, 4, 1, UNIX_TIMESTAMP()-1036800, UNIX_TIMESTAMP()),
(100000, 1, 3, '江湾校区', 5, '出哑铃套装', '可调节哑铃20kg，健身必备', '["https://picsum.photos/400/300?random=2029","https://picsum.photos/400/300?random=2030","https://picsum.photos/400/300?random=2031"]', 299.00, 150.00, 8, '黄同学', '13800000013', 'FACE', '江湾宿舍', 78, 6, 1, UNIX_TIMESTAMP()-1123200, UNIX_TIMESTAMP()),
(100000, 1, 4, '徐汇校区', 6, '出化妆品', '雅诗兰黛眼霜，全新未拆', '["https://picsum.photos/400/300?random=2032","https://picsum.photos/400/300?random=2033"]', 580.00, 400.00, 10, '刘同学', '13800000014', 'EXPRESS', '徐汇宿舍', 123, 11, 1, UNIX_TIMESTAMP()-1209600, UNIX_TIMESTAMP()),
(100000, 1, 5, '玉泉校区', 7, '出电子琴', '雅马哈电子琴61键，带琴架', '["https://picsum.photos/400/300?random=2034","https://picsum.photos/400/300?random=2035","https://picsum.photos/400/300?random=2036"]', 1299.00, 700.00, 7, '杨同学', '13800000015', 'FACE', '玉泉宿舍', 167, 14, 1, UNIX_TIMESTAMP()-1296000, UNIX_TIMESTAMP()),
(100000, 1, 6, '鼓楼校区', 8, '出旧手机', 'iPhone 12 128G，电池健康85%', '["https://picsum.photos/400/300?random=2037","https://picsum.photos/400/300?random=2038"]', 4999.00, 2500.00, 7, '赵同学', '13800000016', 'BOTH', '鼓楼宿舍', 234, 19, 1, UNIX_TIMESTAMP()-1382400, UNIX_TIMESTAMP());

-- ========================================
-- 6. 失物招领数据（学校ID随机1-10，分类使用名称）
-- ========================================
INSERT INTO `niu_xiaoyuan_lost_found` (`site_id`, `member_id`, `school_id`, `campus`, `type`, `category`, `title`, `content`, `images`, `lost_time`, `lost_address`, `contact_name`, `contact_mobile`, `reward`, `view_count`, `status`, `create_time`, `update_time`) VALUES
(100000, 1, 1, '主校区', 'LOST', '证件卡类', '丢失学生卡', '在图书馆丢失学生卡一张，姓名张XX', '["https://picsum.photos/400/300?random=3001","https://picsum.photos/400/300?random=3002"]', UNIX_TIMESTAMP()-86400, '清华图书馆', '张同学', '13800000001', 50.00, 89, 1, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 1, 2, '主校区', 'FOUND', '电子产品', '捡到AirPods', '在教学楼捡到AirPods Pro一副', '["https://picsum.photos/400/300?random=3003","https://picsum.photos/400/300?random=3004"]', UNIX_TIMESTAMP()-43200, '清华教学楼', '李同学', '13800000002', 0.00, 156, 1, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 1, 3, '邯郸校区', 'LOST', '钥匙钱包', '丢失钱包', '黑色钱包，内有身份证和银行卡', '["https://picsum.photos/400/300?random=3005","https://picsum.photos/400/300?random=3006"]', UNIX_TIMESTAMP()-129600, '北大食堂', '王同学', '13800000003', 100.00, 234, 1, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 1, 4, '闵行校区', 'FOUND', '书籍文具', '捡到笔记本', '在自习室捡到一本笔记本，有很多学习笔记', '["https://picsum.photos/400/300?random=3007","https://picsum.photos/400/300?random=3008"]', UNIX_TIMESTAMP()-172800, '北大自习室', '赵同学', '13800000004', 0.00, 67, 1, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 1, 5, '紫金港校区', 'LOST', '电子产品', '丢失U盘', '黑色金士顿U盘，32G，里面有重要资料', '["https://picsum.photos/400/300?random=3009","https://picsum.photos/400/300?random=3010"]', UNIX_TIMESTAMP()-216000, '复旦机房', '钱同学', '13800000005', 30.00, 112, 1, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
(100000, 1, 6, '仙林校区', 'FOUND', '衣物配饰', '捡到外套', '在操场捡到一件黑色外套', '["https://picsum.photos/400/300?random=3011","https://picsum.photos/400/300?random=3012"]', UNIX_TIMESTAMP()-259200, '交大操场', '孙同学', '13800000006', 0.00, 45, 1, UNIX_TIMESTAMP()-21600, UNIX_TIMESTAMP()),
(100000, 1, 7, '南校区', 'LOST', '证件卡类', '丢失身份证', '在校门口丢失身份证', '["https://picsum.photos/400/300?random=3013","https://picsum.photos/400/300?random=3014"]', UNIX_TIMESTAMP()-302400, '浙大东门', '周同学', '13800000007', 80.00, 178, 1, UNIX_TIMESTAMP()-25200, UNIX_TIMESTAMP()),
(100000, 1, 8, '主校区', 'FOUND', '钥匙钱包', '捡到钥匙', '在宿舍楼下捡到一串钥匙', '["https://picsum.photos/400/300?random=3015","https://picsum.photos/400/300?random=3016"]', UNIX_TIMESTAMP()-345600, '南大宿舍区', '吴同学', '13800000008', 0.00, 98, 1, UNIX_TIMESTAMP()-28800, UNIX_TIMESTAMP()),
(100000, 1, 9, '兴庆校区', 'LOST', '其他物品', '丢失水杯', '蓝色保温杯，在食堂丢失', '["https://picsum.photos/400/300?random=3017","https://picsum.photos/400/300?random=3018"]', UNIX_TIMESTAMP()-388800, '中大食堂', '郑同学', '13800000009', 20.00, 56, 1, UNIX_TIMESTAMP()-32400, UNIX_TIMESTAMP()),
(100000, 1, 10, '一校区', 'FOUND', '电子产品', '捡到充电宝', '在图书馆捡到小米充电宝一个', '["https://picsum.photos/400/300?random=3019","https://picsum.photos/400/300?random=3020"]', UNIX_TIMESTAMP()-432000, '华科图书馆', '冯同学', '13800000010', 0.00, 134, 1, UNIX_TIMESTAMP()-36000, UNIX_TIMESTAMP()),
(100000, 1, 1, '昌平校区', 'LOST', '电子产品', '丢失手机', '黑色iPhone 14，在食堂丢失', '["https://picsum.photos/400/300?random=3021","https://picsum.photos/400/300?random=3022"]', UNIX_TIMESTAMP()-518400, '清华食堂', '陈同学', '13800000011', 200.00, 312, 1, UNIX_TIMESTAMP()-39600, UNIX_TIMESTAMP()),
(100000, 1, 2, '深圳研究生院', 'FOUND', '书籍文具', '捡到教材', '在教室捡到一本高等数学教材', '["https://picsum.photos/400/300?random=3023","https://picsum.photos/400/300?random=3024"]', UNIX_TIMESTAMP()-604800, '北大教室', '林同学', '13800000012', 0.00, 45, 1, UNIX_TIMESTAMP()-43200, UNIX_TIMESTAMP()),
(100000, 1, 3, '江湾校区', 'LOST', '衣物配饰', '丢失围巾', '红色羊毛围巾，在图书馆丢失', '["https://picsum.photos/400/300?random=3025","https://picsum.photos/400/300?random=3026"]', UNIX_TIMESTAMP()-691200, '复旦图书馆', '黄同学', '13800000013', 30.00, 78, 1, UNIX_TIMESTAMP()-46800, UNIX_TIMESTAMP()),
(100000, 1, 4, '徐汇校区', 'FOUND', '证件卡类', '捡到校园卡', '在操场捡到一张校园卡', '["https://picsum.photos/400/300?random=3027","https://picsum.photos/400/300?random=3028"]', UNIX_TIMESTAMP()-777600, '交大操场', '刘同学', '13800000014', 0.00, 89, 1, UNIX_TIMESTAMP()-50400, UNIX_TIMESTAMP()),
(100000, 1, 5, '玉泉校区', 'LOST', '钥匙钱包', '丢失钥匙', '一串钥匙，有校园卡挂件', '["https://picsum.photos/400/300?random=3029","https://picsum.photos/400/300?random=3030"]', UNIX_TIMESTAMP()-864000, '浙大宿舍', '杨同学', '13800000015', 50.00, 123, 1, UNIX_TIMESTAMP()-54000, UNIX_TIMESTAMP()),
(100000, 1, 6, '鼓楼校区', 'FOUND', '其他物品', '捡到雨伞', '在教学楼捡到一把黑色雨伞', '["https://picsum.photos/400/300?random=3031","https://picsum.photos/400/300?random=3032"]', UNIX_TIMESTAMP()-950400, '南大教学楼', '赵同学', '13800000016', 0.00, 34, 1, UNIX_TIMESTAMP()-57600, UNIX_TIMESTAMP());

-- ========================================
-- 7. 房屋租赁数据（nearby_schools和facilities使用逗号分隔）
-- ========================================
INSERT INTO `niu_xiaoyuan_house` (`site_id`, `member_id`, `school_id`, `campus`, `house_type`, `title`, `content`, `images`, `cover_image`, `address`, `rent_type`, `rent_price`, `deposit`, `area`, `rooms`, `floor`, `nearby_schools`, `facilities`, `contact_name`, `contact_mobile`, `view_count`, `status`, `create_time`, `update_time`) VALUES
(100000, 1, 1, '主校区', 'RENT', '清华东门精装一居室', '近清华东门，交通便利，家电齐全', '["https://picsum.photos/400/300?random=4001","https://picsum.photos/400/300?random=4002","https://picsum.photos/400/300?random=4003"]', 'https://picsum.photos/400/300?random=4001', '海淀区清华东路10号', 'MONTH', 3500.00, 3500.00, 35.00, '1室1厅', '5/18层', '1,2', '空调,洗衣机,冰箱,热水器,宽带', '房东张', '13800000001', 234, 1, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()),
(100000, 1, 1, '主校区', 'SHARE', '清华西门合租主卧', '三室一厅主卧出租，独立卫生间', '["https://picsum.photos/400/300?random=4004","https://picsum.photos/400/300?random=4005"]', 'https://picsum.photos/400/300?random=4004', '海淀区清华西路5号', 'MONTH', 2000.00, 2000.00, 18.00, '主卧', '3/6层', '1,2', '空调,热水器,宽带', '房东李', '13800000002', 156, 1, UNIX_TIMESTAMP()-172800, UNIX_TIMESTAMP()),
(100000, 1, 2, '主校区', 'RENT', '北大南门两居室', '南北通透，采光好，适合情侣或室友', '["https://picsum.photos/400/300?random=4006","https://picsum.photos/400/300?random=4007","https://picsum.photos/400/300?random=4008"]', 'https://picsum.photos/400/300?random=4006', '海淀区颐和园路8号', 'MONTH', 5500.00, 5500.00, 65.00, '2室1厅', '8/20层', '2,1', '空调,洗衣机,冰箱,热水器,宽带,电视', '房东王', '13800000003', 312, 1, UNIX_TIMESTAMP()-259200, UNIX_TIMESTAMP()),
(100000, 1, 3, '邯郸校区', 'RENT', '复旦邯郸校区单间', '步行5分钟到学校，安静整洁', '["https://picsum.photos/400/300?random=4009","https://picsum.photos/400/300?random=4010"]', 'https://picsum.photos/400/300?random=4009', '杨浦区国权路100号', 'MONTH', 2800.00, 2800.00, 25.00, '1室0厅', '4/7层', '3', '空调,热水器,宽带', '房东赵', '13800000004', 189, 1, UNIX_TIMESTAMP()-345600, UNIX_TIMESTAMP()),
(100000, 1, 4, '闵行校区', 'SHARE', '交大闵行合租次卧', '两室一厅次卧，室友是研究生', '["https://picsum.photos/400/300?random=4011","https://picsum.photos/400/300?random=4012"]', 'https://picsum.photos/400/300?random=4011', '闵行区东川路500号', 'MONTH', 1500.00, 1500.00, 12.00, '次卧', '2/6层', '4', '空调,洗衣机,宽带', '房东钱', '13800000005', 98, 1, UNIX_TIMESTAMP()-432000, UNIX_TIMESTAMP()),
(100000, 1, 5, '紫金港校区', 'RENT', '浙大紫金港精装公寓', '高档小区，物业好，安全有保障', '["https://picsum.photos/400/300?random=4013","https://picsum.photos/400/300?random=4014","https://picsum.photos/400/300?random=4015"]', 'https://picsum.photos/400/300?random=4013', '西湖区余杭塘路500号', 'MONTH', 4000.00, 4000.00, 45.00, '1室1厅', '12/28层', '5', '空调,洗衣机,冰箱,热水器,宽带,电视,微波炉', '房东孙', '13800000006', 267, 1, UNIX_TIMESTAMP()-518400, UNIX_TIMESTAMP()),
(100000, 1, 6, '仙林校区', 'RENT', '南大仙林温馨一居', '近地铁站，出行方便', '["https://picsum.photos/400/300?random=4016","https://picsum.photos/400/300?random=4017"]', 'https://picsum.photos/400/300?random=4016', '栖霞区仙林大道100号', 'MONTH', 2200.00, 2200.00, 30.00, '1室1厅', '6/18层', '6', '空调,热水器,宽带,洗衣机', '房东周', '13800000007', 145, 1, UNIX_TIMESTAMP()-604800, UNIX_TIMESTAMP()),
(100000, 1, 7, '南校区', 'SHARE', '中大南校区合租', '三室一厅，环境优美', '["https://picsum.photos/400/300?random=4018","https://picsum.photos/400/300?random=4019"]', 'https://picsum.photos/400/300?random=4018', '海珠区新港西路200号', 'MONTH', 1800.00, 1800.00, 15.00, '次卧', '4/8层', '7', '空调,宽带', '房东吴', '13800000008', 112, 1, UNIX_TIMESTAMP()-691200, UNIX_TIMESTAMP()),
(100000, 1, 8, '主校区', 'RENT', '华科主校区两居室', '近校门，生活便利', '["https://picsum.photos/400/300?random=4020","https://picsum.photos/400/300?random=4021","https://picsum.photos/400/300?random=4022"]', 'https://picsum.photos/400/300?random=4020', '洪山区珞喻路800号', 'MONTH', 3200.00, 3200.00, 55.00, '2室1厅', '7/15层', '8', '空调,洗衣机,冰箱,热水器,宽带', '房东郑', '13800000009', 198, 1, UNIX_TIMESTAMP()-777600, UNIX_TIMESTAMP()),
(100000, 1, 9, '兴庆校区', 'RENT', '西交大兴庆单间', '独立卫浴，拎包入住', '["https://picsum.photos/400/300?random=4023","https://picsum.photos/400/300?random=4024"]', 'https://picsum.photos/400/300?random=4023', '碑林区咸宁西路50号', 'MONTH', 1600.00, 1600.00, 20.00, '1室0厅', '3/6层', '9', '空调,热水器,宽带', '房东冯', '13800000010', 87, 1, UNIX_TIMESTAMP()-864000, UNIX_TIMESTAMP());

-- ========================================
-- 8. 树洞数据（添加school_id字段，随机1-10）
-- ========================================
INSERT INTO `niu_xiaoyuan_community` (`site_id`, `member_id`, `school_id`, `category_id`, `title`, `content`, `images`, `view_count`, `like_count`, `comment_count`, `status`, `create_time`, `update_time`) VALUES
(100000, 1, 1, 1, '今天食堂的饭太难吃了', '中午去食堂吃饭，排了半小时队，结果菜都凉了，而且味道一言难尽...', '["https://picsum.photos/400/300?random=5001"]', 356, 45, 23, 1, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 1, 2, 2, '求推荐高数辅导书', '高数期末考试要来了，有没有好用的辅导书推荐？', '[]', 234, 12, 18, 1, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 1, 3, 3, '暗恋的人有对象了', '暗恋了两年的人今天发朋友圈秀恩爱，心碎了...', '[]', 567, 89, 45, 1, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 1, 4, 4, '有人知道图书馆几点关门吗', '最近要备考，想知道图书馆的开放时间', '[]', 123, 5, 8, 1, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 1, 5, 5, '今天在校园里看到一只猫', '超级可爱的橘猫，在图书馆门口晒太阳', '["https://picsum.photos/400/300?random=5002","https://picsum.photos/400/300?random=5003"]', 789, 156, 67, 1, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
(100000, 1, 6, 6, '发现一家超好吃的面馆', '学校北门外新开的面馆，牛肉面绝了！', '["https://picsum.photos/400/300?random=5004"]', 445, 78, 34, 1, UNIX_TIMESTAMP()-21600, UNIX_TIMESTAMP()),
(100000, 1, 7, 1, '室友打呼噜怎么办', '每天晚上都被室友的呼噜声吵醒，快崩溃了', '[]', 289, 34, 56, 1, UNIX_TIMESTAMP()-25200, UNIX_TIMESTAMP()),
(100000, 1, 8, 2, '考研还是就业？', '大三了，不知道该考研还是直接找工作，求建议', '[]', 678, 45, 89, 1, UNIX_TIMESTAMP()-28800, UNIX_TIMESTAMP()),
(100000, 1, 9, 4, '校园卡丢了怎么补办', '今天发现校园卡不见了，有人知道去哪里补办吗？', '[]', 156, 8, 12, 1, UNIX_TIMESTAMP()-32400, UNIX_TIMESTAMP()),
(100000, 1, 10, 5, '今天的晚霞好美', '在操场拍的晚霞，分享给大家', '["https://picsum.photos/400/300?random=5005","https://picsum.photos/400/300?random=5006"]', 890, 234, 45, 1, UNIX_TIMESTAMP()-36000, UNIX_TIMESTAMP());

-- ========================================
-- 9. 表白墙数据
-- ========================================
INSERT INTO `niu_xiaoyuan_confession` (`site_id`, `member_id`, `school_id`, `campus`, `target_type`, `target_name`, `content`, `images`, `is_anonymous`, `view_count`, `like_count`, `comment_count`, `status`, `create_time`, `update_time`) VALUES
(100000, 1, 1, '主校区', 'PERSON', '图书馆三楼靠窗的女生', '每天都能看到你在那里学习，你专注的样子真的很美，希望有机会认识你', '[]', 1, 567, 89, 34, 1, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 1, 1, '主校区', 'PERSON', '计算机系的学长', '上次在实验室帮我解决了bug，真的太感谢了，想请你吃饭表示感谢', '[]', 0, 345, 56, 23, 1, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 1, 2, '主校区', 'GROUP', '北大篮球队', '你们打球的样子真的太帅了，每次路过球场都忍不住多看几眼', '["https://picsum.photos/400/300?random=6001"]', 1, 234, 45, 12, 1, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 1, 3, '邯郸校区', 'PERSON', '食堂打饭的小姐姐', '每次去打饭你都会多给我一点，谢谢你的小温暖', '[]', 1, 456, 78, 45, 1, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 1, 4, '闵行校区', 'PERSON', '晨跑时遇到的男生', '每天早上都能在操场遇到你，你跑步的姿势很专业，想认识你', '[]', 0, 289, 34, 18, 1, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
(100000, 1, 5, '紫金港校区', 'GROUP', '浙大舞蹈社', '你们的表演太精彩了，每次看都很感动', '["https://picsum.photos/400/300?random=6002"]', 1, 678, 123, 56, 1, UNIX_TIMESTAMP()-21600, UNIX_TIMESTAMP()),
(100000, 1, 6, '仙林校区', 'PERSON', '英语课的同桌', '虽然我们只是同桌，但我真的很喜欢和你聊天的感觉', '[]', 1, 345, 67, 29, 1, UNIX_TIMESTAMP()-25200, UNIX_TIMESTAMP()),
(100000, 1, 7, '南校区', 'PERSON', '快递站的小哥', '每次取快递你都很热情，笑容很治愈', '[]', 0, 234, 45, 15, 1, UNIX_TIMESTAMP()-28800, UNIX_TIMESTAMP()),
(100000, 1, 8, '主校区', 'GROUP', '华科吉他社', '你们的弹唱真的太好听了，希望能加入你们', '["https://picsum.photos/400/300?random=6003"]', 1, 456, 89, 34, 1, UNIX_TIMESTAMP()-32400, UNIX_TIMESTAMP()),
(100000, 1, 9, '兴庆校区', 'PERSON', '自习室对面的女生', '我们总是在同一个位置自习，想问问你愿不愿意一起吃个饭', '[]', 1, 567, 98, 45, 1, UNIX_TIMESTAMP()-36000, UNIX_TIMESTAMP());

-- ========================================
-- 10. 拼单好饭数据
-- ========================================
INSERT INTO `niu_xiaoyuan_group_order` (`site_id`, `group_no`, `member_id`, `school_id`, `campus`, `group_type`, `title`, `content`, `images`, `shop_name`, `shop_address`, `delivery_address`, `min_members`, `max_members`, `current_members`, `per_price`, `total_price`, `delivery_fee`, `deadline`, `status`, `create_time`, `update_time`) VALUES
(100000, 'GROUP202402240001', 1, 1, '主校区', 'TEA', '拼一点点奶茶', '凑够5人免配送费，大家一起点', '["https://picsum.photos/400/300?random=7001"]', '一点点(清华店)', '清华东门外', '紫荆公寓门口', 3, 10, 2, 15.00, 30.00, 0.00, UNIX_TIMESTAMP()+7200, 0, UNIX_TIMESTAMP()-1800, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240002', 1, 1, '主校区', 'FOOD', '拼麦当劳外卖', '想吃麦当劳，一起拼单省配送费', '["https://picsum.photos/400/300?random=7002"]', '麦当劳(清华店)', '清华西门外', '图书馆门口', 2, 5, 1, 35.00, 35.00, 5.00, UNIX_TIMESTAMP()+10800, 0, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240003', 1, 2, '主校区', 'FRUIT', '拼水果', '想买点水果，一起拼单便宜', '["https://picsum.photos/400/300?random=7003"]', '百果园(北大店)', '北大东门外', '畅春园宿舍', 3, 8, 3, 20.00, 60.00, 0.00, UNIX_TIMESTAMP()+14400, 0, UNIX_TIMESTAMP()-5400, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240004', 1, 3, '邯郸校区', 'TEA', '拼喜茶', '新品多肉葡萄，有人一起吗', '["https://picsum.photos/400/300?random=7004"]', '喜茶(复旦店)', '国权路', '南区宿舍', 4, 10, 2, 25.00, 50.00, 0.00, UNIX_TIMESTAMP()+18000, 0, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240005', 1, 4, '闵行校区', 'FOOD', '拼肯德基', '想吃炸鸡，凑单更划算', '["https://picsum.photos/400/300?random=7005"]', '肯德基(交大店)', '东川路', '东区宿舍', 2, 6, 1, 40.00, 40.00, 6.00, UNIX_TIMESTAMP()+21600, 0, UNIX_TIMESTAMP()-9000, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240006', 1, 5, '紫金港校区', 'RIDE', '拼车去火车站', '周五下午去杭州东站，有人一起吗', '[]', '', '', '杭州东站', 2, 4, 1, 30.00, 30.00, 0.00, UNIX_TIMESTAMP()+86400, 0, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240007', 1, 6, '仙林校区', 'TEA', '拼瑞幸咖啡', '下午茶时间，一起点咖啡', '["https://picsum.photos/400/300?random=7006"]', '瑞幸咖啡(南大店)', '仙林大道', '敬文书院', 3, 8, 4, 18.00, 72.00, 0.00, UNIX_TIMESTAMP()+7200, 0, UNIX_TIMESTAMP()-12600, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240008', 1, 7, '南校区', 'FOOD', '拼海底捞外卖', '想吃火锅，一起拼单', '["https://picsum.photos/400/300?random=7007"]', '海底捞(中大店)', '新港西路', '至善园', 4, 8, 2, 80.00, 160.00, 0.00, UNIX_TIMESTAMP()+25200, 0, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240009', 1, 8, '主校区', 'FRUIT', '拼西瓜', '夏天到了，一起买个大西瓜', '["https://picsum.photos/400/300?random=7008"]', '水果店', '珞喻路', '韵苑宿舍', 3, 6, 2, 10.00, 20.00, 0.00, UNIX_TIMESTAMP()+10800, 0, UNIX_TIMESTAMP()-16200, UNIX_TIMESTAMP()),
(100000, 'GROUP202402240010', 1, 9, '兴庆校区', 'TEA', '拼茶百道', '新品杨枝甘露，有人一起吗', '["https://picsum.photos/400/300?random=7009"]', '茶百道(西交大店)', '咸宁西路', '东区宿舍', 3, 10, 3, 16.00, 48.00, 0.00, UNIX_TIMESTAMP()+14400, 0, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP());

-- ========================================
-- 11. 签到配置数据
-- ========================================
INSERT INTO `niu_xiaoyuan_sign_config` (`site_id`, `base_reward`, `continuous_rewards`, `status`, `create_time`, `update_time`) VALUES
(100000, 10, '{"3":20,"7":50,"15":100,"30":200}', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 12. 包裹规格价格数据
-- ========================================
INSERT INTO `niu_xiaoyuan_package_price` (`site_id`, `size`, `name`, `description`, `price`, `sort`, `status`, `create_time`) VALUES
(100000, 'small', '小件', '信封、文件、小包裹', 3.00, 1, 1, UNIX_TIMESTAMP()),
(100000, 'medium', '中件', '鞋盒大小', 5.00, 2, 1, UNIX_TIMESTAMP()),
(100000, 'large', '大件', '行李箱大小', 8.00, 3, 1, UNIX_TIMESTAMP()),
(100000, 'xlarge', '超大件', '大型包裹', 12.00, 4, 1, UNIX_TIMESTAMP());

-- ========================================
-- 13. 跑腿员等级配置
-- ========================================
INSERT INTO `niu_xiaoyuan_runner_level` (`site_id`, `level`, `name`, `min_orders`, `commission_rate`, `status`, `create_time`) VALUES
(100000, 1, '新手接单员', 0, 70, 1, UNIX_TIMESTAMP()),
(100000, 2, '初级接单员', 10, 75, 1, UNIX_TIMESTAMP()),
(100000, 3, '中级接单员', 50, 80, 1, UNIX_TIMESTAMP()),
(100000, 4, '高级接单员', 100, 85, 1, UNIX_TIMESTAMP()),
(100000, 5, '金牌接单员', 200, 90, 1, UNIX_TIMESTAMP());

-- ========================================
-- 14. 校园认证数据 (测试用户已认证)
-- ========================================
INSERT INTO `niu_xiaoyuan_campus_auth` (`site_id`, `member_id`, `school_id`, `campus`, `campus_name`, `real_name`, `id_card`, `student_no`, `college`, `major`, `grade`, `identity_type`, `cert_image`, `status`, `audit_time`, `create_time`, `update_time`) VALUES
(100000, 1, 1, '主校区', '清华大学', '测试用户', '110101199001011234', '2020001001', '计算机学院', '计算机科学与技术', '2020级', 'STUDENT', 'https://picsum.photos/400/300?random=8001', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 15. 跑腿员数据 (测试用户已成为跑腿员)
-- ========================================
INSERT INTO `niu_xiaoyuan_runner` (`site_id`, `member_id`, `real_name`, `mobile`, `avatar`, `student_cert`, `level`, `level_name`, `commission_rate`, `total_orders`, `complete_orders`, `completed_orders`, `score`, `balance`, `freeze_balance`, `total_income`, `status`, `is_online`, `school_id`, `campus`, `daily_order_limit`, `today_orders`, `create_time`, `update_time`) VALUES
(100000, 1, '测试用户', '13800000001', 'https://picsum.photos/100/100?random=9001', 'https://picsum.photos/400/300?random=9002', 3, '中级接单员', 80, 56, 52, 52, 4.8, 520.00, 0.00, 1560.00, 1, 1, 1, '主校区', 20, 3, UNIX_TIMESTAMP()-2592000, UNIX_TIMESTAMP());

-- ========================================
-- 16. 用户信誉分数据
-- ========================================
INSERT INTO `niu_xiaoyuan_credit` (`site_id`, `member_id`, `credit_score`, `total_complete`, `total_cancel`, `total_complaint`, `is_restricted`, `create_time`, `update_time`) VALUES
(100000, 1, 98, 52, 2, 0, 0, UNIX_TIMESTAMP()-2592000, UNIX_TIMESTAMP());

-- ========================================
-- 17. 常用地址数据
-- ========================================
INSERT INTO `niu_xiaoyuan_address` (`site_id`, `member_id`, `name`, `mobile`, `address`, `address_type`, `building`, `room`, `is_default`, `create_time`, `update_time`) VALUES
(100000, 1, '张同学', '13800000001', '清华大学紫荆公寓3号楼', 'DORM', '3号楼', '201', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, 1, '张同学', '13800000001', '清华大学图书馆', 'TEACHING', '图书馆', '三楼', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, 1, '张同学', '13800000001', '清华大学东门菜鸟驿站', 'EXPRESS', '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 18. 优惠券数据
-- ========================================
INSERT INTO `niu_xiaoyuan_coupon` (`site_id`, `name`, `type`, `discount_value`, `min_amount`, `total_count`, `received_count`, `used_count`, `limit_per_user`, `valid_days`, `start_time`, `end_time`, `status`, `create_time`, `update_time`) VALUES
(100000, '新人专享2元券', 'REDUCE', 2.00, 5.00, 1000, 156, 89, 1, 30, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()+2592000, 1, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()),
(100000, '满10减3元', 'REDUCE', 3.00, 10.00, 500, 234, 123, 3, 15, UNIX_TIMESTAMP()-172800, UNIX_TIMESTAMP()+1728000, 1, UNIX_TIMESTAMP()-172800, UNIX_TIMESTAMP()),
(100000, '8折优惠券', 'DISCOUNT', 0.80, 15.00, 200, 78, 45, 2, 7, UNIX_TIMESTAMP()-259200, UNIX_TIMESTAMP()+604800, 1, UNIX_TIMESTAMP()-259200, UNIX_TIMESTAMP());

-- ========================================
-- 19. DIY装修页面数据
-- ========================================
INSERT INTO `niu_diy_page` (`site_id`, `name`, `type`, `title`, `page_title`, `template`, `mode`, `value`, `is_default`, `share`, `visit_count`, `create_time`, `update_time`) VALUES
(100000, 'DIY_SD_XIAOYUAN_INDEX', 'DIY_SD_XIAOYUAN_INDEX', '校园帮首页', '校园帮', 'sd_xiaoyuan_index_default', 'diy', '{"global":{"title":"校园帮","pageStartBgColor":"#f7f7f7","pageEndBgColor":"#f7f7f7","pageGradientAngle":"to bottom","bgUrl":"","bgHeightScale":100,"topStatusBar":{"control":true,"isShow":true,"bgColor":"#c0fe95","isTransparent":false,"style":"style-1","textColor":"#333333","textAlign":"center"},"bottomTabBar":{"isShow":true,"control":true},"popWindow":{"imgUrl":"","count":-1,"show":0,"link":{"name":""}},"template":{"textColor":"#303133","pageStartBgColor":"","pageEndBgColor":"","pageGradientAngle":"to bottom","componentStartBgColor":"","componentEndBgColor":"","componentGradientAngle":"to bottom","topRounded":0,"bottomRounded":0,"margin":{"top":0,"bottom":0,"both":12}}},"value":[{"path":"edit-xiaoyuan-header","uses":1,"id":"xiaoyuan_header_1","componentName":"XiaoyuanHeader","componentTitle":"顶部导航","bgStartColor":"#c0fe95","bgEndColor":"#e8ffcc","schoolName":"北京大学","schoolUrl":"/addon/sd_xiaoyuan/pages/school/select","taskCount":0,"taskLabel":"今日任务","taskUrl":"/addon/sd_xiaoyuan/pages/order/hall","earningAmount":"0.00","earningLabel":"累计佣金","earningUrl":"/addon/sd_xiaoyuan/pages/runner/index","searchPlaceholder":"搜索任务/服务","searchUrl":"/addon/sd_xiaoyuan/pages/search/index","messageUrl":"/addon/sd_xiaoyuan/pages/message/index","showPromoCard":true,"promoTitle":"校园帮实名认证","promoSubtitle":"安全可靠，快速认证","promoBtnText":"GO","promoIcon":"account-fill","promoUrl":"/addon/sd_xiaoyuan/pages/campus/auth","margin":{"top":0,"bottom":0,"both":0}},{"path":"edit-xiaoyuan-notice","uses":1,"id":"xiaoyuan_notice_1","componentName":"XiaoyuanNotice","componentTitle":"公告栏","text":"欢迎使用校园帮，有问题请联系客服~","bgColor":"#fff7e6","textColor":"#ff9500","isShow":true,"margin":{"top":0,"bottom":10,"both":10}},{"path":"edit-xiaoyuan-menu-grid","uses":1,"id":"xiaoyuan_menu_grid_1","componentName":"XiaoyuanMenuGrid","componentTitle":"功能菜单","style":"style-1","column":5,"list":[{"name":"帮我买","icon":"shopping-cart-fill","iconColor":"#ff6b00","bgColor":"#fff4e6","url":"/addon/sd_xiaoyuan/pages/buy/create","isShow":true},{"name":"帮我送","icon":"car","iconColor":"#13c2c2","bgColor":"#e6fffb","url":"/addon/sd_xiaoyuan/pages/send/create","isShow":true},{"name":"代取快递","icon":"gift-fill","iconColor":"#52c41a","bgColor":"#f6ffed","url":"/addon/sd_xiaoyuan/pages/express/pickup","isShow":true},{"name":"帮打印","icon":"file-text-fill","iconColor":"#ff9800","bgColor":"#fff8e1","url":"/addon/sd_xiaoyuan/pages/print/create","isShow":true},{"name":"扔垃圾","icon":"trash-fill","iconColor":"#9c27b0","bgColor":"#f3e5f5","url":"/addon/sd_xiaoyuan/pages/trash/create","isShow":true},{"name":"帮搬运","icon":"car-fill","iconColor":"#4caf50","bgColor":"#e8f5e9","url":"/addon/sd_xiaoyuan/pages/carry/create","isShow":true},{"name":"代清洁","icon":"star-fill","iconColor":"#2196f3","bgColor":"#e3f2fd","url":"/addon/sd_xiaoyuan/pages/clean/create","isShow":true},{"name":"帮帮忙","icon":"question-circle-fill","iconColor":"#e91e63","bgColor":"#fce4ec","url":"/addon/sd_xiaoyuan/pages/help/create","isShow":true},{"name":"表白墙","icon":"heart-fill","iconColor":"#fa709a","bgColor":"#fff0f5","url":"/addon/sd_xiaoyuan/pages/confession/index","isShow":true},{"name":"游戏陪练","icon":"red-packet-fill","iconColor":"#ff7243","bgColor":"#fff3e0","url":"/addon/sd_xiaoyuan/pages/game/publish","isShow":true}],"componentStartBgColor":"#ffffff","componentEndBgColor":"#ffffff","topRounded":12,"bottomRounded":12,"margin":{"top":10,"bottom":10,"both":10}},{"path":"edit-xiaoyuan-order-hall","uses":1,"id":"xiaoyuan_order_hall_1","componentName":"XiaoyuanOrderHall","componentTitle":"订单大厅","title":"任务大厅","showMore":true,"moreText":"查看更多","moreUrl":"/addon/sd_xiaoyuan/pages/order/hall","num":10,"tabs":[{"name":"全部","type":"all","isShow":true},{"name":"代取快递","type":"EXPRESS","isShow":true},{"name":"帮我买","type":"BUY","isShow":true},{"name":"代打印","type":"PRINT","isShow":true},{"name":"扔垃圾","type":"TRASH","isShow":true},{"name":"帮搬运","type":"CARRY","isShow":true},{"name":"代清洁","type":"CLEAN","isShow":true},{"name":"帮帮忙","type":"HELP","isShow":true},{"name":"游戏陪玩","type":"GAME","isShow":true}],"componentStartBgColor":"#ffffff","componentEndBgColor":"#ffffff","topRounded":12,"bottomRounded":12,"margin":{"top":10,"bottom":10,"both":10}}]}', 1, '{"wechat":{"title":"校园帮","desc":"校园生活服务平台","url":""},"weapp":{"title":"校园帮","url":""}}', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 20. 积分商城商品数据
-- ========================================
INSERT INTO `niu_xiaoyuan_points_goods` (`site_id`, `name`, `image`, `description`, `points_price`, `market_price`, `category_id`, `stock`, `sold_count`, `exchange_count`, `exchange_limit`, `is_recommend`, `sort`, `status`, `start_time`, `end_time`, `create_time`, `update_time`) VALUES
(100000, '校园帮定制帆布袋', 'https://picsum.photos/400/400?random=101', '校园帮专属定制帆布袋，环保时尚，容量大', 100, 39.00, 0, 500, 56, 56, 2, 1, 1, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '星巴克咖啡券', 'https://picsum.photos/400/400?random=102', '星巴克中杯饮品兑换券，全国通用', 200, 35.00, 0, 200, 89, 89, 1, 1, 2, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '小米充电宝10000mAh', 'https://picsum.photos/400/400?random=103', '小米移动电源，10000mAh大容量，双向快充', 500, 99.00, 0, 50, 23, 23, 1, 0, 3, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '网易云音乐VIP月卡', 'https://picsum.photos/400/400?random=104', '网易云音乐黑胶VIP会员月卡，畅听无损音乐', 80, 15.00, 0, 1000, 234, 234, 3, 1, 4, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '肯德基30元代金券', 'https://picsum.photos/400/400?random=105', '肯德基30元电子代金券，全国门店通用', 150, 30.00, 0, 300, 167, 167, 2, 0, 5, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '校园帮定制笔记本', 'https://picsum.photos/400/400?random=106', 'A5精装笔记本，优质纸张，校园帮专属设计', 50, 19.00, 0, 800, 312, 312, 5, 0, 6, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '美团外卖红包10元', 'https://picsum.photos/400/400?random=107', '美团外卖满20减10元红包，限时使用', 60, 10.00, 0, 500, 445, 445, 3, 1, 7, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '爱奇艺VIP周卡', 'https://picsum.photos/400/400?random=108', '爱奇艺黄金VIP会员周卡，追剧必备', 40, 12.00, 0, 1000, 567, 567, 5, 0, 8, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '校园帮定制雨伞', 'https://picsum.photos/400/400?random=109', '三折自动晴雨伞，防晒防雨，校园帮专属', 120, 29.00, 0, 200, 78, 78, 2, 0, 9, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, '瑞幸咖啡券', 'https://picsum.photos/400/400?random=110', '瑞幸咖啡任意饮品兑换券', 100, 20.00, 0, 400, 189, 189, 2, 1, 10, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 21. 大厅订单数据（每个学校每种类型5个）
-- ========================================
-- 清华大学订单 school_id=1
INSERT INTO `niu_xiaoyuan_order` (`site_id`, `order_no`, `member_id`, `runner_id`, `school_id`, `campus`, `task_type`, `status`, `pickup_name`, `pickup_mobile`, `pickup_address`, `receive_name`, `receive_mobile`, `receive_address`, `task_desc`, `remark`, `base_fee`, `total_fee`, `actual_fee`, `create_time`, `update_time`) VALUES
-- EXPRESS 代取快递
(100000, 'XY202402240001', 116, 0, 1, '主校区', 'EXPRESS', 10, '', '', '菜鸟驿站(清华东门)', '张同学', '13800000001', '紫荆公寓3号楼201', '小件包裹1个', '取件码：12-3-456', 3.00, 3.00, 3.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240002', 116, 0, 1, '主校区', 'EXPRESS', 10, '', '', '丰巢快递柜(紫荆公寓)', '李同学', '13800000002', '紫荆公寓5号楼302', '中件包裹2个', '取件码：A12345', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240003', 116, 0, 1, '主校区', 'EXPRESS', 10, '', '', '顺丰速运(清华西门)', '王同学', '13800000003', '紫荆公寓8号楼105', '大件包裹1个', '请轻拿轻放', 8.00, 8.00, 8.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240004', 116, 0, 1, '昌平校区', 'EXPRESS', 10, '', '', '京东快递(学生服务中心)', '赵同学', '13800000004', '学生公寓2号楼401', '小件包裹3个', '取件码：JD8899', 4.00, 4.00, 4.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240005', 116, 0, 1, '主校区', 'EXPRESS', 10, '', '', '中通快递(南门)', '钱同学', '13800000005', '紫荆公寓1号楼601', '中件包裹1个', '取件码：ZT123456', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
-- BUY 帮我买
(100000, 'XY202402240006', 116, 0, 1, '主校区', 'BUY', 10, '', '', '', '张同学', '13800000001', '紫荆公寓3号楼201', '帮买一杯奶茶', '要少糖去冰', 5.00, 20.00, 20.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240007', 116, 0, 1, '主校区', 'BUY', 10, '', '', '', '李同学', '13800000002', '紫荆公寓5号楼302', '帮买午餐', '食堂二楼红烧肉套餐', 5.00, 18.00, 18.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240008', 116, 0, 1, '主校区', 'BUY', 10, '', '', '', '王同学', '13800000003', '紫荆公寓8号楼105', '帮买文具', '需要中性笔和笔记本', 5.00, 25.00, 25.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240009', 116, 0, 1, '昌平校区', 'BUY', 10, '', '', '', '赵同学', '13800000004', '学生公寓2号楼401', '帮买水果', '苹果和香蕉各一斤', 5.00, 30.00, 30.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240010', 116, 0, 1, '主校区', 'BUY', 10, '', '', '', '钱同学', '13800000005', '紫荆公寓1号楼601', '帮买咖啡', '星巴克美式大杯', 5.00, 35.00, 35.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
-- PRINT 代打印
(100000, 'XY202402240011', 116, 0, 1, '主校区', 'PRINT', 10, '', '', '', '张同学', '13800000001', '紫荆公寓3号楼201', '打印论文20页', '双面黑白A4', 3.00, 5.00, 5.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240012', 116, 0, 1, '主校区', 'PRINT', 10, '', '', '', '李同学', '13800000002', '紫荆公寓5号楼302', '打印PPT讲义', '单面彩色A4 30页', 5.00, 15.00, 15.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240013', 116, 0, 1, '主校区', 'PRINT', 10, '', '', '', '王同学', '13800000003', '紫荆公寓8号楼105', '打印简历5份', '单面彩色A4', 3.00, 8.00, 8.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240014', 116, 0, 1, '昌平校区', 'PRINT', 10, '', '', '', '赵同学', '13800000004', '学生公寓2号楼401', '打印复习资料', '双面黑白A4 50页', 3.00, 10.00, 10.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240015', 116, 0, 1, '主校区', 'PRINT', 10, '', '', '', '钱同学', '13800000005', '紫荆公寓1号楼601', '打印海报', '彩色A3 2张', 5.00, 20.00, 20.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
-- SEAT 代占座
(100000, 'XY202402240016', 116, 0, 1, '主校区', 'SEAT', 10, '', '', '', '张同学', '13800000001', '图书馆3楼A区', '占座2个位置', '靠窗位置优先', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240017', 116, 0, 1, '主校区', 'SEAT', 10, '', '', '', '李同学', '13800000002', '自习室B201', '占座1个位置', '需要插座', 3.00, 3.00, 3.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240018', 116, 0, 1, '主校区', 'SEAT', 10, '', '', '', '王同学', '13800000003', '图书馆5楼', '占座3个位置', '小组讨论用', 8.00, 8.00, 8.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240019', 116, 0, 1, '昌平校区', 'SEAT', 10, '', '', '', '赵同学', '13800000004', '教学楼C301', '占座1个位置', '前排位置', 3.00, 3.00, 3.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240020', 116, 0, 1, '主校区', 'SEAT', 10, '', '', '', '钱同学', '13800000005', '图书馆2楼', '占座2个位置', '安静区域', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
-- 北京大学订单 school_id=2
(100000, 'XY202402240021', 116, 0, 2, '主校区', 'EXPRESS', 10, '', '', '菜鸟驿站(北大东门)', '孙同学', '13800000006', '畅春园1号楼101', '小件包裹2个', '取件码：CN789', 4.00, 4.00, 4.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240022', 116, 0, 2, '主校区', 'EXPRESS', 10, '', '', '丰巢快递柜(畅春园)', '周同学', '13800000007', '畅春园3号楼205', '中件包裹1个', '取件码：FC456', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240023', 116, 0, 2, '主校区', 'EXPRESS', 10, '', '', '顺丰速运(北大西门)', '吴同学', '13800000008', '畅春园5号楼308', '大件包裹1个', '易碎物品', 8.00, 8.00, 8.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240024', 116, 0, 2, '昌平校区', 'EXPRESS', 10, '', '', '京东快递(百年讲堂)', '郑同学', '13800000009', '学生公寓A栋402', '小件包裹1个', '取件码：JD1234', 3.00, 3.00, 3.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240025', 116, 0, 2, '主校区', 'EXPRESS', 10, '', '', '圆通快递(南门)', '冯同学', '13800000010', '畅春园2号楼506', '中件包裹2个', '取件码：YT5678', 6.00, 6.00, 6.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
(100000, 'XY202402240026', 116, 0, 2, '主校区', 'BUY', 10, '', '', '', '孙同学', '13800000006', '畅春园1号楼101', '帮买早餐', '包子豆浆', 3.00, 15.00, 15.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240027', 116, 0, 2, '主校区', 'BUY', 10, '', '', '', '周同学', '13800000007', '畅春园3号楼205', '帮买零食', '薯片和饮料', 3.00, 25.00, 25.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240028', 116, 0, 2, '主校区', 'BUY', 10, '', '', '', '吴同学', '13800000008', '畅春园5号楼308', '帮买药品', '感冒药', 5.00, 30.00, 30.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240029', 116, 0, 2, '昌平校区', 'BUY', 10, '', '', '', '郑同学', '13800000009', '学生公寓A栋402', '帮买晚餐', '麻辣烫', 5.00, 28.00, 28.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240030', 116, 0, 2, '主校区', 'BUY', 10, '', '', '', '冯同学', '13800000010', '畅春园2号楼506', '帮买奶茶', '一点点波霸奶茶', 3.00, 18.00, 18.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
(100000, 'XY202402240031', 116, 0, 2, '主校区', 'PRINT', 10, '', '', '', '孙同学', '13800000006', '畅春园1号楼101', '打印作业', '双面黑白15页', 3.00, 4.00, 4.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240032', 116, 0, 2, '主校区', 'PRINT', 10, '', '', '', '周同学', '13800000007', '畅春园3号楼205', '打印报告', '单面彩色20页', 5.00, 12.00, 12.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240033', 116, 0, 2, '主校区', 'PRINT', 10, '', '', '', '吴同学', '13800000008', '畅春园5号楼308', '打印证件照', '一寸照片8张', 5.00, 10.00, 10.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240034', 116, 0, 2, '昌平校区', 'PRINT', 10, '', '', '', '郑同学', '13800000009', '学生公寓A栋402', '打印教材', '双面黑白100页', 5.00, 20.00, 20.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240035', 116, 0, 2, '主校区', 'PRINT', 10, '', '', '', '冯同学', '13800000010', '畅春园2号楼506', '打印试卷', '单面黑白30页', 3.00, 6.00, 6.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP()),
(100000, 'XY202402240036', 116, 0, 2, '主校区', 'SEAT', 10, '', '', '', '孙同学', '13800000006', '图书馆东区', '占座1个位置', '安静学习', 3.00, 3.00, 3.00, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()),
(100000, 'XY202402240037', 116, 0, 2, '主校区', 'SEAT', 10, '', '', '', '周同学', '13800000007', '自习室A101', '占座2个位置', '需要电源', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-7200, UNIX_TIMESTAMP()),
(100000, 'XY202402240038', 116, 0, 2, '主校区', 'SEAT', 10, '', '', '', '吴同学', '13800000008', '图书馆西区', '占座1个位置', '靠窗', 3.00, 3.00, 3.00, UNIX_TIMESTAMP()-10800, UNIX_TIMESTAMP()),
(100000, 'XY202402240039', 116, 0, 2, '昌平校区', 'SEAT', 10, '', '', '', '郑同学', '13800000009', '教学楼B201', '占座3个位置', '小组学习', 8.00, 8.00, 8.00, UNIX_TIMESTAMP()-14400, UNIX_TIMESTAMP()),
(100000, 'XY202402240040', 116, 0, 2, '主校区', 'SEAT', 10, '', '', '', '冯同学', '13800000010', '图书馆3楼', '占座2个位置', '考研复习', 5.00, 5.00, 5.00, UNIX_TIMESTAMP()-18000, UNIX_TIMESTAMP());

-- ========================================
-- 22. 课程表数据（member_id=116）
-- ========================================
INSERT INTO `niu_xiaoyuan_schedule` (`site_id`, `member_id`, `school_id`, `semester`, `week_start`, `total_weeks`, `schedule_data`, `create_time`, `update_time`) VALUES
(100000, 116, 1, '2024-2025-1', '2024-09-02', 20, '[{"day":1,"section":1,"name":"高等数学","teacher":"张教授","location":"教学楼A101","weeks":"1-16"},{"day":1,"section":3,"name":"大学英语","teacher":"李老师","location":"外语楼B201","weeks":"1-18"},{"day":2,"section":1,"name":"线性代数","teacher":"王教授","location":"教学楼A203","weeks":"1-16"},{"day":2,"section":3,"name":"程序设计","teacher":"刘老师","location":"计算机楼C301","weeks":"1-18"},{"day":3,"section":1,"name":"大学物理","teacher":"陈教授","location":"理学楼D102","weeks":"1-16"},{"day":3,"section":3,"name":"体育","teacher":"赵老师","location":"体育馆","weeks":"1-18"},{"day":4,"section":1,"name":"高等数学","teacher":"张教授","location":"教学楼A101","weeks":"1-16"},{"day":4,"section":3,"name":"思想政治","teacher":"孙老师","location":"教学楼B301","weeks":"1-18"},{"day":5,"section":1,"name":"程序设计实验","teacher":"刘老师","location":"计算机楼C401","weeks":"1-18"},{"day":5,"section":3,"name":"大学英语","teacher":"李老师","location":"外语楼B201","weeks":"1-18"}]', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(100000, 116, 2, '2024-2025-1', '2024-09-02', 20, '[{"day":1,"section":1,"name":"微积分","teacher":"周教授","location":"理科楼101","weeks":"1-16"},{"day":1,"section":3,"name":"英语听说","teacher":"吴老师","location":"外语楼201","weeks":"1-18"},{"day":2,"section":1,"name":"概率论","teacher":"郑教授","location":"理科楼203","weeks":"1-16"},{"day":2,"section":3,"name":"数据结构","teacher":"钱老师","location":"信息楼301","weeks":"1-18"},{"day":3,"section":1,"name":"离散数学","teacher":"孙教授","location":"理科楼102","weeks":"1-16"},{"day":3,"section":3,"name":"羽毛球","teacher":"李老师","location":"体育馆","weeks":"1-18"},{"day":4,"section":1,"name":"微积分","teacher":"周教授","location":"理科楼101","weeks":"1-16"},{"day":4,"section":3,"name":"中国近代史","teacher":"王老师","location":"文科楼301","weeks":"1-18"},{"day":5,"section":1,"name":"数据结构实验","teacher":"钱老师","location":"信息楼401","weeks":"1-18"},{"day":5,"section":3,"name":"英语听说","teacher":"吴老师","location":"外语楼201","weeks":"1-18"}]', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 23. 课表设置数据（member_id=116）
-- ========================================
INSERT INTO `niu_xiaoyuan_schedule_setting` (`site_id`, `member_id`, `start_date`, `end_date`, `total_weeks`, `sections`, `create_time`, `update_time`) VALUES
(100000, 116, '2024-09-02', '2025-01-17', 20, '[{"start":"08:00","end":"08:45"},{"start":"08:55","end":"09:40"},{"start":"10:00","end":"10:45"},{"start":"10:55","end":"11:40"},{"start":"14:00","end":"14:45"},{"start":"14:55","end":"15:40"},{"start":"16:00","end":"16:45"},{"start":"16:55","end":"17:40"},{"start":"19:00","end":"19:45"},{"start":"19:55","end":"20:40"}]', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ========================================
-- 24. 游戏陪玩数据
-- ========================================
INSERT INTO `niu_xiaoyuan_game_companion` (`site_id`, `member_id`, `school_id`, `game_type`, `game_name`, `rank_level`, `service_type`, `title`, `content`, `images`, `price`, `unit`, `voice_chat`, `online_time`, `view_count`, `order_count`, `score`, `status`, `is_top`, `create_time`, `update_time`) VALUES
(100000, 1, 1, 'WZRY', '荣耀王者小姐姐', '荣耀王者100星', 'PLAY_WITH', '王者荣耀陪玩上分', '国服百星荣耀王者，擅长打野和中单，带飞上分稳定', '["https://picsum.photos/400/300?random=9101","https://picsum.photos/400/300?random=9102"]', 30.00, '小时', 1, '19:00-24:00', 567, 89, 4.9, 1, 1, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()),
(100000, 1, 2, 'WZRY', '开心玩家', '永恒钻石', 'PLAY_WITH', '王者荣耀娱乐陪玩', '钻石段位，性格开朗，可语音开黑', '["https://picsum.photos/400/300?random=9103"]', 20.00, '小时', 1, '20:00-23:00', 345, 56, 4.7, 1, 0, UNIX_TIMESTAMP()-172800, UNIX_TIMESTAMP()),
(100000, 1, 3, 'LOL', 'ADC大神', '钻石I', 'PLAY_WITH', 'LOL钻石陪玩', '钻石ADC，擅长EZ、金克斯，稳定carry', '["https://picsum.photos/400/300?random=9104","https://picsum.photos/400/300?random=9105"]', 35.00, '小时', 1, '18:00-24:00', 456, 78, 4.8, 1, 1, UNIX_TIMESTAMP()-259200, UNIX_TIMESTAMP()),
(100000, 1, 4, 'LOL', '大师上分王', '大师', 'BOOST', 'LOL大师陪玩上分', '大师段位，全位置精通，带飞保底', '["https://picsum.photos/400/300?random=9106"]', 50.00, '小时', 1, '19:00-02:00', 678, 123, 4.9, 1, 0, UNIX_TIMESTAMP()-345600, UNIX_TIMESTAMP()),
(100000, 1, 5, 'PUBG', '吃鸡战神', '无敌战神', 'PLAY_WITH', '吃鸡陪玩带躺', '无敌战神，稳定吃鸡，带你躺赢', '["https://picsum.photos/400/300?random=9107","https://picsum.photos/400/300?random=9108"]', 25.00, '小时', 1, '20:00-24:00', 389, 67, 4.6, 1, 0, UNIX_TIMESTAMP()-432000, UNIX_TIMESTAMP()),
(100000, 1, 6, 'PUBG', '快乐吃鸡', '超级王牌', 'PLAY_WITH', '和平精英娱乐陪玩', '超级王牌，会聊天会开车，一起快乐吃鸡', '["https://picsum.photos/400/300?random=9109"]', 18.00, '小时', 1, '21:00-24:00', 234, 45, 4.5, 1, 0, UNIX_TIMESTAMP()-518400, UNIX_TIMESTAMP()),
(100000, 1, 7, 'YS', '原神大佬', '60级满探索', 'PLAY_WITH', '原神陪玩探索', '全角色满命，带你打深渊、刷圣遗物', '["https://picsum.photos/400/300?random=9110","https://picsum.photos/400/300?random=9111"]', 40.00, '小时', 1, '19:00-23:00', 512, 89, 4.8, 1, 1, UNIX_TIMESTAMP()-604800, UNIX_TIMESTAMP()),
(100000, 1, 8, 'YS', '提瓦特旅行者', '55级', 'PLAY_WITH', '原神剧情陪玩', '一起探索提瓦特大陆，讲解剧情', '["https://picsum.photos/400/300?random=9112"]', 22.00, '小时', 1, '18:00-22:00', 278, 34, 4.7, 1, 0, UNIX_TIMESTAMP()-691200, UNIX_TIMESTAMP()),
(100000, 1, 9, 'EGG', '永劫高手', '修罗', 'PLAY_WITH', '永劫无间陪玩上分', '修罗段位，擅长胡桃、妖刀姬', '["https://picsum.photos/400/300?random=9113","https://picsum.photos/400/300?random=9114"]', 35.00, '小时', 1, '20:00-01:00', 345, 56, 4.6, 1, 0, UNIX_TIMESTAMP()-777600, UNIX_TIMESTAMP()),
(100000, 1, 10, 'CSGO', 'CS大神', '全球精英', 'BOOST', 'CSGO陪玩上分', '全球精英，带你上分', '["https://picsum.photos/400/300?random=9115"]', 45.00, '小时', 1, '19:00-24:00', 423, 78, 4.9, 1, 1, UNIX_TIMESTAMP()-864000, UNIX_TIMESTAMP()),
(100000, 1, 1, 'OTHER', 'DOTA大神', '5500分', 'PLAY_WITH', 'DOTA2陪玩', '天梯5500分，擅长中单和大哥位', '["https://picsum.photos/400/300?random=9116","https://picsum.photos/400/300?random=9117"]', 40.00, '小时', 1, '20:00-02:00', 289, 45, 4.7, 1, 0, UNIX_TIMESTAMP()-950400, UNIX_TIMESTAMP()),
(100000, 1, 2, 'OTHER', '全能玩家', '多游戏高段位', 'PLAY_WITH', '各类游戏陪玩', '多游戏精通，可陪玩各类热门游戏', '["https://picsum.photos/400/300?random=9118"]', 15.00, '小时', 1, '全天', 156, 23, 4.5, 1, 0, UNIX_TIMESTAMP()-1036800, UNIX_TIMESTAMP());

-- ========================================
-- 完成！测试数据导入完毕
-- ========================================
