-- hsx_recycle 0.0.10
-- 拍机堂质检模板导入改为紧凑结构存储：导入模板只写模板主表 schema_json，
-- 验机 schema 接口按需展开，避免批量写入大量一次性 group/field/option 明细数据。

ALTER TABLE `{{prefix}}recycle_check_template`
  ADD COLUMN `schema_hash` varchar(32) NOT NULL DEFAULT '' COMMENT '紧凑模板结构hash' AFTER `version`,
  ADD COLUMN `schema_json` longtext NULL COMMENT '导入模板紧凑结构JSON' AFTER `schema_hash`;
