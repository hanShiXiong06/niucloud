<?php
declare(strict_types=1);
// Strictly read-only. It neither boots addon listeners nor exposes gateway credentials or business records.
if (PHP_SAPI!=='cli') exit(2);
$root=dirname(__DIR__,3);
require $root.'/vendor/autoload.php';
$app=new think\App($root); $app->loadEnv(); require_once $app->getThinkPath().'helper.php';
$db=require $root.'/config/database.php'; $d=$db['connections'][$db['default']];
if ($d['type']!=='mysql' || !in_array($d['hostname'],['127.0.0.1','localhost'],true) || !empty($d['deploy']) ||
    !preg_match('/^[a-zA-Z0-9_]+$/D',$d['database']) || !preg_match('/^[a-zA-Z0-9_]*$/D',$d['prefix'])) throw new LogicException('只允许本机 MySQL');
$pdo=new PDO('mysql:host=127.0.0.1;port='.(int)$d['hostport'].';dbname='.$d['database'].';charset=utf8mb4',$d['username'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
$site=100003; $dbName=$d['database']; $prefix=$d['prefix']; $table=static fn($name)=>'`'.$prefix.$name.'`';
$has=static function(string $name) use($pdo,$dbName,$prefix):bool { $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?'); $q->execute([$dbName,$prefix.$name]); return (bool)$q->fetchColumn(); };
$query=static function(string $sql,array $args=[]) use($pdo) { $q=$pdo->prepare($sql);$q->execute($args);return $q; };
$one=static fn(string $sql,array $args=[])=>$query($sql,$args)->fetch(PDO::FETCH_ASSOC)?:[];
try {
    $raw=$one('SELECT site_id,site_name,addons,group_id FROM '.$table('site').' WHERE site_id=?',[$site]); if (!$raw) throw new RuntimeException('本站不存在');
    $out=['site_id'=>$site,'site_name'=>$raw['site_name'],'site_group'=>(int)$raw['group_id'],'declared_addons'=>json_decode((string)$raw['addons'],true),'database_host'=>'127.0.0.1'];
    $counts=[];
    foreach(['erp_warehouse','erp_warehouse_location','erp_site_catalog_product','erp_party','erp_capital_account','erp_asset','erp_purchase_order','erp_sale_order','erp_payable','erp_receivable','erp_settlement','erp_outbox_event','erp_printer','erp_print_scene','erp_print_job',...array_map([\addon\hsx_boyuan\app\support\CatalogStore::class,'table'],\addon\hsx_boyuan\app\support\CatalogStore::kinds()),'hsx_boyuan_mapping','hsx_boyuan_task'] as $name) {
        $counts[$name]=$has($name)?(int)$query('SELECT COUNT(*) FROM '.$table($name).' WHERE site_id=?',[$site])->fetchColumn():null;
    }
    $out['counts']=$counts;
    foreach(['erp_receivable','erp_payable'] as $n) if($has($n)) $out['outstanding'][$n]=(int)$query('SELECT COUNT(*) FROM '.$table($n)." WHERE site_id=? AND status<>'void' AND ABS(amount-settled_amount)>0",[$site])->fetchColumn();
    $out['active_staff_count']=(int)$query('SELECT COUNT(*) FROM '.$table('sys_user_role').' r JOIN '.$table('sys_user').' u ON u.uid=r.uid WHERE r.site_id=? AND r.status=1 AND r.delete_time=0 AND u.status=1 AND u.delete_time=0',[$site])->fetchColumn();
    $out['first_site_admin_uid']=$query('SELECT MIN(r.uid) FROM '.$table('sys_user_role').' r JOIN '.$table('sys_user').' u ON u.uid=r.uid WHERE r.site_id=? AND r.is_admin=1 AND r.status=1 AND r.delete_time=0 AND u.status=1 AND u.delete_time=0',[$site])->fetchColumn();
    $out['enabled_printers']=$has('erp_printer')?(int)$query('SELECT COUNT(*) FROM '.$table('erp_printer').' WHERE site_id=? AND status=1',[$site])->fetchColumn():null;
    $config=$query('SELECT value FROM '.$table('sys_config').' WHERE site_id=? AND config_key=?',[$site,'HSX_BOYUAN_CONNECTION'])->fetchAll(PDO::FETCH_COLUMN);
    if(count($config)>1) throw new RuntimeException('请先核对重复连接设置');
    $out['boyuan_connection_saved']=(bool)$config;
    if ($config) {
        $value=json_decode($config[0],true,512,JSON_THROW_ON_ERROR);
        $out['boyuan_connection_flags']=array_intersect_key($value,array_flip(['enabled','mode','paused','purchase_enabled','finance_enabled','purchase_mode']));
    }
    $out['tenant_config_keys']=$query('SELECT config_key FROM '.$table('sys_config').' WHERE site_id=? AND (config_key LIKE ? OR config_key LIKE ?) ORDER BY 1',[$site,'HSX_ERP%','HSX_BOYUAN%'])->fetchAll(PDO::FETCH_COLUMN);
    $out['other_ledger_connector_config_count']=(int)$query('SELECT COUNT(*) FROM '.$table('sys_config').' WHERE site_id=? AND config_key IN (?,?)',[$site,'HSX_KINGDEE_CONNECTION','HSX_KINGDEE_JDY_CONNECTION'])->fetchColumn();
    if (!defined('LOCAL_CYCLE_INTERNAL_PREFLIGHT')) echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally { $pdo->rollBack(); }
