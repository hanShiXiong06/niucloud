<?php
$permissions=[
 ['config','查看连接','get','config'],['save_config','管理连接','post','config'],['probe','核验账套','post','probe'],
 ['tasks','查看任务','get','tasks'],['detail','查看明细','get','tasks/<id>'],['action','处理异常','post','tasks/<id>/action'],
 ['mappings','查看资料对应','get','mappings'],['save_mapping','修改资料对应','post','mappings'],['references','读取博远档案','get','references'],
 ['staff','查看责任人','get','staff'],['logs','查看审计记录','get','logs'],
 ['reference_sync','查看资料同步','get','reference-sync'],['reference_start','同步基础资料','post','reference-sync'],['reference_retry','重试资料同步','post','reference-sync/<id>/retry'],
 ['mapping_sources','选择本地资料','get','mapping-sources'],
 ['reference_search','搜索基础资料','post','reference-search'],
 ['task_batch','批量处理同步任务','post','task-batch'],['mapping_candidates','按手机号匹配人员','post','mapping-candidates'],
 ['task_batch_status','查看批次结果','post','task-batch-status'],
];
$children=[];
foreach ($permissions as [$key,$name,$method,$path]) $children[]=['menu_name'=>$name,'menu_key'=>'hsx_boyuan_'.$key,'menu_short_name'=>$name,
 'parent_select_key'=>'','menu_type'=>'2','icon'=>'','api_url'=>'boyuan/'.$path,'router_path'=>'','view_path'=>'','methods'=>$method,'sort'=>'10','status'=>'1','is_show'=>'0'];
return [['menu_name'=>'博远对接','menu_key'=>'hsx_boyuan','menu_short_name'=>'博远','parent_select_key'=>'','parent_key'=>'','menu_type'=>'1','icon'=>'iconfont iconcaigou',
 'api_url'=>'','router_path'=>'hsx_boyuan/workbench','view_path'=>'','methods'=>'','sort'=>'180','status'=>'1','is_show'=>'0','children'=>$children]];
