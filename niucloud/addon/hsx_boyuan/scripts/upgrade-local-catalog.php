<?php
declare(strict_types=1);
// Development CLI only. Default: inventory; --apply invokes just the official plug-in schema/menu upgrade.
if(PHP_SAPI!=='cli')exit(2);
use addon\hsx_boyuan\app\support\CatalogStore;
use think\facade\Db;
$root=dirname(__DIR__,3);
require $root.'/vendor/autoload.php';
$app=new think\App($root);$app->loadEnv();require_once $app->getThinkPath().'helper.php';
$conf=require $root.'/config/database.php';$d=$conf['connections'][$conf['default']];
if(!in_array($d['hostname']??'',['localhost','127.0.0.1'],true) || !empty($d['deploy']) || ($d['type']??'')!=='mysql' || !preg_match('/^[A-Za-z0-9_]+$/D',$d['database']) || !preg_match('/^[A-Za-z0-9_]*$/D',$d['prefix']))exit("仅允许本机开发数据库。\n");
$app->initialize();$db=Db::connect();$db->query('SELECT 1',[],true);$pdo=$db->getPdo();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$d['database'] || !in_array($db->getConfig('hostname'),['localhost','127.0.0.1'],true) || $db->getConfig('prefix')!==$d['prefix'])throw new LogicException('未核对本地连接，停止。');
$prefix=$d['prefix'];$old=$prefix.'hsx_boyuan_reference';$frozen=$old.'_v123';$names=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
$src=in_array($old,$names,true)?$old:(in_array($frozen,$names,true)?$frozen:null);
if($src===null && !in_array($prefix.CatalogStore::table('parties'),$names,true))throw new LogicException('此脚本仅用于已安装博远的开发库');
if($src!==null)echo '旧档案（各站合计）：',json_encode($pdo->query('SELECT resource,COUNT(*) FROM '.$src.' GROUP BY resource')->fetchAll(PDO::FETCH_KEY_PAIR),JSON_UNESCAPED_UNICODE),PHP_EOL;
if(!in_array('--apply',$argv,true))exit("只读盘点，未更改。\n");
(new \addon\hsx_boyuan\app\upgrade\v130\Upgrade())->handle();
$counts=[];foreach(CatalogStore::kinds() as $k)$counts[$k]=CatalogStore::query($k)->count();
if(!Db::name('sys_menu')->where(['app_type'=>'site','addon'=>'hsx_boyuan','menu_key'=>'hsx_boyuan_catalog_page','is_show'=>1])->count())throw new LogicException('目录已升级，但未发现新菜单');
echo '开发库各分表：',json_encode($counts,JSON_UNESCAPED_UNICODE),PHP_EOL,'独立菜单已登记。未调用博远，未同步单据。',PHP_EOL;
