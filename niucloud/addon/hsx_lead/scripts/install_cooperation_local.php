<?php
declare(strict_types=1);
// Developer convenience only. New installations use sql/install.sql normally.
if(PHP_SAPI!=='cli'||!in_array('--local-development',$argv,true))exit("Usage: php addon/hsx_lead/scripts/install_cooperation_local.php --local-development\nLocal MySQL only; adds 6 plugin tables and refreshes this plugin's menu. No rows are deleted.\n");
$root=dirname(__DIR__,3);require $root.'/vendor/autoload.php';$app=new think\App($root);$app->loadEnv();require $app->getThinkPath().'helper.php';require $root.'/app/common.php';
$db=require $root.'/config/database.php';$original=$db['connections'][$db['default']]??[];
if(($original['type']??'')!=='mysql'||!in_array($original['hostname']??'',['localhost','127.0.0.1'],true)||!empty($original['deploy']))throw new RuntimeException('Refusing a non-local or distributed database; use the reviewed installation SQL explicitly.');
$prefix=(string)($original['prefix']??'');if(!preg_match('/^[A-Za-z0-9_]*$/D',$prefix))throw new RuntimeException('Invalid table prefix');
$app->config->set($db,'database');$app->config->set(require $root.'/config/cache.php','cache');$app->config->set(require $root.'/config/log.php','log');
$manager=new think\DbManager();$manager->setConfig($db);$app->instance('think\DbManager',$manager);$app->instance('request',new app\Request());
$sql=file_get_contents(dirname(__DIR__).'/sql/cooperation.sql');$count=0;
foreach(parse_sql($sql) as $statement){$statement=trim($statement);if($statement==='')continue;
    if(!preg_match('/^CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}hsx_lead_(partner|collaboration|coop_order|wallet_entry|withdrawal|review)`/D',$statement))throw new RuntimeException('Unexpected statement: this helper only permits the six additive CREATE statements');
    think\facade\Db::execute(str_replace('{{prefix}}',$prefix,$statement));$count++;
}
(new app\service\core\menu\CoreMenuService())->refreshAddonMenu('hsx_lead');
addon\hsx_lead\app\service\NoticeConfigService::syncAssignedSites();
echo "[OK] Checked/created {$count} cooperation tables; refreshed hsx_lead menus and notice registrations.\n";
echo "[UNCHANGED] Existing ERP snapshots, member accounts, business records, balances and site pricing were not reset.\n";
echo "[NEXT] Open site/hsx_lead/workbench -> 商户协作 -> 业务配置. Set prices, grade rules and confirm the fee policy before enabling.\n";
