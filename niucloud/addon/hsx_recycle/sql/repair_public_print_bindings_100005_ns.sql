-- 仅适用于本次 saas_ 数据库、ns_ 前缀、站点 100005。
-- 手工执行前备份 ns_recycle_template_binding，并暂停模板配置操作。
-- 不修改型号、质检、打印模板内容、设备或订单；不触发打印。
-- 完整执行，同一连接中运行；任何语句报错应停止并执行 ROLLBACK，不要继续 COMMIT。
-- 这是手工修复脚本，不属于自动版本升级。

USE `saas_`;

-- 先看候选与冲突。原模板必须仍归 100005；模板也改成 0 的情况不猜归属。
SELECT b.id AS public_binding_id, b.target_type, b.target_id, b.scene_key,
       p.template_name,
       CASE
         WHEN l.id IS NULL THEN '可恢复到本站'
         WHEN l.print_template_id = b.print_template_id
          AND l.inherit_enabled = b.inherit_enabled AND l.status = b.status
           THEN '本站已存在相同规则，仅清理公共打印引用'
         ELSE '本站已有不同规则，跳过，不覆盖'
       END AS action
FROM ns_recycle_template_binding b
JOIN ns_recycle_printer_template p
  ON p.template_id = b.print_template_id AND p.site_id = 100005 AND p.delete_time = 0
LEFT JOIN ns_recycle_device_model_dict m ON m.id = b.target_id AND m.site_id = 0
LEFT JOIN ns_recycle_template_binding l
  ON l.site_id = 100005 AND l.target_type = b.target_type
 AND l.target_id = b.target_id AND l.scene_key = b.scene_key
WHERE b.site_id = 0
  AND ((b.target_type = 'global' AND b.target_id = 0) OR (b.target_type = 'model_dict' AND m.id IS NOT NULL));

START TRANSACTION;

-- 仅插入不存在的本站绑定；唯一键冲突时保持已有整行不变。
INSERT INTO ns_recycle_template_binding
  (site_id, target_type, target_id, scene_key, check_template_id, print_template_id,
   inherit_enabled, status, sort, remark, create_at, update_at)
SELECT 100005, b.target_type, b.target_id, b.scene_key, 0, b.print_template_id,
       b.inherit_enabled, b.status, b.sort, b.remark, b.create_at, UNIX_TIMESTAMP()
FROM ns_recycle_template_binding b
JOIN ns_recycle_printer_template p
  ON p.template_id = b.print_template_id AND p.site_id = 100005 AND p.delete_time = 0
LEFT JOIN ns_recycle_device_model_dict m ON m.id = b.target_id AND m.site_id = 0
LEFT JOIN ns_recycle_template_binding l
  ON l.site_id = 100005 AND l.target_type = b.target_type
 AND l.target_id = b.target_id AND l.scene_key = b.scene_key
WHERE b.site_id = 0 AND l.id IS NULL
  AND ((b.target_type = 'global' AND b.target_id = 0) OR (b.target_type = 'model_dict' AND m.id IS NOT NULL))
ON DUPLICATE KEY UPDATE id = ns_recycle_template_binding.id;

-- 只有本站已经安全持有相同打印规则时，才清理公共记录的打印引用。
-- 公共记录的 check_template_id、继承设置、状态等保持原样。
UPDATE ns_recycle_template_binding b
JOIN ns_recycle_printer_template p
  ON p.template_id = b.print_template_id AND p.site_id = 100005 AND p.delete_time = 0
JOIN ns_recycle_template_binding l
  ON l.site_id = 100005 AND l.target_type = b.target_type
 AND l.target_id = b.target_id AND l.scene_key = b.scene_key
 AND l.print_template_id = b.print_template_id
 AND l.inherit_enabled = b.inherit_enabled AND l.status = b.status
LEFT JOIN ns_recycle_device_model_dict m ON m.id = b.target_id AND m.site_id = 0
SET b.print_template_id = 0, b.update_at = UNIX_TIMESTAMP()
WHERE b.site_id = 0
  AND ((b.target_type = 'global' AND b.target_id = 0) OR (b.target_type = 'model_dict' AND m.id IS NOT NULL));

COMMIT;

-- 结果核对：有冲突的记录仍会留在公共库，不会被误清理。
SELECT p.template_name, COUNT(*) AS site_binding_count
FROM ns_recycle_template_binding b
JOIN ns_recycle_printer_template p ON p.template_id = b.print_template_id AND p.site_id = 100005
WHERE b.site_id = 100005
GROUP BY p.template_name;

SELECT COUNT(*) AS remaining_public_print_references
FROM ns_recycle_template_binding b
JOIN ns_recycle_printer_template p ON p.template_id = b.print_template_id AND p.site_id = 100005
WHERE b.site_id = 0;
