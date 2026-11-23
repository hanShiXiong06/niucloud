<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

use think\facade\Db;

/** One-time archive-only cutover. The frozen v1.2 table is retained untouched, including numeric IDs and snapshots. */
final class CatalogUpgrade
{
    private const COPY=['id','site_id','connection_key','external_id','code','name','enabled','phone_hash','phone_masked','extra_json','seen_run','update_at'];
    public static function run():void
    {
        $db=Db::connect();$db->query('SELECT 1',[],true);$pdo=$db->getPdo();
        $prefix=(string)$db->getConfig('prefix');
        if(!preg_match('/^[a-zA-Z0-9_]*$/D',$prefix))throw new Problem('CATALOG_SCHEMA','档案表前缀无效');
        $dbname=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $lock='boyuan:md:'.substr(hash('sha256',$dbname.':'.$prefix),0,40);
        $s=$pdo->prepare('SELECT GET_LOCK(?,10)');$s->execute([$lock]);
        if((int)$s->fetchColumn()!==1)throw new Problem('CATALOG_BUSY','另一进程正在升级博远资料，请稍后继续');
        try {self::split($pdo,$dbname,$prefix);} finally {$s=$pdo->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
    private static function split(\PDO $pdo,string $db,string $prefix):void
    {
        $kindNames=CatalogStore::kinds();$tables=[];
        foreach($kindNames as $kind)$tables[$kind]=$prefix.CatalogStore::table($kind);
        $exists=function(string $name)use($pdo,$db):bool {
            $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=? AND table_type='BASE TABLE'");$q->execute([$db,$name]);return (int)$q->fetchColumn()===1;
        };
        $quote=static function(string $v):string {if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$v))throw new Problem('CATALOG_SCHEMA','档案表名称超长或无效');return '`'.$v.'`';};
        $old=$prefix.'hsx_boyuan_reference';$frozen=$old.'_v123';$published=count(array_filter($tables,$exists));
        if($published===count($tables) && !$exists($old))return;
        if($published!==0 || ($exists($old) && $exists($frozen)))throw new Problem('CATALOG_CONFLICT','发现冲突的档案结构，未覆盖任何数据。请核实升级状态');
        $src=$exists($old)?$old:($exists($frozen)?$frozen:'');
        if($src!==''){
            $q=$pdo->prepare('SELECT COUNT(*) FROM '.$quote($src).' WHERE resource NOT IN ('.implode(',',array_fill(0,count($kindNames),'?')).')');
            $q->execute($kindNames);if((int)$q->fetchColumn()!==0)throw new Problem('CATALOG_UNKNOWN','旧档案中有未识别的类别，请先核实；没有删除资料');
            if($pdo->query('SELECT 1 FROM '.$quote($src).' WHERE NOT JSON_VALID(extra_json) LIMIT 1')->fetchColumn()!==false)
                throw new Problem('CATALOG_INVALID','旧档案含不可解析的扩展资料，未切换目录；请先核实');
        }
        // Stage schema first. Freeze old writes before copying; a restarted upgrade resumes from the same immutable input.
        foreach($tables as $kind=>$name)$pdo->exec(CatalogStore::schema($kind,$name.'_next'));
        if($src===$old){$pdo->exec('RENAME TABLE '.$quote($old).' TO '.$quote($frozen));$src=$frozen;}
        $metas=implode(',',array_map($quote,self::COPY));$select=implode(',',array_map(fn($c)=>'s.'.$quote($c),self::COPY));
        $same=implode(' AND ',array_map(fn($c)=>'BINARY d.'.$quote($c).' <=> BINARY s.'.$quote($c),self::COPY));
        $rename=[];
        foreach($tables as $kind=>$name){
            $stage=$quote($name.'_next');
            if($src!==''){
                $source=$quote($src);$cursor=0;
                $page=$pdo->prepare('SELECT id FROM '.$source.' WHERE resource=? AND id>? ORDER BY id LIMIT 1000');
                $copy=$pdo->prepare('INSERT INTO '.$stage.' ('.$metas.') SELECT '.$select.' FROM '.$source.' s WHERE s.resource=? AND s.id>? AND s.id<=? AND NOT EXISTS (SELECT 1 FROM '.$stage.' d WHERE d.id=s.id)');
                do {$page->execute([$kind,$cursor]);$ids=$page->fetchAll(\PDO::FETCH_COLUMN);if(!$ids)break;$end=(int)end($ids);$copy->execute([$kind,$cursor,$end]);$cursor=$end;}while(count($ids)===1000);
                $sourceCount=$pdo->prepare('SELECT COUNT(*) FROM '.$source.' WHERE resource=?');$sourceCount->execute([$kind]);
                $n=(int)$pdo->query('SELECT COUNT(*) FROM '.$stage)->fetchColumn();
                $check=$pdo->prepare('SELECT COUNT(*) FROM '.$source.' s LEFT JOIN '.$stage.' d ON d.id=s.id WHERE s.resource=? AND (d.id IS NULL OR NOT ('.$same.'))');$check->execute([$kind]);
                if($n!==(int)$sourceCount->fetchColumn() || (int)$check->fetchColumn()!==0)throw new Problem('CATALOG_MISMATCH','资料拆分未通过逐条核对：'.$kind.'。旧档案已完整保留，未发布新目录');
            } elseif((int)$pdo->query('SELECT COUNT(*) FROM '.$stage)->fetchColumn()!==0)throw new Problem('CATALOG_CONFLICT','空库升级发现非空暂存表，未发布新目录');
            $rename[]=$stage.' TO '.$quote($name);
        }
        $pdo->exec('RENAME TABLE '.implode(',',$rename));
    }
}
