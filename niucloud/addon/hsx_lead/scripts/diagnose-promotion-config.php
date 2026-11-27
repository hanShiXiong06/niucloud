<?php
declare(strict_types=1);

/** Read-only source-key diagnostic. Never saves, clears or migrates any rule. */
if (PHP_SAPI!=='cli') exit("CLI only\n");
$site=0;$localOnly=false;
foreach(array_slice($argv,1) as $argument) {
    if (preg_match('/^--site=([1-9][0-9]{0,8})$/D',$argument,$match)&&$site===0) $site=(int)$match[1];
    elseif ($argument==='--local-only'&&!$localOnly) $localOnly=true;
    else exit("Usage: php diagnose-promotion-config.php --site=100005 [--local-only]\n");
}
if ($site<=0) exit("Usage: php diagnose-promotion-config.php --site=100005 [--local-only]\n");
error_reporting(E_ALL & ~E_DEPRECATED);
$pdo=null;$stage='bootstrap';
try {
    $root=dirname(__DIR__,3);require $root.'/vendor/autoload.php';
    $app=new think\App($root);$app->loadEnv();require $app->getThinkPath().'helper.php';
    $stage='database_configuration';$database=require $root.'/config/database.php';$connection=$database['connections'][$database['default']]??[];
    if (($connection['type']??'')!=='mysql'||!empty($connection['deploy'])) throw new RuntimeException('Unsupported diagnostic database');
    if ($localOnly&&!in_array($connection['hostname']??'',['127.0.0.1','localhost'],true)) throw new RuntimeException('Local-only diagnostic refused remote database');
    $database['connections'][$database['default']]['trigger_sql']=false;
    $database['connections'][$database['default']]['fields_cache']=false;
    $app->config->set($database,'database');
    $manager=new think\DbManager();$manager->setConfig($database);$app->instance('think\DbManager',$manager);
    $stage='database_connection';think\facade\Db::query('SELECT 1');$pdo=think\facade\Db::connect()->getPdo();
    if (!$pdo instanceof PDO) throw new RuntimeException('Read-only database connection unavailable');
    // The database itself rejects writes even if a future implementation changes.
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
    $stage='source_key_read';$rows=think\facade\Db::name('sys_config')->where('site_id',$site)->where('config_key','HSX_LEAD_PROMOTION')->field('site_id,value')->select()->toArray();
    $summary=[];
    foreach($rows as $row) {
        $value=is_array($row['value'])?$row['value']:json_decode((string)$row['value'],true);
        $summary[]=['site_id'=>(int)$row['site_id'],'json_object_valid'=>is_array($value),
            'source_keys'=>is_array($value['sources']??null)?array_keys($value['sources']):[]];
    }
    $stage='service_configuration';$selected=['status'=>'valid','source_keys'=>[]];
    try {
        $config=addon\hsx_lead\app\service\cooperation\PromotionService::configuration($site);
        $selected['source_keys']=array_keys($config['sources']);
        $selected['requires_review']=($config['requires_review']??false)===true;
        $selected['ignored_source_keys']=$config['ignored_source_keys']??[];
    } catch(core\exception\CommonException $error) {
        $selected=['status'=>'rejected','message'=>$error->getMessage()];
    }
    $pdo->rollBack();
    echo json_encode(['read_only'=>true,'database_writes'=>0,'external_erp_calls'=>0,'selected_site_id'=>$site,
        'stored_promotion_rows'=>$summary,'selected_configuration'=>$selected],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $error) {
    if ($pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    // Do not expose DSN, credentials, SQL or full configuration values.
    fwrite(STDERR,"只读诊断未完成（".get_class($error)."，阶段：".$stage."）；请核对站点参数、数据库连接和后端文件。没有执行配置保存或迁移。\n");
    exit(1);
}
