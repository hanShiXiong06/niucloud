<?php
declare(strict_types=1);

// Fresh-base contracts only. Never boots the application or opens a database.
$plugin=dirname(__DIR__);$checks=0;
function freshCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);$GLOBALS['checks']++;}
$info=json_decode(file_get_contents($plugin.'/info.json'),true,512,JSON_THROW_ON_ERROR);
freshCheck($info['version']==='0.0.1','metadata uses the requested base version');
$sql=file_get_contents($plugin.'/sql/install.sql');
freshCheck($sql===file_get_contents($plugin.'/install.sql'),'both installation entries are identical');
preg_match_all('/CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}(hsx_lead_[a-z_]+)`/i',$sql,$tables);
$actual=$tables[1];sort($actual);
$expected=array_map(static fn(string $name):string=>'hsx_lead_'.$name,['merchant','merchant_member','connection','inventory','sync_run','audit','partner','collaboration','coop_order','review','promotion_reward']);sort($expected);
freshCheck($actual===$expected,'new install has eleven tables including subject membership and only one promotion reward audit table');
freshCheck(!str_contains($sql,'hsx_lead_wallet')&&!str_contains($sql,'hsx_lead_withdrawal'),'promotion adds no independent wallet or withdrawal table');
freshCheck(!preg_match('/\b(?:ALTER|DROP|TRUNCATE|DELETE|UPDATE)\b/i',$sql),'installer contains no upgrade or destructive statement');
$addon=file_get_contents($plugin.'/Addon.php');
freshCheck(!str_contains($addon,'Schema::')&&!str_contains($addon,'upgrade_'),'lifecycle does not load a historical migrator');
freshCheck(str_contains($addon,'registerSchedule(true)')&&str_contains($addon,'ScheduleDict::OFF'),'fresh install does not silently start inventory harvesting');
foreach(['Schema.php','WorkflowService.php','DealProgress.php','CollaborationResult.php'] as $name){
    freshCheck(!is_file($plugin.'/app/support/'.$name)&&!is_file($plugin.'/app/service/'.$name),'retired business implementation is absent: '.$name);
}
freshCheck(!is_file($plugin.'/sql/cooperation.sql')&&count(glob($plugin.'/sql/upgrade*.sql'))===0,'one canonical fresh schema without legacy patches');
$uninstall=file_get_contents($plugin.'/sql/uninstall.sql');
freshCheck(!preg_match('/^\s*(?:DROP|TRUNCATE|DELETE|UPDATE)\s/im',$uninstall),'uninstall does not destroy business history');
echo "PASS fresh install: {$checks} checks; source contracts only, no database or network\n";
