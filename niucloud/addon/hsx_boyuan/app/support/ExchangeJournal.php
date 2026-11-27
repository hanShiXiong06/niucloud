<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

use think\log\Channel;
use think\log\driver\File;

/** Dedicated ThinkPHP Log channel; no schema, raw mixed-server log, SQL audit or operator-supplied paths. */
final class ExchangeJournal
{
    public static function root(): string { return root_path().'runtime/hsx_boyuan/exchanges'; }
    private static function directory(string $root,int $site,string $mode,string $key,string $day): string
    {
        if($site<=0 || $key==='' || !in_array($mode,['test','production'],true)) throw new Problem('JOURNAL_SCOPE','请先连接本站账套');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day) || !checkdate((int)substr($day,5,2),(int)substr($day,8,2),(int)substr($day,0,4))) throw new Problem('JOURNAL_DATE','请选择有效日期');
        return $root.'/'.$site.'/'.hash('sha256',$mode.':'.$key).'/'.$day.'/';
    }
    public static function record(int $site,string $mode,string $key,array $row,?string $root=null): void
    {
        $time=intdiv($row['sent_ms'],1000);
        $path=self::directory($root??self::root(),$site,$mode,$key,date('Y-m-d',$time));
        // Use the hour of the outgoing request also for its result, including a response past midnight.
        self::log($path,date('H',$time).'h',$row);
    }
    private static function log(string $path,string $name,array $row): void
    {
        $app=app();$driver=new File($app,['path'=>$path,'single'=>$name,'file_size'=>PHP_INT_MAX,'format'=>'%3$s']);
        (new Channel('hsx_boyuan_exchange',$driver,[],false,$app->event))->write(Contract::json($row),'info');
    }
    private static function detailLocation(string $directory,string $call,string $side): array
    {
        if(!preg_match('/^[a-f0-9]{16}$/D',$call) || !in_array($side,['request','response'],true)) throw new Problem('JOURNAL_DETAIL','调用或方向无效');
        return [$directory.'detail/'.substr($call,0,2).'/',$call.'-'.$side];
    }
    public static function recordDetail(int $site,string $mode,string $key,array $meta,string $side,array $payload,?string $root=null): void
    {
        $directory=self::directory($root??self::root(),$site,$mode,$key,date('Y-m-d',intdiv($meta['sent_ms'],1000)));
        [$path,$name]=self::detailLocation($directory,$meta['call_id'],$side);
        self::log($path,$name,['site_id'=>$site,'mode'=>$mode,'call_id'=>$meta['call_id'],'side'=>$side,'payload'=>$payload]);
    }
    public static function detail(string $root,int $site,string $mode,string $key,string $day,string $call,string $side): array
    {
        [$path,$name]=self::detailLocation(self::directory($root,$site,$mode,$key,$day),$call,$side);
        $file=$path.$name.'.log';
        if(!is_file($file) || filesize($file)>8388608) throw new Problem('JOURNAL_DETAIL_MISSING','没有可读取的展开报文；已保留的预览仍可查看');
        $r=json_decode((string)file_get_contents($file),true);
        if(!is_array($r) || ($r['site_id']??0)!==$site || ($r['mode']??null)!==$mode || ($r['call_id']??null)!==$call || ($r['side']??null)!==$side || !is_array($r['payload']??null))
            throw new Problem('JOURNAL_DETAIL_INVALID','报文明细不完整，不能据此判断业务结果');
        return ['payload'=>$r['payload']];
    }
    /** A task can be sent/retried on days other than its last local update. Skip empty sections
     * within a read/time budget, newest first. Only the current connection's logs are eligible. */
    public static function forTask(string $root,int $site,string $mode,string $key,string $trace,string $cursor): array
    {
        if(!preg_match('/^T'.preg_quote((string)$site,'/').'\-[1-9][0-9]*$/D',$trace)) throw new Problem('JOURNAL_TASK','请从本站原单选择要核对的任务');
        return self::acrossDays($root,$site,$mode,$key,$trace,$cursor,'task');
    }

    /** The default list starts at the newest actual log day, even when nothing happened today. */
    public static function recent(string $root,int $site,string $mode,string $key,string $keyword,string $cursor): array
    {
        return self::acrossDays($root,$site,$mode,$key,mb_substr(trim($keyword),0,128),$cursor,'recent');
    }

    private static function acrossDays(string $root,int $site,string $mode,string $key,string $keyword,string $cursor,string $scopeType): array
    {
        $scope=dirname(self::directory($root,$site,$mode,$key,'2000-01-01'));
        $tag=substr(hash('sha256',$scopeType.'|'.$site.'|'.$mode.'|'.$key.'|'.$keyword),0,16);
        $at='';$inner='';
        if($cursor!==''){
            $parts=explode('@',$cursor,3);
            if(count($parts)!==3 || $parts[0]!==$tag || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$parts[1]))
                throw new Problem('JOURNAL_CURSOR','筛选已变化，请从最新记录重新查询');
            [, $at, $inner]=$parts;
        }
        $days=[];
        if(is_dir($scope)) foreach(new \DirectoryIterator($scope) as $dir){
            $name=$dir->getFilename();
            if(!$dir->isLink() && $dir->isDir() && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$name) && ($at==='' || strcmp($name,$at)<=0)) $days[]=$name;
        }
        rsort($days,SORT_STRING);
        if(!$days) return ['rows'=>[],'next'=>''];
        $index=0;$inner=$days[0]===$at?$inner:'';
        $deadline=hrtime(true)+200000000;
        for($reads=1;;++$reads){
            $day=$days[$index];
            $page=self::read($root,$site,$mode,$key,$day,$keyword,$inner);
            foreach($page['rows'] as &$r) $r['journal_day']=$day;
            unset($r);
            $inner=$page['next'];
            if($inner==='') ++$index;
            $page['next']=isset($days[$index])?$tag.'@'.$days[$index].'@'.$inner:'';
            // A single read is capped (~768 KiB); keep a resumable cursor even for noisy days.
            if($page['rows'] || $page['next']==='' || $reads>=6 || hrtime(true)>=$deadline) return $page;
        }
    }
    /** Read newest first. Byte cursor bounds each request even on a high-volume account. */
    public static function read(string $root,int $site,string $mode,string $key,string $day,string $keyword,string $cursor): array
    {
        $path=self::directory($root,$site,$mode,$key,$day);
        $hour=23;$offset=null;$rows=[];$budget=768*1024;$q=mb_substr(trim($keyword),0,128);
        $tag=substr(hash('sha256',$day.'|'.$site.'|'.$mode.'|'.$key.'|'.$q),0,16);
        if($cursor!==''){
            if(!preg_match('/^([a-f0-9]{16}):(\d{1,2}):(\d{1,16})$/D',$cursor,$m) || $m[1]!==$tag || (int)$m[2]>23)
                throw new Problem('JOURNAL_CURSOR','筛选已变化，请从最新记录重新查询');
            $hour=(int)$m[2];$offset=(int)$m[3];
        }
        for(;$hour>=0;$hour--,$offset=null) {
            $file=$path.sprintf('%02dh.log',$hour);
            if(!is_file($file)) continue;
            $fp=fopen($file,'rb');if(!$fp) throw new Problem('JOURNAL_UNREADABLE','日志文件暂不可读，请检查服务权限');
            try {
                $end=min((int)fstat($fp)['size'],$offset??PHP_INT_MAX);
                while($end>0) {
                    $start=max(0,$end-65536);
                    if(fseek($fp,$start)!==0 || ($buffer=fread($fp,$end-$start))===false) throw new Problem('JOURNAL_UNREADABLE','接口日志暂时读取失败');
                    $budget-=strlen($buffer);
                    if($start>0) {
                        $cut=strpos($buffer,"\n");
                        // Each bounded diagnostic record fits in one block; never deserialize a partial line.
                        if($cut===false || $cut===strlen($buffer)-1){$end=$start;continue;}
                        $start+=$cut+1;$buffer=substr($buffer,$cut+1);
                    }
                    $lines=explode("\n",$buffer);$position=$end;
                    for($i=count($lines)-1;$i>=0;$i--) {
                        $position-=strlen($lines[$i])+($i===count($lines)-1?0:1);
                        $r=json_decode($lines[$i],true);
                        if(!is_array($r) || ($r['site_id']??0)!==$site || ($r['mode']??null)!==$mode || !preg_match('/^[a-f0-9]{16}$/D',(string)($r['call_id']??''))) continue;
                        if($q!=='' && (preg_match('/^[TS]\d+-\d+$/D',$q)?($r['trace_id']??null)!==$q:mb_stripos($lines[$i],$q)===false)) continue;
                        $id=$r['call_id'];
                        if(!isset($rows[$id])) $rows[$id]=$r;
                        // Results contain the corresponding sanitized request; a request alone is not success.
                        if(count($rows)>=25) return ['rows'=>array_values($rows),'next'=>$tag.':'.$hour.':'.$position];
                    }
                    $end=$start;
                    if($budget<=0) return ['rows'=>array_values($rows),'next'=>$tag.':'.$hour.':'.$end];
                }
            } finally { fclose($fp); }
        }
        return ['rows'=>array_values($rows),'next'=>''];
    }
}
