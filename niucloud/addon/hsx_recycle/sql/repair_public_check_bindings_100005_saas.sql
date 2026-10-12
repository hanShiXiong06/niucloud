-- 仅适用于本次已核对的正式库 niushops（表前缀 saas_）。
-- 执行前导出备份 saas_recycle_template_binding；在 phpMyAdmin 中选中 niushops。
-- 不新增表/字段，不更新或删除原绑定，不修改订单/设备/质检结果/打印配置。
-- 两条 INSERT 各自原子执行；已有公共绑定不覆盖，可重复执行。
-- 本次核对：100005 有 24483 条有效原质检绑定；公共绑定缺失。

-- 1. 按原型号 ID、模板 ID、场景补公共质检关联，公共打印 ID 固定为 0。
INSERT INTO saas_recycle_template_binding
    (site_id, target_type, target_id, scene_key, check_template_id,
     print_template_id, inherit_enabled, status, sort, remark, create_at, update_at)
SELECT 0, b.target_type, b.target_id, b.scene_key, b.check_template_id,
       0, b.inherit_enabled, b.status, b.sort,
       'repair_public_check_100005_20261003', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM saas_recycle_template_binding b
JOIN saas_recycle_check_template t
  ON t.id = b.check_template_id AND t.site_id = 0
LEFT JOIN saas_recycle_device_model_dict m
  ON m.id = b.target_id AND m.site_id = 0
LEFT JOIN saas_recycle_template_binding existing
  ON existing.site_id = 0
 AND existing.target_type = b.target_type
 AND existing.target_id = b.target_id
 AND existing.scene_key = b.scene_key
WHERE b.site_id = 100005
  AND b.check_template_id > 0
  AND existing.id IS NULL
  AND ((b.target_type = 'global' AND b.target_id = 0)
       OR (b.target_type = 'model_dict' AND m.id IS NOT NULL));

SELECT ROW_COUNT() AS copied_public_bindings;

-- 2. 单独补已核对的 iPhone Air：型号 8，来源产品 225366，模板 13196。
-- 已核对模板含 41 个字段；容量 256G/512G/1T，颜色深空黑/天蓝/浅金/云白。
-- 不对其他未绑定型号进行名称模糊匹配；任何现有公共绑定均不覆盖。
INSERT INTO saas_recycle_template_binding
    (site_id, target_type, target_id, scene_key, check_template_id,
     print_template_id, inherit_enabled, status, sort, remark, create_at, update_at)
SELECT 0, 'model_dict', m.id, 'manual_device_label', t.id,
       0, 1, 1, 0, 'repair_air_225366_20261003', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM saas_recycle_device_model_dict m
JOIN saas_recycle_check_template t
  ON t.id = 13196 AND t.site_id = 0 AND t.status = 1
 AND t.scene = 'pjt'
 AND t.template_key = 'pjt_36bd6c1424209396a5d56d2d5194bd40'
LEFT JOIN saas_recycle_template_binding existing
  ON existing.site_id = 0 AND existing.target_type = 'model_dict'
 AND existing.target_id = m.id AND existing.scene_key = 'manual_device_label'
WHERE m.id = 8 AND m.site_id = 0
  AND m.source = 'recycle_spider' AND m.product_source_id = '225366'
  AND existing.id IS NULL
  AND EXISTS (SELECT 1 FROM saas_recycle_check_field f
              WHERE f.site_id = 0 AND f.template_id = t.id);

SELECT ROW_COUNT() AS added_air_binding;

-- 3. 结果核对：首次完整执行、期间无其他改动时，公共关联应为 24484 条；
-- 原站仍为 24483 条，原记录保留；下面 missing/conflicting 两项应为 0。
SELECT site_id, COUNT(*) AS binding_count,
       SUM(check_template_id > 0) AS check_binding_count,
       SUM(print_template_id > 0) AS print_binding_count
FROM saas_recycle_template_binding
WHERE site_id IN (0, 100005)
GROUP BY site_id ORDER BY site_id;

SELECT COUNT(*) AS source_check_bindings,
       COALESCE(SUM(p.id IS NULL), 0) AS missing_public_bindings,
       COALESCE(SUM(p.id IS NOT NULL AND
           (p.check_template_id <> b.check_template_id
            OR p.inherit_enabled <> b.inherit_enabled OR p.status <> b.status)), 0)
           AS conflicting_public_bindings
FROM saas_recycle_template_binding b
LEFT JOIN saas_recycle_template_binding p
  ON p.site_id = 0 AND p.target_type = b.target_type
 AND p.target_id = b.target_id AND p.scene_key = b.scene_key
WHERE b.site_id = 100005 AND b.check_template_id > 0;

SELECT b.id AS binding_id, b.site_id, b.target_id AS model_id,
       m.model_full_name, b.check_template_id, t.template_name,
       b.print_template_id, b.status
FROM saas_recycle_template_binding b
JOIN saas_recycle_device_model_dict m ON m.id = b.target_id
JOIN saas_recycle_check_template t ON t.id = b.check_template_id
WHERE b.site_id = 0 AND b.target_type = 'model_dict'
  AND b.target_id = 8 AND b.scene_key = 'manual_device_label';
