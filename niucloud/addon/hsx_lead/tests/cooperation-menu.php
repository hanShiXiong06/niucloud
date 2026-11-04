<?php
declare(strict_types=1);

// Pure menu/source contract check: no application boot, database or network.
$plugin=dirname(__DIR__);$root=dirname($plugin,3);$menu=require $plugin.'/app/dict/menu/site.php';
$checks=0;
function menuCheck(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try {
    menuCheck(count($menu)===1&&$menu[0]['menu_key']==='hsx_lead','single plugin top-level directory');
    menuCheck($menu[0]['parent_key']===''&&(int)$menu[0]['menu_type']===0,'plugin directory remains top level');
    $visible=array_values(array_filter($menu[0]['children'],static fn(array $r):bool=>(int)$r['is_show']===1));
    menuCheck(count($visible)===9,'nine current supervision/resource/promotion business pages');
    $expected=[
        'hsx_lead/workbench'=>'workbench/index',
        'hsx_lead/cooperation/leads'=>'cooperation/leads-page',
        'hsx_lead/resource/merchants'=>'resource/merchants',
        'hsx_lead/resource/inventory'=>'resource/inventory',
        'hsx_lead/cooperation/partners'=>'cooperation/partners-page',
        'hsx_lead/cooperation/reviews'=>'cooperation/reviews-page',
        'hsx_lead/cooperation/orders'=>'cooperation/orders-page',
        'hsx_lead/cooperation/settings'=>'cooperation/settings-page',
        'hsx_lead/promotion/index'=>'promotion/index',
    ];
    menuCheck(count(array_filter($menu[0]['children'],static fn(array $r):bool=>$r['menu_key']==='hsx_lead_manage_leads'))===0,'retired platform workflow and route are removed, not hidden');
    $configuration=array_values(array_filter($visible,static fn(array $r):bool=>$r['menu_key']==='hsx_lead_cooperation_config'))[0];
    $configKeys=array_column($configuration['children'],'menu_key');
    foreach(['hsx_lead_manage_catalog','hsx_lead_manage_catalog_save','hsx_lead_manage_notice_config','hsx_lead_manage_notice_config_save'] as $key) menuCheck(in_array($key,$configKeys,true),'business configuration owns catalog and framework notice grants: '.$key);
    $settings=file_get_contents($plugin.'/admin/views/cooperation/Settings.vue');
    menuCheck(str_contains($settings,'v-if="canEditCatalog"')&&str_contains($settings,'<CatalogDrawer v-if="canEditCatalog"')&&str_contains($settings,'需求型号清单'),'demand model management survives retirement behind the settings action');
    menuCheck(str_contains($settings,"hasLeadPagePermission(user,'hsx_lead_manage_catalog')&&hasLeadPagePermission(user,'hsx_lead_manage_catalog_save')"),'catalog editor requires both existing read and write grants, not configuration visibility alone');
    $css=file_get_contents($root.'/admin/src/styles/icon/official-iconfont.css');
    foreach(array_merge([$menu[0]],$visible) as $node) {
        $icon=explode(' ',$node['icon']);$class=end($icon);
        menuCheck(str_contains($css,'.'.$class.':before'),'menu icon exists in the installed icon library: '.$node['menu_name']);
    }
    foreach($visible as $page) {
        menuCheck((int)$page['menu_type']===1&&isset($expected[$page['router_path']])&&$expected[$page['router_path']]===$page['view_path'],'actual page path matches PHP menu: '.$page['menu_name']);
        menuCheck(is_file($plugin.'/admin/views/'.$page['view_path'].'.vue'),'plugin page exists: '.$page['view_path']);
        if ($page['menu_key']==='hsx_lead_promotion_manage') {
            foreach(['hsx_lead_promotion_rewards','hsx_lead_promotion_config','hsx_lead_promotion_config_save','hsx_lead_promotion_providers','hsx_lead_promotion_providers_save','hsx_lead_promotion_settle','hsx_lead_promotion_hold','hsx_lead_promotion_resume'] as $key) menuCheck(in_array($key,array_column($page['children'],'menu_key'),true),'promotion page keeps explicit read/write/settlement grants: '.$key);
        } else menuCheck(count(array_filter($page['children'],static fn(array $r):bool=>$r['api_url']==='hsx_lead/cooperation/scope'&&$r['methods']==='get'))===1,'page owns its authorized scope-read button');
        foreach($page['children'] as $button) menuCheck((int)$button['menu_type']===2&&(int)$button['is_show']===0&&$button['router_path']==='','third-level entries are hidden actions, not another navigation layer');
    }
    $keys=[];$walk=function(array $nodes)use(&$walk,&$keys):void {foreach($nodes as $node){menuCheck(!isset($keys[$node['menu_key']]),'permission keys remain unique: '.$node['menu_key']);$keys[$node['menu_key']]=true;if(isset($node['children']))$walk($node['children']);}};$walk($menu);
    foreach(['hsx_lead_manage_all_data','hsx_lead_cooperation_owner','hsx_lead_cooperation_owners'] as $key) menuCheck(isset($keys[$key]),'current business/API grant is retained: '.$key);
    foreach(['hsx_lead_manage_leads','hsx_lead_manage_lead_owner','hsx_lead_manage_workorders','hsx_lead_manage_commissions','hsx_lead_manage_commission_receive','hsx_lead_manage_commission_refund'] as $key) menuCheck(!isset($keys[$key]),'retired business/API grant is removed: '.$key);
    foreach($visible as $page) menuCheck(!preg_match('/提现|佣金账户|商户端入口|原分享|平台派单概况|平台客资/u',$page['menu_name']),'no redundant framework finance, retired dispatch or portal page');
    foreach(['Finance.vue','FrameworkFinance.vue','CooperationPanel.vue'] as $file) menuCheck(!is_file($plugin.'/admin/views/cooperation/'.$file),'redundant nested navigation removed: '.$file);
    echo "PASS cooperation menu: {$checks} checks; pure PHP/source contract, no database or network\n";
} catch(Throwable $e) {fwrite(STDERR,'FAIL cooperation menu: '.$e->getMessage()."\n");exit(1);}
