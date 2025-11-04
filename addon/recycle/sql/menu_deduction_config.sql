-- 扣费配置菜单SQL
-- 使用说明：
-- 1. 先查询报价管理的父菜单ID：SELECT id, menu_key, menu_name FROM sys_menu WHERE menu_name LIKE '%报价%';
-- 2. 将下面SQL中的 @parent_key 替换为实际的父菜单key
-- 3. 执行SQL创建菜单
-- 4. 刷新后台页面即可看到新菜单

-- 方式1：如果父菜单key是已知的，直接替换并执行
SET @parent_key = 'recycle_quotation';  -- 修改为实际的父菜单key

-- 添加扣费配置主菜单
INSERT INTO `sys_menu` (
    `menu_name`, 
    `menu_key`, 
    `menu_short_name`, 
    `parent_key`, 
    `menu_type`, 
    `icon`, 
    `api_url`, 
    `router_path`, 
    `view_path`, 
    `methods`, 
    `sort`, 
    `is_show`, 
    `status`, 
    `app_type`
) VALUES (
    '扣费配置',
    'recycle_deduction_config',
    '扣费配置',
    @parent_key,
    '1',
    'icon-settings',
    '',
    'recycle/deduction_config',
    'addon/recycle/views/quotation/deduction_config',
    'GET',
    100,
    1,
    1,
    'admin'
);

-- 查看权限
INSERT INTO `sys_menu` (
    `menu_name`, 
    `menu_key`, 
    `menu_short_name`, 
    `parent_key`, 
    `menu_type`, 
    `icon`, 
    `api_url`, 
    `router_path`, 
    `view_path`, 
    `methods`, 
    `sort`, 
    `is_show`, 
    `status`, 
    `app_type`
) VALUES (
    '扣费配置列表',
    'recycle_deduction_config_lists',
    '查看',
    'recycle_deduction_config',
    '2',
    '',
    'recycle/deduction_config/pages',
    '',
    '',
    'GET',
    0,
    1,
    1,
    'admin'
);

-- 添加权限
INSERT INTO `sys_menu` (
    `menu_name`, 
    `menu_key`, 
    `menu_short_name`, 
    `parent_key`, 
    `menu_type`, 
    `icon`, 
    `api_url`, 
    `router_path`, 
    `view_path`, 
    `methods`, 
    `sort`, 
    `is_show`, 
    `status`, 
    `app_type`
) VALUES (
    '添加扣费配置',
    'recycle_deduction_config_add',
    '添加',
    'recycle_deduction_config',
    '2',
    '',
    'recycle/deduction_config',
    '',
    '',
    'POST',
    1,
    1,
    1,
    'admin'
);

-- 编辑权限
INSERT INTO `sys_menu` (
    `menu_name`, 
    `menu_key`, 
    `menu_short_name`, 
    `parent_key`, 
    `menu_type`, 
    `icon`, 
    `api_url`, 
    `router_path`, 
    `view_path`, 
    `methods`, 
    `sort`, 
    `is_show`, 
    `status`, 
    `app_type`
) VALUES (
    '编辑扣费配置',
    'recycle_deduction_config_edit',
    '编辑',
    'recycle_deduction_config',
    '2',
    '',
    'recycle/deduction_config/:id',
    '',
    '',
    'PUT',
    2,
    1,
    1,
    'admin'
);

-- 删除权限
INSERT INTO `sys_menu` (
    `menu_name`, 
    `menu_key`, 
    `menu_short_name`, 
    `parent_key`, 
    `menu_type`, 
    `icon`, 
    `api_url`, 
    `router_path`, 
    `view_path`, 
    `methods`, 
    `sort`, 
    `is_show`, 
    `status`, 
    `app_type`
) VALUES (
    '删除扣费配置',
    'recycle_deduction_config_delete',
    '删除',
    'recycle_deduction_config',
    '2',
    '',
    'recycle/deduction_config/:id',
    '',
    '',
    'DELETE',
    3,
    1,
    1,
    'admin'
);

-- 修改状态权限
INSERT INTO `sys_menu` (
    `menu_name`, 
    `menu_key`, 
    `menu_short_name`, 
    `parent_key`, 
    `menu_type`, 
    `icon`, 
    `api_url`, 
    `router_path`, 
    `view_path`, 
    `methods`, 
    `sort`, 
    `is_show`, 
    `status`, 
    `app_type`
) VALUES (
    '修改扣费配置状态',
    'recycle_deduction_config_status',
    '状态',
    'recycle_deduction_config',
    '2',
    '',
    'recycle/deduction_config/modify_status',
    '',
    '',
    'PUT',
    4,
    1,
    1,
    'admin'
);

-- 完成后查询验证
SELECT menu_name, menu_key, parent_key, router_path 
FROM sys_menu 
WHERE menu_key LIKE '%deduction%' 
ORDER BY sort;

