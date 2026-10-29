<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\adminapi\controller;
use addon\hsx_boyuan\app\service\{ConfigService,TaskService,PermissionService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\facade\{Db,Log};
final class Workbench extends BaseAdminController
{
    private function run(string $method,string $api,callable $callback)
    {
        (new PermissionService())->assertApi($method,'boyuan/'.$api);
        try { return success($callback((int)$this->request->siteId(),(int)$this->request->uid())); }
        catch (Problem $e) { throw new CommonException('['.$e->reason.'] '.$e->getMessage()); }
        catch (\Throwable $e) { $info=$e instanceof \think\db\exception\PDOException ? ($e->getData()['PDO Error Info']??[]) : []; $driver=(int)($info['Driver Error Code']??0); Log::error('hsx_boyuan command error; type='.get_class($e).'; driver_code='.$driver.'; location='.basename($e->getFile()).':'.$e->getLine().($driver===1146?'; missing_table='.(preg_match("/Table '([a-zA-Z0-9_.]+)'/",(string)($info['Driver Error Message']??''),$m)?$m[1]:'unknown'):'')); throw new CommonException('操作未完成，请按任务编号核查插件安装和服务状态'); }
    }
    public function config() { return $this->run('get','config',fn($site)=>(new ConfigService())->get($site)); }
    public function saveConfig() {
        $data=$this->request->params([['revision',-1],['mode','mock'],['enabled',false],['paused',true],['finance_enabled',false],['profile',[]],['assignee_uid',0],['confirm_account','']]);
        return $this->run('post','config',fn($site,$uid)=>(new ConfigService())->save($site,$uid,$data));
    }
    public function probe() { return $this->run('post','probe',fn($site,$uid)=>(new ConfigService())->probe($site,$uid)); }
    public function staff() { return $this->run('get','staff',function($site) {
        $ids=Db::name('sys_user_role')->where('site_id',$site)->where('status',1)->column('uid');
        return $ids?Db::name('sys_user')->whereIn('uid',$ids)->where('status',1)->field('uid,username,real_name')->select()->toArray():[];
    }); }
    public function tasks() {
        $input=$this->request->params([['page',1],['limit',15],['status',''],['keyword',''],['mode','']]);
        return $this->run('get','tasks',function($site) use($input) {
            $query=Db::name('hsx_boyuan_task')->where('site_id',$site);
            if ($input['mode']!=='') $query->where('mode',$input['mode']);
            $counts=clone $query;
            if ($input['status']!=='') $query->where('status',$input['status']);
            if (trim($input['keyword'])!=='') $query->where('business_no','like','%'.addcslashes(mb_substr(trim($input['keyword']),0,100),'%_\\').'%');
            $rows=(clone $query)->field('id,mode,kind,business_no,status,reason,external_no,attempts,notice_status,create_at,update_at,connection_key')->order('id desc')->page(max(1,(int)$input['page']),min(100,max(1,(int)$input['limit'])))->select()->toArray();
            return ['total'=>(clone $query)->count(),'data'=>$rows,'counts'=>$counts->field('status,COUNT(*) AS total')->group('status')->select()->toArray()];
        });
    }
    public function detail(int $id) { return $this->run('get','tasks/<id>',function($site) use($id) {
        $row=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('id',$id)->find(); if (!$row) throw new Problem('TASK_MISSING','任务不存在');
        foreach (['snapshot','request','result'] as $name) { $row[$name]=Contract::redact(Contract::decode($row[$name.'_json'])); unset($row[$name.'_json']); }
        unset($row['lease_token']);
        $row['logs']=Db::name('hsx_boyuan_log')->where('site_id',$site)->where('task_id',$id)->order('id')->limit(200)->select()->toArray();
        $config=(new ConfigService())->get($site); $row['active_connection']=$row['connection_key']===$config['profiles'][$config['mode']]['connection_key'] && $row['mode']===$config['mode'];
        return $row;
    }); }
    public function action(int $id) {
        $input=$this->request->params([['action',''],['note',''],['external_no','']]);
        return $this->run('post','tasks/<id>/action',function($site,$uid) use($id,$input) { (new TaskService())->action($site,$id,$uid,$input['action'],$input['note'],$input['external_no']); return ['queued'=>true,'message'=>'处理请求已记录，实际结果由后台异步核验']; });
    }
    public function mappings() {
        $input=$this->request->params([['page',1],['limit',50],['kind',''],['local_ref',''],['keyword','']]);
        return $this->run('get','mappings',function($site) use($input) {
        $c=(new ConfigService())->get($site); $query=Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$c['profiles'][$c['mode']]['connection_key']);
        foreach(['kind','local_ref'] as $field) if($input[$field]!=='') $query->where($field,$input[$field]);
        if(trim($input['keyword'])!=='') $query->where(function($q) use($input){$pattern='%'.addcslashes(mb_substr(trim($input['keyword']),0,100),'%_\\').'%';$q->whereLike('local_name',$pattern)->whereOr('external_name','like',$pattern)->whereOr('external_code','like',$pattern);});
        return ['total'=>(clone $query)->count(),'data'=>$query->order('id desc')->page(max(1,(int)$input['page']),min(100,max(1,(int)$input['limit'])))->select()->toArray()];
    }); }
    public function saveMapping() {
        $input=$this->request->params([['kind',''],['local_ref',''],['local_name',''],['external_id',''],['external_code',''],['external_name',''],['extra',[]],['revision',0]]);
        return $this->run('post','mappings',function($site,$uid) use($input) {
            if (!in_array($input['kind'],['product','warehouse','location','supplier','customer','staff','method','account'],true)) throw new Problem('MAPPING_INVALID','资料类型无效');
            foreach (['local_ref','external_id','external_code'] as $field) Contract::id($input[$field]);
            if ($input['kind']==='product') Contract::id($input['extra']['unit_code']??null);
            Db::transaction(function() use($site,$uid,$input) {
                Db::name('site')->where('site_id',$site)->lock(true)->find(); $c=(new ConfigService())->get($site); $key=$c['profiles'][$c['mode']]['connection_key'];
                if ($key==='') throw new Problem('CONFIG_REQUIRED','请先保存当前环境配置');
                $where=[['site_id','=',$site],['connection_key','=',$key],['kind','=',$input['kind']],['local_ref','=',$input['local_ref']]];
                $old=Db::name('hsx_boyuan_mapping')->where($where)->find();
                if ((int)($old['revision']??0)!==(int)$input['revision']) throw new Problem('MAPPING_STALE','对应关系已被修改，请刷新');
                $value=['external_id'=>$input['external_id'],'external_code'=>$input['external_code'],'external_name'=>mb_substr($input['external_name'],0,255),
                    'local_name'=>mb_substr($input['local_name'],0,255),'extra_json'=>Contract::json((array)$input['extra']),'revision'=>(int)$input['revision']+1,'operator_uid'=>$uid,'update_at'=>time()];
                if ($old) Db::name('hsx_boyuan_mapping')->where($where)->update($value);
                else Db::name('hsx_boyuan_mapping')->insert($value+['site_id'=>$site,'connection_key'=>$key,'kind'=>$input['kind'],'local_ref'=>$input['local_ref']]);
                TaskService::audit($site,0,$key,'mapping','saved','资料对应已保存；已发送请求不被修改',$uid,['before'=>$old?Contract::redact($old):[],'after'=>$value]);
            }); return [];
        });
    }
    public function references() {
        $input=$this->request->params([['resource','warehouses'],['keyword',''],['cursor',''],['limit',50]]);
        return $this->run('get','references',function($site) use($input) {
            if (!in_array($input['resource'],['warehouses','products','staff','payment_methods','capital_accounts'],true)) throw new Problem('RESOURCE_INVALID','档案类型无效');
            $c=(new ConfigService())->get($site,false); $p=$c['profiles'][$c['mode']];
            return (new ConfigService())->transport($site,$p,$c['mode'])->send('GET',$p['paths']['references'],$input);
        });
    }
    public function logs() { return $this->run('get','logs',fn($site)=>Db::name('hsx_boyuan_log')->where('site_id',$site)->field('id,task_id,action,outcome,message,operator_uid,create_at')->order('id desc')->limit(100)->select()->toArray()); }
}
