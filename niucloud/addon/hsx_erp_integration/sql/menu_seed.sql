-- 仅供需要手工维护站点菜单的环境。正常安装/升级请使用 PHP 插件菜单刷新；不会修改业务表。
-- 由 app/support/ConsoleMenu.php 的站点菜单生成；手工执行后仍需刷新菜单缓存和角色授权。
START TRANSACTION;
DELETE FROM `{prefix}sys_menu` WHERE addon='hsx_erp_integration' AND app_type='site' AND source='system';
INSERT INTO `{prefix}sys_menu` (`app_type`,`menu_name`,`menu_short_name`,`menu_key`,`parent_key`,`menu_type`,`icon`,`api_url`,`router_path`,`view_path`,`methods`,`sort`,`status`,`is_show`,`addon`,`source`) VALUES
('site','ERP 集成','ERP 集成','hsx_erp_integration_root','',0,'','','hsx_erp_integration','','',200,1,1,'hsx_erp_integration','system'),
('site','连接管理','连接管理','hsx_erp_integration_connection','hsx_erp_integration_root',1,'','integration/connection/lists','hsx_erp_integration/connection','integration/connection','get',100,1,1,'hsx_erp_integration','system'),
('site','查看连接','查看连接','hsx_erp_integration_connection_lists','hsx_erp_integration_connection',2,'','integration/connection/lists','','','get',0,1,0,'hsx_erp_integration','system'),
('site','查看连接配置','查看连接配置','hsx_erp_integration_connection_info','hsx_erp_integration_connection',2,'','integration/connection/<id>','','','get',0,1,0,'hsx_erp_integration','system'),
('site','新建连接','新建连接','hsx_erp_integration_connection_add','hsx_erp_integration_connection',2,'','integration/connection/save/0','','','post',0,1,0,'hsx_erp_integration','system'),
('site','编辑连接','编辑连接','hsx_erp_integration_connection_edit','hsx_erp_integration_connection',2,'','integration/connection/save/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','测试连接','测试连接','hsx_erp_integration_connection_test','hsx_erp_integration_connection',2,'','integration/connection/test/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','启用或暂停连接','启用或暂停连接','hsx_erp_integration_connection_status','hsx_erp_integration_connection',2,'','integration/connection/status/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','删除未使用连接','删除未使用连接','hsx_erp_integration_connection_del','hsx_erp_integration_connection',2,'','integration/connection/<id>','','','delete',0,1,0,'hsx_erp_integration','system'),
('site','查看配置表单','查看配置表单','hsx_erp_integration_connection_adapters','hsx_erp_integration_connection',2,'','integration/connection/adapters','','','get',0,1,0,'hsx_erp_integration','system'),
('site','资料映射','资料映射','hsx_erp_integration_mapping','hsx_erp_integration_root',1,'','integration/mapping/lists','hsx_erp_integration/mapping','integration/mapping','get',99,1,1,'hsx_erp_integration','system'),
('site','查看映射','查看映射','hsx_erp_integration_mapping_lists','hsx_erp_integration_mapping',2,'','integration/mapping/lists','','','get',0,1,0,'hsx_erp_integration','system'),
('site','选择映射连接','选择映射连接','hsx_erp_integration_mapping_connections','hsx_erp_integration_mapping',2,'','integration/mapping/connections','','','get',0,1,0,'hsx_erp_integration','system'),
('site','选择本站资料','选择本站资料','hsx_erp_integration_mapping_resources','hsx_erp_integration_mapping',2,'','integration/mapping/resources','','','get',0,1,0,'hsx_erp_integration','system'),
('site','新建映射','新建映射','hsx_erp_integration_mapping_add','hsx_erp_integration_mapping',2,'','integration/mapping/save/0','','','post',0,1,0,'hsx_erp_integration','system'),
('site','编辑映射','编辑映射','hsx_erp_integration_mapping_edit','hsx_erp_integration_mapping',2,'','integration/mapping/save/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','删除映射','删除映射','hsx_erp_integration_mapping_del','hsx_erp_integration_mapping',2,'','integration/mapping/<id>','','','delete',0,1,0,'hsx_erp_integration','system'),
('site','同步任务','同步任务','hsx_erp_integration_task','hsx_erp_integration_root',1,'','integration/task/lists','hsx_erp_integration/task','integration/task','get',98,1,1,'hsx_erp_integration','system'),
('site','查看任务','查看任务','hsx_erp_integration_task_lists','hsx_erp_integration_task',2,'','integration/task/lists','','','get',0,1,0,'hsx_erp_integration','system'),
('site','查看任务详情','查看任务详情','hsx_erp_integration_task_info','hsx_erp_integration_task',2,'','integration/task/<id>','','','get',0,1,0,'hsx_erp_integration','system'),
('site','查看今日统计','查看今日统计','hsx_erp_integration_task_statistics','hsx_erp_integration_task',2,'','integration/task/statistics','','','get',0,1,0,'hsx_erp_integration','system'),
('site','重试发送前校验失败的任务','重试发送前校验失败的任务','hsx_erp_integration_task_retry','hsx_erp_integration_task',2,'','integration/task/retry/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','关联已有原单','关联已有原单','hsx_erp_integration_task_prepare','hsx_erp_integration_task',2,'','integration/task/prepare/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','读取原单回执','读取原单回执','hsx_erp_integration_task_review','hsx_erp_integration_task',2,'','integration/task/review/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','异常处理','异常处理','hsx_erp_integration_failure','hsx_erp_integration_root',1,'','integration/failure/lists','hsx_erp_integration/failure','integration/failure','get',97,1,1,'hsx_erp_integration','system'),
('site','查看异常','查看异常','hsx_erp_integration_failure_lists','hsx_erp_integration_failure',2,'','integration/failure/lists','','','get',0,1,0,'hsx_erp_integration','system'),
('site','查看异常详情','查看异常详情','hsx_erp_integration_failure_info','hsx_erp_integration_failure',2,'','integration/failure/<id>','','','get',0,1,0,'hsx_erp_integration','system'),
('site','登记处理结果','登记处理结果','hsx_erp_integration_failure_resolve','hsx_erp_integration_failure',2,'','integration/failure/resolve/<id>','','','post',0,1,0,'hsx_erp_integration','system'),
('site','删除已处理异常记录','删除已处理异常记录','hsx_erp_integration_failure_batch_delete','hsx_erp_integration_failure',2,'','integration/failure/batch_delete','','','delete',0,1,0,'hsx_erp_integration','system'),
('site','可用适配器','可用适配器','hsx_erp_integration_adapter','hsx_erp_integration_root',1,'','integration/adapter/list','hsx_erp_integration/adapter','integration/adapter','get',96,1,1,'hsx_erp_integration','system'),
('site','查看适配器','查看适配器','hsx_erp_integration_adapter_lists','hsx_erp_integration_adapter',2,'','integration/adapter/list','','','get',0,1,0,'hsx_erp_integration','system');
COMMIT;
