<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use RuntimeException;
use think\facade\Db;

/** Explicit additive index upgrade. Never invoked by an API or a worker. */
final class RuntimeIndexUpgrade
{
    private const TABLE='hsx_lead_coop_order';
    private const INDEX='idx_status_expiry';
    private const COLUMNS=['status','create_time','site_id','id'];

    public function __construct(private string $backend) {}

    /** Read-only: no cache, file, business-row or schema writes. */
    public function check(): array
    {
        $db=Db::getConfig();$prefix=(string)($db['connections'][$db['default']]['prefix']??'');
        if(!preg_match('/^[A-Za-z0-9_]*$/D',$prefix))throw new RuntimeException('数据库表前缀无效，未执行');
        $table=$prefix.self::TABLE;
        $meta=Db::query('SELECT ENGINE,TABLE_ROWS,DATA_LENGTH,INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if(!$meta||strtoupper((string)$meta[0]['ENGINE'])!=='INNODB')throw new RuntimeException('缺少原订单表或不是InnoDB；本命令不创建业务表');
        $columns=Db::query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if(array_diff(array_merge(self::COLUMNS,['purpose','update_time']),array_column($columns,'COLUMN_NAME')))throw new RuntimeException('原订单表字段不完整，请先核对完整版本，未执行索引升级');
        $indexes=Db::query('SHOW INDEX FROM `'.$table.'`');$groups=[];
        foreach($indexes as$index)$groups[(string)$index['Key_name']][(int)$index['Seq_in_index']]=$index;
        $hasEquivalent=false;
        foreach($groups as$name=>$parts){
            ksort($parts);$parts=array_values($parts);
            $matches=array_column($parts,'Column_name')===self::COLUMNS;
            foreach($parts as$part)$matches=$matches&&$part['Sub_part']===null&&strtoupper((string)$part['Index_type'])==='BTREE'
                &&strtoupper((string)($part['Visible']??'YES'))==='YES'&&strtoupper((string)($part['Ignored']??'NO'))==='NO';
            if($name===self::INDEX&&!$matches)throw new RuntimeException('同名索引结构不同，拒绝覆盖，请管理员核对');
            if($matches)$hasEquivalent=true;
        }
        $sql='ALTER TABLE `'.$table.'` ADD INDEX `'.self::INDEX.'` (`'.implode('`,`',self::COLUMNS).'`), ALGORITHM=INPLACE, LOCK=NONE';
        return ['table'=>$table,'index'=>self::INDEX,'columns'=>self::COLUMNS,'pending_statements'=>$hasEquivalent?0:1,
            'estimated_rows'=>(int)$meta[0]['TABLE_ROWS'],'estimated_table_bytes'=>(int)$meta[0]['DATA_LENGTH']+(int)$meta[0]['INDEX_LENGTH'],
            'sql'=>$hasEquivalent?null:$sql,'business_row_writes'=>false,'creates_tables'=>false,'changes_fields'=>false];
    }

    public function apply(): array
    {
        $connection=Db::connect();
        // connect() only fills the pool; getPdo() returns the active link, which the first master query sets.
        $connection->query('SELECT 1',[],true);$pdo=$connection->getPdo();
        if(!$pdo)throw new RuntimeException('数据库连接未建立，未执行');
        if($pdo->inTransaction())throw new RuntimeException('索引DDL不能在业务事务内执行');
        $plan=$this->check();if(!$plan['pending_statements'])return $plan+['applied'=>false,'message'=>'索引已存在，未重复执行'];
        $scope=(string)$connection->getConfig('database').':'.(string)$connection->getConfig('prefix');
        $key='lead_runtime_index:'.substr(hash('sha256',$scope),0,40);
        $lock=$connection->query('SELECT GET_LOCK(?, 0) AS acquired',[$key],true);
        if((int)($lock[0]['acquired']??0)!==1)throw new RuntimeException('本项目索引升级正在执行，请勿重复运行');
        $oldWait=null;
        try{
            $plan=$this->check();if(!$plan['pending_statements'])return $plan+['applied'=>false,'message'=>'索引已存在，未重复执行'];
            $path=$this->backend.'/runtime/hsx_lead_upgrade/runtime-indexes-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
            for($parent=dirname($path);$parent!==dirname($parent);$parent=dirname($parent))if(is_link($parent))throw new RuntimeException('结构备份路径含符号链接，未执行');
            if(!mkdir($path,0700,true)&&!is_dir($path))throw new RuntimeException('无法创建结构备份目录，未执行');
            chmod($path,0700);
            $before=['plan'=>$plan,'create_table'=>Db::query('SHOW CREATE TABLE `'.$plan['table'].'`'),'indexes'=>Db::query('SHOW INDEX FROM `'.$plan['table'].'`')];
            $backup=$path.'/schema-before.json';
            if(file_put_contents($backup,json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('结构备份失败，未执行');
            chmod($backup,0600);
            $oldWait=(int)($connection->query('SELECT @@SESSION.lock_wait_timeout AS wait_seconds')[0]['wait_seconds']??0);
            $connection->execute('SET SESSION lock_wait_timeout=5');
            // No blocking/COPY fallback. MySQL DDL commits independently; an
            // interrupted command must be followed by --check, not a fake rollback.
            $connection->execute($plan['sql']);
            $after=$this->check();if($after['pending_statements'])throw new RuntimeException('执行后索引读回未确认，请重新运行--check核对');
            return $after+['applied'=>true,'backup'=>$backup,'message'=>'仅新增超时扫描索引，未修改订单、金额、会员或配置'];
        }finally{
            if($oldWait!==null)try{$connection->execute('SET SESSION lock_wait_timeout='.$oldWait);}catch(\Throwable $ignored){}
            try{$connection->query('SELECT RELEASE_LOCK(?) AS released',[$key],true);}catch(\Throwable $ignored){}
        }
    }
}

if(!defined('HSX_LEAD_RUNTIME_INDEX_LIBRARY_ONLY')){
    if(PHP_SAPI!=='cli')exit(1);
    $mode=$argv[1]??'';
    if(count($argv)!==2||!in_array($mode,['--check','--apply'],true))exit("php addon/hsx_lead/scripts/upgrade-runtime-indexes.php --check|--apply\n");
    define('HSX_LEAD_UPGRADE_LIBRARY_ONLY',true);require __DIR__.'/upgrade.php';
    try{
        $backend=dirname(__DIR__,3);bootstrap($backend);$command=new RuntimeIndexUpgrade($backend);
        echo json_encode($mode==='--check'?$command->check():$command->apply(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
    }catch(\Throwable $error){
        fwrite(STDERR,'[未确认完成] '.($error instanceof RuntimeException?$error->getMessage():'索引操作失败或数据库不支持在线建索引，未降级为锁表建索引')."\n请重新--check核对；DDL不保证事务回滚，勿卸载重装。\n");exit(1);
    }
}
