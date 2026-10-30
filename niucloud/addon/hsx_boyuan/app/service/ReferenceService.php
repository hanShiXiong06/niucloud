<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\gateway\{OpenApi,Transport};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

final class ReferenceService
{
    private function context(int $site): array
    {
        $service=new ConfigService(); if(!$service->installed($site)) throw new Problem('ADDON_MISSING','请先安装ERP及博远插件');
        $config=$service->get($site,false); return [$config,$config['profiles'][$config['mode']]];
    }
    public function start(int $site,int $uid,array $resources): array
    {
        if(!$resources || count($resources)>count(OpenApi::RESOURCES)) throw new Problem('RESOURCE_INVALID','请选择需要同步的基础资料');
        foreach($resources as $resource) if(!is_string($resource) || !isset(OpenApi::RESOURCES[$resource])) throw new Problem('RESOURCE_INVALID','资料类型不支持');
        return Db::transaction(function() use($site,$uid,$resources): array {
            Db::name('site')->where('site_id',$site)->lock(true)->find(); [$config,$profile]=$this->context($site);
            if($profile['connection_key']==='') throw new Problem('CONFIG_REQUIRED','请先保存连接配置');
            if($config['mode']!=='mock' && ($profile['protocol']!=='openapi_v153' || !$profile['probe_at'])) throw new Problem('PREFLIGHT_REQUIRED','请先保存开放平台授权并通过只读账套核验');
            $ids=[];
            foreach(array_unique($resources) as $resource) {
                $where=[['site_id','=',$site],['connection_key','=',$profile['connection_key']],['resource','=',$resource]];
                $running=Db::name('hsx_boyuan_reference_sync')->where($where)->whereIn('status',['queued','running','retry'])->order('id desc')->find();
                if($running) {$ids[]=(int)$running['id']; continue;}
                $ids[]=(int)Db::name('hsx_boyuan_reference_sync')->insertGetId(['site_id'=>$site,'connection_key'=>$profile['connection_key'],'mode'=>$config['mode'],
                    'resource'=>$resource,'run_key'=>bin2hex(random_bytes(16)),'operator_uid'=>$uid,'scope_json'=>Contract::json(['since'=>$profile['member_since'],
                        'until'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d')]),'create_at'=>time(),'update_at'=>time()]);
                TaskService::audit($site,0,$profile['connection_key'],'reference_start','queued',OpenApi::RESOURCES[$resource][0].'同步已排队',$uid,['sync_id'=>end($ids)]);
            }
            return ['ids'=>$ids,'queued'=>true];
        });
    }
    public function retry(int $site,int $uid,int $id): void
    {
        Db::transaction(function() use($site,$uid,$id): void {
            $row=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('id',$id)->lock(true)->find();
            [$config,$p]=$this->context($site);
            if(!$row || $row['connection_key']!==$p['connection_key'] || $row['mode']!==$config['mode']) throw new Problem('SYNC_MISSING','同步任务不属于当前连接');
            if($row['status']!=='failed') throw new Problem('SYNC_STATE','仅失败任务可从原分页继续');
            Db::name('hsx_boyuan_reference_sync')->where('id',$id)->update(['status'=>'queued','attempts'=>0,'next_at'=>0,'reason'=>'','update_at'=>time()]);
            TaskService::audit($site,0,$p['connection_key'],'reference_retry','queued','人工请求从失败分页继续',$uid,['sync_id'=>$id,'cursor'=>$row['cursor']]);
        });
    }
    public function jobs(int $site): array
    {
        [$config,$p]=$this->context($site); $result=[];
        foreach(OpenApi::RESOURCES as $resource=>[$name]) {
            $q=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('resource',$resource);
            $job=(clone $q)->field('id,status,cursor,processed,pages,reported_total,reason,warning,scope_json,update_at')->order('id desc')->find();
            if($job) { $job['scope']=Contract::decode($job['scope_json']); unset($job['scope_json']); }
            $last=(clone $q)->where('status','completed')->order('id desc')->value('update_at');
            $count=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('resource',$resource)->count();
            $result[]=['resource'=>$resource,'name'=>$name,'count'=>$count,'last_success_at'=>(int)$last,'job'=>$job];
        }
        return $result;
    }
    public static function phoneHash(int $site,string $phone): string
    {
        $phone=trim($phone); if(!preg_match('/^\+?[0-9 ()-]{6,24}$/D',$phone)) return '';
        $digits=preg_replace('/\D/','',$phone); if(strlen($digits)<6 || strlen($digits)>20) return '';
        $key=(string)env('app.auth_key',''); if($key==='') throw new Problem('ENCRYPTION_KEY_MISSING','系统身份密钥未配置');
        return hash_hmac('sha256',$site.':'.$digits,$key);
    }
    public function search(int $site,array $input): array
    {
        [, $p]=$this->context($site); $resource=(string)($input['resource']??'warehouses');
        if(!isset(OpenApi::RESOURCES[$resource])) throw new Problem('RESOURCE_INVALID','资料类型不支持');
        $q=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('resource',$resource);
        $keyword=trim((string)($input['keyword']??''));
        if($keyword!=='') {
            $hash=self::phoneHash($site,$keyword); $pattern='%'.addcslashes(mb_substr($keyword,0,100),'%_\\').'%';
            $q->where(function($where) use($hash,$pattern): void { $where->whereLike('name',$pattern)->whereOr('code','like',$pattern); if($hash!=='') $where->whereOr('phone_hash',$hash); });
        }
        $latest=self::latestRun($site,$p['connection_key'],$resource);
        if(!empty($input['enabled_only'])) $q->where('enabled',1)->where('seen_run',$latest);
        if(($input['role']??'')==='customer') $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(extra_json, '$.is_cust')) = '1'");
        if(($input['role']??'')==='supplier') $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(extra_json, '$.is_supplier')) = '1'");
        $page=max(1,(int)($input['page']??1)); $limit=min(100,max(1,(int)($input['limit']??25))); $total=(clone $q)->count();
        $rows=$q->field('external_id,code,name,enabled,phone_masked,extra_json,seen_run,update_at')->order('id')->page($page,$limit)->select()->toArray(); $items=[];
        foreach($rows as $row) { $row['id']=$row['external_id']; unset($row['external_id']); $row['enabled']=(bool)$row['enabled']; $row['extra']=Contract::decode($row['extra_json']); unset($row['extra_json']);
            $row['stale']=$latest==='' || $row['seen_run']!==$latest; unset($row['seen_run']);
            $items[]=$row;
        }
        return ['items'=>$items,'total'=>$total,'has_more'=>$page*$limit<$total,'next_cursor'=>$page*$limit<$total?(string)($page+1):null,'source'=>'local_archive'];
    }
    public static function latestRun(int $site,string $connection,string $resource): string
    {
        return (string)Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$connection)->where('resource',$resource)->where('status','completed')->order('id desc')->value('run_key');
    }
    public function tick(int $site,int $limit=10,?Transport $transport=null): int
    {
        [$config,$p]=$this->context($site);
        $ids=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('mode',$config['mode'])->whereIn('status',['queued','running','retry'])->where('next_at','<=',time())->order('id')->limit(min(10,max(1,$limit)))->column('id');
        $done=0; foreach($ids as $id) if($this->page($site,(int)$id,$transport)) $done++; return $done;
    }
    public function page(int $site,int $id,?Transport $transport=null): bool
    {
        [$config,$p]=$this->context($site); $lease=bin2hex(random_bytes(16));
        $base=[['site_id','=',$site],['id','=',$id],['connection_key','=',$p['connection_key']],['mode','=',$config['mode']]];
        $claimed=Db::name('hsx_boyuan_reference_sync')->where($base)->where('next_at','<=',time())->where(function($q): void {
            $q->whereIn('status',['queued','retry'])->whereOr(function($stale): void {$stale->where('status','running')->where('lease_until','<',time());});
        })->update(['status'=>'running','lease_token'=>$lease,'lease_until'=>time()+90,'update_at'=>time()]);
        if($claimed!==1) return false;
        $owned=array_merge($base,[['lease_token','=',$lease]]); $row=Db::name('hsx_boyuan_reference_sync')->where($owned)->find();
        try {
            if((int)$row['pages']>=1000) throw new Problem('SYNC_PAGE_LIMIT','已达到1000页安全上限，请核对接口分页');
            $reply=($transport??(new ConfigService())->transport($site,$p,$config['mode']))->send('GET',$p['paths']['references'],['resource'=>$row['resource'],'cursor'=>$row['cursor'],'limit'=>100]+Contract::decode($row['scope_json']));
            $data=$reply['data']??null;
            if(($reply['code']??'')!=='OK' || !is_array($data) || !is_array($data['items']??null) || !array_is_list($data['items']) || !is_bool($data['has_more']??null)) throw new Problem('REFERENCE_RESULT_INVALID','基础资料分页回执不完整');
            $next=(string)($data['next_cursor']??'');
            if($data['has_more'] && (!$data['items'] || !preg_match('/^[1-9][0-9]{0,5}$/D',$next) || (int)$next<=(int)$row['cursor'])) throw new Problem('REFERENCE_CURSOR_INVALID','基础资料分页未前进，已停止同步');
            $fresh=(new ConfigService())->get($site,false);
            if($fresh['mode']!==$config['mode'] || $fresh['profiles'][$fresh['mode']]['connection_key']!==$p['connection_key']) throw new Problem('CONNECTION_CHANGED','连接已改变，旧任务不写入新环境');
            Db::transaction(function() use($site,$id,$owned,$row,$data,$next,$p): void {
                if(!Db::name('hsx_boyuan_reference_sync')->where($owned)->lock(true)->find()) return;
                $seen=[]; $warnings=$row['warning'];
                foreach($data['items'] as $item) {
                    Contract::id($item['id']??null); Contract::id($item['code']??null);
                    if(isset($seen[$item['id']]) || !is_string($item['name']??null) || $item['name']==='' || mb_strlen($item['name'])>255 || !is_bool($item['enabled']??null)) throw new Problem('REFERENCE_RESULT_INVALID','基础资料身份、名称或启用状态异常');
                    $seen[$item['id']]=true; $phone=(string)($item['phone']??'');
                    if(!empty($item['extra']['source_name_missing'])) $warnings='部分档案未提供名称，已保留原记录，仅供核对';
                    $where=[['site_id','=',$site],['connection_key','=',$p['connection_key']],['resource','=',$row['resource']],['external_id','=',$item['id']]];
                    $value=['code'=>$item['code'],'name'=>$item['name'],'enabled'=>(int)$item['enabled'],'phone_hash'=>self::phoneHash($site,$phone),
                        'phone_masked'=>$phone===''?'':mb_substr($phone,0,3).'****'.mb_substr($phone,-4),'extra_json'=>Contract::json((array)($item['extra']??[])),'seen_run'=>$row['run_key'],'update_at'=>time()];
                    if(Db::name('hsx_boyuan_reference')->where($where)->count()) Db::name('hsx_boyuan_reference')->where($where)->update($value);
                    else Db::name('hsx_boyuan_reference')->insert($value+['site_id'=>$site,'connection_key'=>$p['connection_key'],'resource'=>$row['resource'],'external_id'=>$item['id']]);
                }
                $state=$data['has_more']?'queued':'completed';
                $reason='';
                if(!$data['has_more'] && isset($data['total'])) {
                    $received=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$p['connection_key'])->where('resource',$row['resource'])->where('seen_run',$row['run_key'])->count();
                    if($received!==(int)$data['total']) {$state='needs_review'; $reason='博远声明总数'.$data['total'].'，本轮收到唯一档案'.$received.'；已保存有效资料，完整性待博远核对';}
                }
                Db::name('hsx_boyuan_reference_sync')->where($owned)->update(['status'=>$state,'cursor'=>$data['has_more']?$next:$row['cursor'],'processed'=>(int)$row['processed']+count($data['items']),
                    'pages'=>(int)$row['pages']+1,'reported_total'=>$data['total']??-1,'attempts'=>0,'next_at'=>time()+1,'lease_token'=>'','lease_until'=>0,'reason'=>$reason,'warning'=>$warnings,'update_at'=>time()]);
                TaskService::audit($site,0,$p['connection_key'],'reference_page',$state,OpenApi::RESOURCES[$row['resource']][0].'：第'.$row['cursor'].'页已保存'.($state==='completed'?'，本轮拉取完成':''),0,['sync_id'=>$id,'rows'=>count($data['items'])]);
            });
        } catch(\Throwable $e) {
            $attempt=(int)$row['attempts']+1; $state=$attempt>=3?'failed':'retry';
            $reason=$e instanceof Problem?$e->getMessage():'资料同步未完成，请检查插件服务日志';
            Db::name('hsx_boyuan_reference_sync')->where($owned)->update(['status'=>$state,'attempts'=>$attempt,'reason'=>mb_substr($reason,0,1000),'next_at'=>time()+30*$attempt,'lease_token'=>'','lease_until'=>0,'update_at'=>time()]);
            TaskService::audit($site,0,$p['connection_key'],'reference_page',$state,$reason,0,['sync_id'=>$id,'cursor'=>$row['cursor']]);
        }
        return true;
    }
}
