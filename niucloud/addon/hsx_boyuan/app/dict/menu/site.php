<?php
$permissions=[
 ['config','查看连接','get','config'],['save_config','管理连接','post','config'],['probe','核验账套','post','probe'],
 ['tasks','查看任务','get','tasks'],['detail','查看明细','get','tasks/<id>'],['action','处理异常','post','tasks/<id>/action'],
 ['mappings','查看资料对应','get','mappings'],['save_mapping','修改资料对应','post','mappings'],['references','读取博远档案','get','references'],
 ['staff','查看责任人','get','staff'],['logs','查看审计记录','get','logs'],
];
$children=[];
foreach ($permissions as [$key,$name,$method,$path]) $children[]=['menu_name'=>$name,'menu_key'=>'hsx_boyuan_'.$key,'menu_short_name'=>$name,
 'parent_select_key'=>'','menu_type'=>'2','icon'=>'','api_url'=>'boyuan/'.$path,'router_path'=>'','view_path'=>'','methods'=>$method,'sort'=>'10','status'=>'1','is_show'=>'0'];
return [['menu_name'=>'博远对接','menu_key'=>'hsx_boyuan','menu_short_name'=>'博远','parent_select_key'=>'','parent_key'=>'','menu_type'=>'1','icon'=>'iconfont iconcaigou',
 'api_url'=>'','router_path'=>'hsx_boyuan/workbench','view_path'=>'','methods'=>'','sort'=>'180','status'=>'1','is_show'=>'0','children'=>$children]];
