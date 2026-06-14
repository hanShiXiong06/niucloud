-- phpMyAdmin/Navicat 用 *_pma.csv 导入后执行：把 site_id 从 0 改成你的站点ID。
-- 把 {prefix} 换成表前缀(如 ns_)，{SITE_ID} 换成你的站点ID。
UPDATE `{prefix}recycle_check_dict` SET `site_id` = {SITE_ID} WHERE `site_id` = 0;
UPDATE `{prefix}recycle_check_data` SET `site_id` = {SITE_ID} WHERE `site_id` = 0;

-- 校验
SELECT (SELECT COUNT(*) FROM `{prefix}recycle_check_dict` WHERE site_id={SITE_ID}) AS dict_rows,
       (SELECT COUNT(*) FROM `{prefix}recycle_check_data` WHERE site_id={SITE_ID}) AS data_rows;
