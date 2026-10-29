<?php
declare(strict_types=1);

/** Creates only a random, localhost-only database; never copies customer rows. */
function boyuanBoot(?string $existing=null): array
{
    $root=dirname(__DIR__,3); require_once $root.'/vendor/autoload.php';
    $app=new think\App($root); $app->loadEnv(); require_once $app->getThinkPath().'helper.php'; require_once $root.'/app/common.php';
    $database=require $root.'/config/database.php'; $original=$database['connections'][$database['default']];
    if (($original['type']??'')!=='mysql' || !in_array($original['hostname'],['localhost','127.0.0.1'],true) || !empty($original['deploy'])) throw new RuntimeException('Only localhost MySQL is permitted');
    $schema=$existing??('hsx_boyuan_test_'.bin2hex(random_bytes(8)));
    if (!preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$schema)) throw new RuntimeException('Unsafe isolated schema name');
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$original['hostport'].';charset=utf8mb4',$original['username'],$original['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($existing===null) {
        $pdo->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4');
        $q=$pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'"); $q->execute([$original['database']]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $short=str_starts_with($table,$original['prefix'])?substr($table,strlen($original['prefix'])):'';
            if (!str_starts_with($short,'erp_') && !in_array($short,['site','site_group','sys_config','sys_user','sys_user_role','sys_schedule','member','addon'],true)) continue;
            foreach ([$table,$original['database']] as $identifier) if (!preg_match('/^[a-zA-Z0-9_]+$/D',$identifier)) throw new RuntimeException('Unsafe source schema');
            $pdo->exec('CREATE TABLE `'.$schema.'`.`'.$table.'` LIKE `'.$original['database'].'`.`'.$table.'`');
        }
        $pdo->exec('USE `'.$schema.'`');
        $sql=str_replace('{{prefix}}',$original['prefix'],file_get_contents(dirname(__DIR__).'/sql/install.sql')); $pdo->exec($sql); $pdo->exec($sql);
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$original['database'].$original['prefix'])) throw new RuntimeException('Unsafe schema identifier');
    $pdo->exec('CREATE TABLE IF NOT EXISTS `'.$schema.'`.`'.$original['prefix'].'addon` LIKE `'.$original['database'].'`.`'.$original['prefix'].'addon`');
    $database['default']='isolated'; $database['connections']=['isolated'=>array_replace($original,['hostname'=>'127.0.0.1','database'=>$schema,'trigger_sql'=>false,'fields_cache'=>false,'break_reconnect'=>false])];
    $app->config->set($database,'database');
    $app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/'.$schema.'/cache/']]],'cache');
    $app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/'.$schema.'/log/']]],'log');
    $app->instance('request',new app\Request()); $manager=new think\DbManager(); $manager->setConfig($database); $app->instance('think\DbManager',$manager);
    if (think\facade\Db::query('SELECT DATABASE() AS db')[0]['db']!==$schema) throw new RuntimeException('Database isolation failed');
    $site=900000028; request()->siteId($site); request()->uid(1); request()->username('Isolated tester'); request()->appType('adminapi');
    $app->event->listen('ErpDomainEvent',addon\hsx_boyuan\app\listener\DomainEvent::class);
    if ($existing===null) {
        think\facade\Db::name('site')->insert(['site_id'=>$site,'site_name'=>'博远隔离验收','app_type'=>'site','addons'=>json_encode(['hsx_erp','hsx_boyuan']),'group_id'=>0]);
        think\facade\Db::name('sys_user')->insert(['uid'=>1,'username'=>'boyuan-fixture','real_name'=>'测试经办人','status'=>1]);
        think\facade\Db::name('sys_user_role')->insert(['uid'=>1,'site_id'=>$site,'status'=>1,'is_admin'=>1]);
    }
    return [$app,$pdo,$schema,$site];
}
