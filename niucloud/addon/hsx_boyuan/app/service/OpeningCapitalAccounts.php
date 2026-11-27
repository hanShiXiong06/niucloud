<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\{CatalogStore,Problem};
use addon\hsx_erp\app\service\admin\ErpCapitalAccountService;
use think\facade\Db;

/** Selected account identities only. The caller owns the opening gate and transaction. */
final class OpeningCapitalAccounts
{
    public const RESOURCE = 'capital_accounts';
    private const LIMIT = 50;

    private static function archive(int $site,string $key,string $run) {
        return CatalogStore::query(self::RESOURCE)->where(['site_id'=>$site,'connection_key'=>$key,'seen_run'=>$run,'enabled'=>1]);
    }
    private static function accounts(int $site) {
        return Db::name('erp_capital_account')->where(['site_id'=>$site,'status'=>1])->where('account_no','not like','system:%');
    }
    private static function links(int $site,string $key) {
        return Db::name('hsx_boyuan_mapping')->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>'account']);
    }

    public static function options(int $site,string $key,string $run,int $cursor): array
    {
        $source=self::archive($site,$key,$run)->where('id','>',$cursor)->order('id')->limit(self::LIMIT+1)
            ->field('id,external_id,name,account_type,company_name')->select()->toArray();
        $more=count($source)>self::LIMIT;
        if($more) array_pop($source);
        $links=self::links($site,$key)->field('external_id,local_ref')->select()->toArray();
        $taken=array_fill_keys(array_column($links,'local_ref'),true);
        $linked=array_fill_keys(array_column($links,'external_id'),true);
        $rows=[];
        foreach($source as $r) {
            // Suggest only an exact ERP type or its label; never guess from an account name or a vendor number.
            $type=(string)$r['account_type'];
            $hint=isset(ErpCapitalAccountService::TYPE_MAP[$type])?$type:array_search($type,ErpCapitalAccountService::TYPE_MAP,true);
            $rows[]=['id'=>(int)$r['id'],'name'=>(string)$r['name'],'company'=>(string)$r['company_name'],
                'suggested_type'=>$hint?:'','linked'=>isset($linked[$r['external_id']])];
        }
        $result=['rows'=>$rows,'next_cursor'=>$more?(int)end($source)['id']:0,'run'=>$run];
        if(!$cursor) {
            $locals=self::accounts($site)->field('id,account_name,account_type')->order('is_default desc,sort asc,id asc')->select()->toArray();
            $available=[];
            foreach($locals as $r) if(!isset($taken['account:'.$r['id']]))
                $available[]=['id'=>(int)$r['id'],'name'=>$r['account_name'],'type'=>$r['account_type']];
            $result+=['locals'=>$available,'types'=>ErpCapitalAccountService::TYPE_MAP];
        }
        return $result;
    }

    public static function plan(int $site,string $key,string $run,$choices): array
    {
        if(!is_array($choices) || !array_is_list($choices) || !$choices || count($choices)>self::LIMIT)
            throw new Problem('OPENING_ACCOUNT_CHOICE','请选择 1 至 50 个要使用的资金账户');
        $rows=[]; $sources=[]; $targets=[]; $newNames=[];
        foreach($choices as $c) {
            if(!is_array($c) || array_diff(array_keys($c),['id','local_id','type']) || !is_int($c['id']??null) || $c['id']<1
                || !is_int($c['local_id']??null) || $c['local_id']<0 || isset($sources[$c['id']]))
                throw new Problem('OPENING_ACCOUNT_CHOICE','请选择不重复的博远账户及其本站处理方式');
            $id=$c['id']; $localId=$c['local_id']; $sources[$id]=true;
            $item=self::archive($site,$key,$run)->where('id',$id)->field('id,external_id,code,name,extra_json,company_name')->find();
            if(!$item || !empty(json_decode($item['extra_json'],true,128,JSON_THROW_ON_ERROR)['source_name_missing']))
                throw new Problem('OPENING_ACCOUNT_SOURCE','所选账户不在本轮启用档案中或没有名称，请重新选择');
            $name=trim((string)$item['name']); $ext=$item['external_id'];
            if($name==='' || mb_strlen($name)>100 || preg_match('/[[:cntrl:]]/u',$name))
                throw new Problem('OPENING_ACCOUNT_SOURCE','博远账户名称为空、过长或有控制字符，请核对后再建档');
            if(self::links($site,$key)->where('external_id',$ext)->count())
                throw new Problem('OPENING_ACCOUNT_LINKED','「'.$name.'」已经有本站对应，不会重复创建或覆盖；请刷新');
            if($localId>0) {
                if(($c['type']??'')!=='' || isset($targets[$localId]) || self::links($site,$key)->where('local_ref','account:'.$localId)->count())
                    throw new Problem('OPENING_ACCOUNT_TAKEN','一个本站账户只能关联本账套中的一个博远账户，不会替换原对应');
                $local=self::accounts($site)->where('id',$localId)->field('account_name,account_type')->find();
                if(!$local) throw new Problem('OPENING_ACCOUNT_TARGET','所选本站账户已停用、不属于本站或为系统账户，请重新选择');
                $targets[$localId]=true; $type=$local['account_type']; $localName=$local['account_name'];
            } else {
                $type=$c['type']??null;
                if(!is_string($type) || !isset(ErpCapitalAccountService::TYPE_MAP[$type]))
                    throw new Problem('OPENING_ACCOUNT_TYPE','请选择「'.$name.'」在本站的账户类型');
                if(Db::name('erp_capital_account')->where(['site_id'=>$site,'account_name'=>$name])->count())
                    throw new Problem('OPENING_ACCOUNT_NAME','本站已有「'.$name.'」，请选择关联已有账户；不会按名称合并或重复建档');
                if(self::archive($site,$key,$run)->where('name',$name)->where('id','<>',$id)->count())
                    throw new Problem('OPENING_ACCOUNT_NAME','博远有同名账户「'.$name.'」，请按所属公司分别关联已有本站账户');
                if(isset($newNames[mb_strtolower($name)])) throw new Problem('OPENING_ACCOUNT_NAME','所选账户名称重复，请分别关联已有本站账户');
                $newNames[mb_strtolower($name)]=true;
                $localName=$name;
            }
            $rows[]=['id'=>$id,'external_id'=>$ext,'code'=>$item['code'],'name'=>$name,'company'=>(string)$item['company_name'],
                'action'=>$localId?'link':'create','local_id'=>$localId,'local_name'=>$localName,'type'=>$type];
        }
        return ['rows'=>$rows,'next_cursor'=>0];
    }

    public static function write(int $site,int $uid,string $key,array $plan): void
    {
        if((int)request()->siteId()!==$site) throw new Problem('OPENING_CONNECTION','本站身份已变化，未写入账户');
        $erp=new ErpCapitalAccountService();
        foreach($plan['rows'] as $row) {
            $id=$row['local_id'];
            if($row['action']==='create') {
                if(Db::name('erp_capital_account')->where(['site_id'=>$site,'account_name'=>$row['local_name']])->count())
                    throw new Problem('OPENING_ACCOUNT_NAME','本站已有同名账户，本次未写入；请重新预览');
                $id=$erp->save(['account_name'=>$row['local_name'],'account_type'=>$row['type'],'balance'=>0,'is_default'=>0]);
            }
            Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$site,'connection_key'=>$key,'kind'=>'account','local_ref'=>'account:'.$id,
                'local_name'=>$row['local_name'],'external_id'=>$row['external_id'],'external_code'=>$row['code'],'external_name'=>$row['name'],
                'extra_json'=>'{}','revision'=>1,'operator_uid'=>$uid,'update_at'=>time()]);
        }
    }
}
