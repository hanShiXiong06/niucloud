<?php
declare(strict_types=1);
namespace addon\hsx_lead\scripts;

use PDO;
use RuntimeException;
use Throwable;
use think\facade\Db;

/** Explicit schema-only upgrade. Existing rewards become level 1; never calculate old level 2. */
final class PromotionLevelsUpgrade
{
    public function __construct(private string $backend,private bool $includeMemberIndex=false){}
    private function prefix():string{$c=Db::getConfig();$p=(string)($c['connections'][$c['default']]['prefix']??'');if(!preg_match('/^[A-Za-z0-9_]*$/D',$p)||strlen($p)>30)throw new RuntimeException('数据库前缀无效');return$p;}
    private function indexes(string$table):array
    {
        $out=[];foreach(Db::query('SHOW INDEX FROM `'.$table.'`',[],true)as$row){$out[$row['Key_name']]['columns'][(int)$row['Seq_in_index']]=$row['Column_name'];$out[$row['Key_name']]['unique']=(int)$row['Non_unique']===0;if($row['Sub_part']!==null)throw new RuntimeException('现有索引包含前缀，需人工核对');}
        foreach($out as&$row){ksort($row['columns']);$row['columns']=array_values($row['columns']);}unset($row);return$out;
    }
    private function plan():array
    {
        if(realpath($this->backend)!==$this->backend||!Db::name('addon')->where('key','hsx_lead')->find())throw new RuntimeException('插件尚未安装或PHP根目录异常');
        $p=$this->prefix();$reward=$p.'hsx_lead_promotion_reward';$audit=$p.'hsx_lead_audit';$member=$p.'member';$sql=[];$tables=[$reward,$audit];
        foreach([$reward,$audit,$member]as$table){$meta=Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table],true);if(count($meta)!==1||strtoupper((string)$meta[0]['ENGINE'])!=='INNODB')throw new RuntimeException('基础业务表缺失或不是InnoDB，请先核对原插件升级');}
        $fields=Db::query('SHOW COLUMNS FROM `'.$reward.'`',[],true);$fields=array_column($fields,null,'Field');
        if(!isset($fields['commission_level']))$sql[]='ALTER TABLE `'.$reward.'` ADD COLUMN `commission_level` tinyint unsigned NOT NULL DEFAULT 1 AFTER `coop_order_id`';
        else{if(!preg_match('/^tinyint(?:\([0-9]+\))? unsigned$/i',$fields['commission_level']['Type'])||$fields['commission_level']['Null']!=='NO'||(string)$fields['commission_level']['Default']!=='1')throw new RuntimeException('commission_level现有字段定义不符，不会覆盖');if(Db::name('hsx_lead_promotion_reward')->whereNotIn('commission_level',[1,2])->find())throw new RuntimeException('发现超出两级的奖励记录，不会自动改写');}
        $indexes=$this->indexes($reward);$old=['columns'=>['site_id','coop_order_id'],'unique'=>true];$new=['columns'=>['site_id','coop_order_id','commission_level'],'unique'=>true];
        if(isset($indexes['uk_site_order'])&&$indexes['uk_site_order']!==$old)throw new RuntimeException('旧奖励唯一索引定义异常');
        if(isset($indexes['uk_site_order_level'])&&$indexes['uk_site_order_level']!==$new)throw new RuntimeException('新奖励唯一索引定义异常');
        foreach($indexes as$key=>$index)if($index['unique']&&$key!=='PRIMARY'&&!in_array($key,['uk_site_order','uk_site_order_level'],true))throw new RuntimeException('奖励表有未知唯一约束，需人工核对');
        if(!isset($indexes['uk_site_order_level']))$sql[]='ALTER TABLE `'.$reward.'` '.(isset($indexes['uk_site_order'])?'DROP INDEX `uk_site_order`, ':'').'ADD UNIQUE KEY `uk_site_order_level` (`site_id`,`coop_order_id`,`commission_level`)';
        elseif(isset($indexes['uk_site_order']))$sql[]='ALTER TABLE `'.$reward.'` DROP INDEX `uk_site_order`';
        $activity=['site_id','operator_member_id','create_time','entity_type','action'];$ai=$this->indexes($audit);if(isset($ai['idx_member_activity'])&&$ai['idx_member_activity']['columns']!==$activity)throw new RuntimeException('活动审计同名索引不符');if(!isset($ai['idx_member_activity']))$sql[]='ALTER TABLE `'.$audit.'` ADD KEY `idx_member_activity` (`site_id`,`operator_member_id`,`create_time`,`entity_type`,`action`)';
        $memberCovered=false;$mi=$this->indexes($member);foreach($mi as$index)if(array_slice($index['columns'],0,3)===['site_id','pid','member_id'])$memberCovered=true;
        if(!$memberCovered&&$this->includeMemberIndex){if(isset($mi['idx_lead_site_pid_member']))throw new RuntimeException('框架member同名索引不符');$sql[]='ALTER TABLE `'.$member.'` ADD KEY `idx_lead_site_pid_member` (`site_id`,`pid`,`member_id`)';$tables[]=$member;}
        return['statements'=>$sql,'tables'=>$tables,'business_row_writes'=>false,'historical_reward_backfill'=>false,'framework_member_index_requested'=>$this->includeMemberIndex,'framework_member_index_recommended'=>!$memberCovered,'framework_source_changes'=>false];
    }
    public function check():array{$plan=$this->plan();return$plan+['pending_statements'=>count($plan['statements']),'read_only'=>true];}
    public function apply():array
    {
        $connection=Db::connect();$database=(string)($connection->query('SELECT DATABASE() AS db',[],true)[0]['db']??'');$pdo=$connection->getPdo();if(!$pdo instanceof PDO||$pdo->inTransaction())throw new RuntimeException('DDL必须在独立连接执行');$key='lead_promotion_levels:'.substr(hash('sha256',$database.':'.$this->prefix()),0,36);
        if((int)(Db::query('SELECT GET_LOCK(?,0) AS n',[$key],true)[0]['n']??0)!==1)throw new RuntimeException('推广层级升级正在执行');$old=null;
        try{$plan=$this->plan();if(!$plan['statements'])return$this->check()+['applied'=>false,'backup'=>''];
            $path=$this->backend.'/runtime/hsx_lead_upgrade/promotion-levels-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));for($parent=$path;$parent!==dirname($parent);$parent=dirname($parent))if(is_link($parent))throw new RuntimeException('备份路径包含符号链接');if(!mkdir($path,0700,true)||!chmod($path,0700))throw new RuntimeException('无法建立私有结构备份');
            $before=[];foreach($plan['tables']as$table){$ddl=Db::query('SHOW CREATE TABLE `'.$table.'`',[],true)[0]['Create Table']??'';$before[$table]=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',(string)$ddl);}$backup=$path.'/schema-before.json';$handle=fopen($backup,'x');if(!$handle)throw new RuntimeException('无法建立结构备份');try{if(!chmod($backup,0600))throw new RuntimeException('无法保护备份');$json=json_encode(['schema'=>$before,'plan'=>$plan],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);if(fwrite($handle,$json)!==strlen($json)||!fflush($handle))throw new RuntimeException('结构备份未完整写入');}finally{fclose($handle);}
            $old=(int)(Db::query('SELECT @@SESSION.lock_wait_timeout AS n',[],true)[0]['n']??0);Db::execute('SET SESSION lock_wait_timeout=5');foreach($plan['statements']as$sql){if($pdo->inTransaction())throw new RuntimeException('DDL不能混入业务事务');Db::execute($sql);}
            $after=$this->check();if($after['pending_statements']!==0)throw new RuntimeException('升级结果未完整读回，请重新--check');return array_replace($after,['read_only'=>false,'applied'=>true,'backup'=>$backup]);
        }finally{if($old!==null)try{Db::execute('SET SESSION lock_wait_timeout='.$old);}catch(Throwable$ignored){}try{Db::query('SELECT RELEASE_LOCK(?) AS n',[$key],true);}catch(Throwable$ignored){}}
    }
}
if(!defined('HSX_LEAD_PROMOTION_LEVELS_LIBRARY_ONLY')){
    if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80000)exit(1);$mode=$argv[1]??'';$member=in_array('--include-member-index',$argv,true);
    if(!in_array($mode,['--check','--apply'],true)||count($argv)!==($member?3:2)){fwrite(STDERR,"php addon/hsx_lead/scripts/upgrade-promotion-levels.php --check|--apply [--include-member-index]\n");exit(2);}
    define('HSX_LEAD_UPGRADE_LIBRARY_ONLY',true);require __DIR__.'/upgrade.php';
    try{$backend=dirname(__DIR__,3);bootstrap($backend);$command=new PromotionLevelsUpgrade($backend,$member);echo json_encode($mode==='--check'?$command->check():$command->apply(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;}
    catch(Throwable$error){fwrite(STDERR,'[未确认完成] '.($error instanceof RuntimeException?$error->getMessage():'推广层级结构检查失败，连接详情已隐藏')."\nDDL独立提交，不保证回滚；不卸载重装，修正后重新--check。\n");exit(1);}
}
