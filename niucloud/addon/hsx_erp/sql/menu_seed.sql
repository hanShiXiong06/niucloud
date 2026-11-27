-- ERP 新增菜单兜底 SQL（仅当“系统→刷新菜单”不可用时手动执行）
-- 用法：把 {prefix} 换成你的表前缀（如 ns_）；执行后超级管理员即可看到「库位责任」「资金账户」菜单。
-- 正常情况下，请优先用 后台「系统 → 菜单 → 刷新菜单」(POST sys/menu/refresh)，它会清缓存并自动重建所有插件菜单。

-- 先清掉这两组菜单，避免重复
DELETE FROM `{prefix}sys_menu` WHERE `addon`='hsx_erp' AND `app_type`='site' AND `menu_key` IN (
  'hsx_erp_location_assign_list','hsx_erp_location_assign_tree','hsx_erp_location_assign_staff_options',
  'hsx_erp_location_assign_set_location','hsx_erp_location_assign_set_staff',
  'hsx_erp_capital_account_list','hsx_erp_capital_account_save','hsx_erp_capital_account_delete',
  'hsx_erp_capital_account_entry','hsx_erp_capital_account_ledger'
);

-- 库位责任（挂在「基础配置 hsx_erp_config_group」下）
INSERT INTO `{prefix}sys_menu`
(`app_type`,`menu_name`,`menu_short_name`,`menu_key`,`parent_key`,`menu_type`,`icon`,`api_url`,`router_path`,`view_path`,`methods`,`sort`,`status`,`is_show`,`addon`,`source`) VALUES
('site','库位责任','库位责任','hsx_erp_location_assign_list','hsx_erp_config_group',1,'nc-iconfont nc-icon-yonghu','erp/location_assign/lists','hsx_erp/location_assign','location_assign/list','get',115,1,1,'hsx_erp','system'),
('site','仓库库位树','仓库库位树','hsx_erp_location_assign_tree','hsx_erp_location_assign_list',2,'','erp/location_assign/tree','','','get',100,1,0,'hsx_erp','system'),
('site','可分配员工','可分配员工','hsx_erp_location_assign_staff_options','hsx_erp_location_assign_list',2,'','erp/location_assign/staff_options','','','get',90,1,0,'hsx_erp','system'),
('site','设置库位负责人','设置库位负责人','hsx_erp_location_assign_set_location','hsx_erp_location_assign_list',2,'','erp/location_assign/location/<location_id>/staff','','','post',80,1,0,'hsx_erp','system'),
('site','设置员工负责库位','设置员工负责库位','hsx_erp_location_assign_set_staff','hsx_erp_location_assign_list',2,'','erp/location_assign/staff/<uid>/locations','','','post',70,1,0,'hsx_erp','system');

-- 资金账户（顶级菜单，挂在 hsx_erp_manage 下）
INSERT INTO `{prefix}sys_menu`
(`app_type`,`menu_name`,`menu_short_name`,`menu_key`,`parent_key`,`menu_type`,`icon`,`api_url`,`router_path`,`view_path`,`methods`,`sort`,`status`,`is_show`,`addon`,`source`) VALUES
('site','资金账户','资金账户','hsx_erp_capital_account_list','hsx_erp_manage',1,'nc-iconfont nc-icon-yinhangqia','erp/capital_account/lists','hsx_erp/capital_account','capital_account/list','get',55,1,1,'hsx_erp','system'),
('site','保存账户','保存账户','hsx_erp_capital_account_save','hsx_erp_capital_account_list',2,'','erp/capital_account/save/<id>','','','post',90,1,0,'hsx_erp','system'),
('site','删除账户','删除账户','hsx_erp_capital_account_delete','hsx_erp_capital_account_list',2,'','erp/capital_account/<id>','','','delete',85,1,0,'hsx_erp','system'),
('site','记一笔收付','记一笔收付','hsx_erp_capital_account_entry','hsx_erp_capital_account_list',2,'','erp/capital_account/entry','','','post',80,1,0,'hsx_erp','system'),
('site','账目往来流水','账目往来流水','hsx_erp_capital_account_ledger','hsx_erp_capital_account_list',2,'','erp/capital_account/ledger','','','get',75,1,0,'hsx_erp','system');
