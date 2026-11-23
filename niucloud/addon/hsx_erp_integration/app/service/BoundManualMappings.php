<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\support\ConnectionFields;
use think\facade\Db;

/** Read-only adapter port. Only one enabled manual connection for this exact authenticated destination. */
final class BoundManualMappings
{
    public static function connection(int $site,string $adapter,string $business,array $identity,?string $manualScope=null): int
    {
        $ids=[];
        foreach(Db::name('erp_integration_connection')->where(['site_id'=>$site,'adapter_code'=>$adapter,'sync_mode'=>'manual','status'=>'enabled'])
            ->field('id,config,business_types')->select()->toArray() as $row) {
            if(!in_array($business,ConnectionFields::values($row['business_types']),true)) continue;
            $cfg=ConnectionFields::values($row['config']);
            if($manualScope!==null && ($cfg['manual_scope']??'')!==$manualScope) continue;
            foreach(['mode','api_url','gateway_path','app_id','account_code'] as $key)
                if(!is_string($identity[$key]??null) || $identity[$key]==='' || ($cfg[$key]??null)!==$identity[$key]) continue 2;
            $ids[]=(int)$row['id'];
        }
        return count($ids)===1?$ids[0]:0;
    }

    public static function rows(int $site,int $connection,string $type,array $ids): array
    {
        return Db::name('erp_integration_mapping')->where(['site_id'=>$site,'connection_id'=>$connection,'resource_type'=>$type,'auto_matched'=>0])
            ->whereIn('local_id',$ids)->field('local_id,external_id,external_code')->select()->toArray();
    }

    public static function staffOwners(int $site,int $connection,array $ids,array $codes): array
    {
        if(!$ids) return [];
        return Db::name('erp_integration_mapping')->where(['site_id'=>$site,'connection_id'=>$connection,'resource_type'=>'staff','auto_matched'=>0])
            ->where(function($q) use($ids,$codes) {
                $q->whereIn('external_id',$ids);
                if($codes) $q->whereOr(function($q) use($codes) { $q->whereIn('external_code',$codes); });
            })->field('local_id,external_id,external_code')->select()->toArray();
    }
}
