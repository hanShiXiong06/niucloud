<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\{Db,Log};

/** Local master data only. This service never contacts Boyuan or calls an ERP ledger posting method. */
final class OpeningPostingService
{
    private function context(int $site,string $expected): array
    {
        $config=(new ConfigService())->get($site,false);
        $mode=$config['mode']; $profile=$config['profiles'][$mode];
        if ($expected==='' || !hash_equals((string)$profile['connection_key'],$expected))
            throw new Problem('OPENING_CONNECTION','账套已改变，请刷新期初建账页面');
        if (!in_array($mode,['test','production'],true) || $profile['protocol']!=='openapi_v153' || empty($profile['probe_at']) || $profile['account_id']==='')
            throw new Problem('OPENING_UNVERIFIED','请先保存并核验博远账套；演示资料不能建入 ERP');
        return [$mode,(string)$profile['account_id']];
    }

    // Scan both complete site_id index ranges on the master. During apply the
    // SERIALIZABLE transaction also protects empty ranges against concurrent ERP inserts.
    private function balances(int $site,bool $lock): array
    {
        $result=[];
        foreach (['receivable','payable'] as $kind) {
            $query=Db::name('erp_'.$kind)->master()->force('uk_site_no')->where('site_id',$site);
            if ($lock) $query->lock(true);
            // Treat an inconsistent status or amount as unresolved. Never net A/R and A/P.
            $row=$query->fieldRaw("COALESCE(SUM(CASE WHEN status='void' THEN 0 WHEN status IN ('settled','completed') AND amount=settled_amount AND amount>=0 THEN 0 ELSE 1 END),0) AS unresolved")->find();
            if (!is_array($row) || !isset($row['unresolved']) || !ctype_digit((string)$row['unresolved']))
                throw new Problem('OPENING_LEDGER_UNKNOWN','应收或应付状态未能核实，未执行建档');
            $result[$kind.'_count']=(int)$row['unresolved'];
        }
        return $result;
    }
    private function eligibleRun(int $site,string $connection,string $mode,string $resource,bool $lock=false): string
    {
        $query=Db::name('hsx_boyuan_reference_sync')->master()->where(['site_id'=>$site,'connection_key'=>$connection,'mode'=>$mode,'resource'=>$resource])->order('id desc');
        if ($lock) $query->lock(true);
        $job=$query->field('status,run_key')->find();
        return ($job['status']??null)==='completed'?(string)$job['run_key']:'';
    }

    public function check(int $site,string $connection): array
    {
        [$mode,$account]=$this->context($site,$connection);
        $gate=$this->balances($site,false);
        $allowed=!$gate['receivable_count'] && !$gate['payable_count'];
        $rows=[];
        foreach (OpeningMasterService::RESOURCES as $resource) {
            $ready=$this->eligibleRun($site,$connection,$mode,$resource)!=='';
            $rows[]=['resource'=>$resource,'name'=>OpenApi::RESOURCES[$resource][0],'ready'=>$ready,'reason'=>$ready?'':'请先完成本类博远档案同步并核对数量'];
        }
        return $gate+['account'=>$account,'ready'=>$allowed,'reason'=>$allowed?'':'本站还有未结清或状态异常的应收、应付，请财务先核对后再建档。','resources'=>$rows,
            'accounts_ready'=>$this->eligibleRun($site,$connection,$mode,OpeningCapitalAccounts::RESOURCE)!==''];
    }

    public function accountOptions(int $site,string $connection,int $cursor): array
    {
        [$mode]=$this->context($site,$connection);
        $run=$this->eligibleRun($site,$connection,$mode,OpeningCapitalAccounts::RESOURCE);
        if($run==='') throw new Problem('OPENING_SOURCE','请先完整取回博远资金账户');
        return OpeningCapitalAccounts::options($site,$connection,$run,$cursor);
    }
    private function token(int $site,int $uid,string $connection,string $mode,string $resource,string $run,int $cursor,int $epoch,array $plan): string
    {
        $key=(string)env('app.auth_key','');
        if ($key==='') throw new Problem('OPENING_SIGNING','未配置系统密钥，不能确认期初建档');
        return hash_hmac('sha256',Contract::json(['opening-master-v1',$site,$uid,$connection,$mode,$resource,$run,$cursor,$epoch,$plan]),$key);
    }
    public function handle(int $site,int $uid,array $input,bool $write): array
    {
        $connection=$input['connection_key']??null; $resource=$input['resource']??null;
        $cursor=filter_var($input['cursor']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $accounts=$resource===OpeningCapitalAccounts::RESOURCE;
        if ($site<=0 || $uid<=0 || !is_string($connection) || !is_string($resource) || (!$accounts && !in_array($resource,OpeningMasterService::RESOURCES,true)) || $cursor===false || ($accounts && $cursor!==0))
            throw new Problem('OPENING_PARAMS','请选择要建入本站的资料并重新预览');
        $choices=$input['choices']??[];
        $epoch=time(); $signature='';
        if ($write) {
            if (!in_array($input['confirm']??false,[true,1,'1'],true) || !is_string($input['token']??null) || !preg_match('/^(\d{10}):([a-f0-9]{64})$/D',$input['token'],$match))
                throw new Problem('OPENING_CONFIRM','请先预览，并明确确认要建入本站的资料');
            $epoch=(int)$match[1]; $signature=$match[2];
            if ($epoch>time() || time()-$epoch>3600) throw new Problem('OPENING_EXPIRED','预览已超过一小时，请重新预览');
            // Must apply to this transaction, never change the session/server default.
            Db::execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        }
        $result=Db::transaction(function() use ($site,$uid,$connection,$resource,$cursor,$epoch,$signature,$write,$accounts,$choices) {
            if ($write && !Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            [$mode]=$this->context($site,$connection);
            $gate=$this->balances($site,$write);
            if ($gate['receivable_count'] || $gate['payable_count'])
                throw new Problem('OPENING_OUTSTANDING','本站有待收 '.$gate['receivable_count'].' 笔、待付 '.$gate['payable_count'].' 笔（含账款状态异常）；本批未写入。');
            $run=$this->eligibleRun($site,$connection,$mode,$resource,$write);
            if ($run==='') throw new Problem('OPENING_SOURCE','本类档案最新一轮尚未完整同步，不能用于建档');
            $master=new OpeningMasterService();
            $plan=$accounts?OpeningCapitalAccounts::plan($site,$connection,$run,$choices):$master->page($site,$uid,$connection,$resource,$run,$cursor,false);
            $sign=$this->token($site,$uid,$connection,$mode,$resource,$run,$cursor,$epoch,$plan);
            if (!$write) return $plan+['token'=>$epoch.':'.$sign];
            if (!hash_equals($sign,$signature)) throw new Problem('OPENING_CHANGED','预览后的档案或对应关系已变化，请重新预览；本批未写入');
            if($accounts) {
                OpeningCapitalAccounts::write($site,$uid,$connection,$plan);
                return $plan;
            }
            $actual=$master->page($site,$uid,$connection,$resource,$run,$cursor,true);
            if ($actual!==$plan) throw new Problem('OPENING_CHANGED','资料发生变化，本批已回滚；请重新预览');
            return $actual;
        });
        if ($write) Log::info('博远期初主档批次已确认',['site_id'=>$site,'uid'=>$uid,'resource'=>$resource,'cursor'=>$cursor,
            'created'=>count(array_filter($result['rows'],static fn(array $r):bool=>$r['action']==='create')),
            'linked'=>count(array_filter($result['rows'],static fn(array $r):bool=>$r['action']==='link'))]);
        return $result;
    }
}
