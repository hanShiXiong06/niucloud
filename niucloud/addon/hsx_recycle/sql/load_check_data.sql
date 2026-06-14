-- 把本地去重好的两张表灌进库（服务器零计算，秒级）。
-- 前置：已执行 update_check_dict.sql 建好两张表。
-- 用法：
--   1) 把 check_dict.tsv、check_data.tsv 传到数据库服务器某目录（如 /tmp/）。
--   2) 把 {prefix} 换成表前缀(如 ns_)，{SITE_ID} 换成你的站点ID。
--   3) 若报 secure_file_priv，把文件放到 SHOW VARIABLES LIKE 'secure_file_priv' 指定的目录；
--      或用 mysql 客户端加 --local-infile=1 并把 LOAD DATA 改成 LOAD DATA LOCAL INFILE。
SET @now = UNIX_TIMESTAMP();

-- 参考表（ID+中文）
LOAD DATA INFILE '/tmp/check_dict.tsv'
INTO TABLE `{prefix}recycle_check_dict`
FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
(`id`,`dict_type`,`text`,`severity`,`sort`)
SET `site_id`={SITE_ID}, `is_user_modified`=0, `create_at`=@now, `update_at`=@now;

-- 数据表（全ID映射）
LOAD DATA INFILE '/tmp/check_data.tsv'
INTO TABLE `{prefix}recycle_check_data`
FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'
(`id`,`model_key`,`product_id`,`group_id`,`field_id`,`default_option_id`,`option_ids`,`import_hash`,`sort`)
SET `site_id`={SITE_ID}, `is_user_modified`=0, `create_at`=@now, `update_at`=@now;

-- 校验
SELECT (SELECT COUNT(*) FROM `{prefix}recycle_check_dict`  WHERE site_id={SITE_ID}) AS dict_rows,
       (SELECT COUNT(*) FROM `{prefix}recycle_check_data`  WHERE site_id={SITE_ID}) AS data_rows;
