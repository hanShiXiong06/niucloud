<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

final class MappingCandidateService
{
    public function search(int $site,string $kind,string $ref): array
    {
        if(!in_array($kind,['staff','customer','supplier'],true)) return ['match'=>'manual','items'=>[]];
        MappingSourceService::assertMatches($site,$kind,$ref);
        $id=(int)substr($ref,strpos($ref,':')+1);
        $phone=$kind==='staff'?Db::name('sys_user')->where('uid',$id)->value('mobile'):Db::name('erp_party')->where('site_id',$site)->where('id',$id)->value('contact_mobile');
        $hash=ReferenceService::phoneHash($site,(string)$phone);
        if($hash==='') return ['match'=>'missing_phone','items'=>[]];
        $c=(new ConfigService())->get($site); $key=$c['profiles'][$c['mode']]['connection_key']; $resource=$kind==='staff'?'staff':'parties';
        $run=ReferenceService::latestRun($site,$key,$resource);
        if($run==='') return ['match'=>'sync_required','items'=>[]];
        $q=Db::name('hsx_boyuan_reference')->where('site_id',$site)->where('connection_key',$key)->where('resource',$resource)->where('phone_hash',$hash)->where('enabled',1)->where('seen_run',$run);
        if($kind!=='staff') $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(extra_json, '$.".($kind==='customer'?'is_cust':'is_supplier')."')) = '1'");
        $total=(clone $q)->count();
        $rows=$q->field('external_id,code,name,phone_masked,extra_json')->order('id')->limit(100)->select()->toArray();
        $items=[];
        foreach($rows as $row) $items[]=['id'=>$row['external_id'],'code'=>$row['code'],'name'=>$row['name'],'enabled'=>true,'phone_masked'=>$row['phone_masked'],'extra'=>Contract::decode($row['extra_json'])];
        return ['match'=>$total===1?'unique':($total>1?'ambiguous':'not_found'),'total'=>$total,'items'=>$items];
    }
}
