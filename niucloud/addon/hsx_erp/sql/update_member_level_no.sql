-- ============================================================
-- 会员等级"站内序号" level_no:让每个站点的等级从 1 开始独立编号,
-- 用于跨站统一引用(会员价/同行价等配置口径一致),不改 core 主键 level_id。
-- 维护方式:懒补号(MemberLevelNoService::ensureForSite),core 新建等级后下次读取时自动补号。
-- 本脚本为追加列 + 历史回填,可重复执行(列存在则忽略报错即可)。
-- ============================================================

-- 1) 追加列(若已存在会报 Duplicate column,可忽略)
ALTER TABLE `saas_member_level`
    ADD COLUMN `level_no` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '站内序号(每站从1开始,跨站统一引用口径)' AFTER `site_id`;

-- 2) 站内联合索引,按 (site_id, level_no) 快速取号
ALTER TABLE `saas_member_level`
    ADD INDEX `idx_site_level_no` (`site_id`, `level_no`);

-- 3) 历史数据回填:每个站点按 level_id 升序(创建顺序)从 1 编号
UPDATE `saas_member_level` ml
JOIN (
    SELECT level_id,
           ROW_NUMBER() OVER (PARTITION BY site_id ORDER BY level_id ASC) AS rn
    FROM `saas_member_level`
) seq ON seq.level_id = ml.level_id
SET ml.level_no = seq.rn
WHERE ml.level_no = 0;
