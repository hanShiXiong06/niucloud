<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\CatalogStore;
use addon\hsx_boyuan\app\support\ProductionPurchasePilot as Pilot;
use addon\hsx_erp_integration\app\service\BoundManualMappings;
use app\service\core\site\CoreSiteService;

/** Read suggestions only; never write a mapping/draft. A code is not a SKU, a person name, or a login. */
final class BridgeMasterSelections
{
    private const TYPES=['staff'=>['staff','staff','staff'],'warehouse'=>['warehouse','warehouse','warehouses'],'party'=>['party','supplier','parties']];

    public static function merge(int $site,string $key,string $business,array $refs,array $native,array $snapshot=[]): array
    {
        $groups=[];
        foreach($refs as $ref) if(is_string($ref) && preg_match('/^(staff|warehouse|party):([1-9][0-9]*)$/D',$ref,$m)) $groups[$m[1]][(int)$m[2]]=$ref;
        if(!$groups || !class_exists(BoundManualMappings::class)) return $native;
        if(!in_array('hsx_erp_integration',(new CoreSiteService())->getAddonKeysBySiteId($site),true)) return $native;
        $config=(new ConfigService())->get($site,false); $mode=$config['mode']; $p=$config['profiles'][$mode]??[];
        if(!in_array($mode,['test','production'],true) || $key==='' || ($p['connection_key']??'')!==$key || empty($p['probe_at'])) return $native;
        $target=['mode'=>$mode,'api_url'=>$p['endpoint'],'gateway_path'=>$p['gateway_path'],'app_id'=>$p['app_id'],'account_code'=>$p['account_id']];
        $scope=null;
        if($business==='purchase' && $site===Pilot::SITE && array_keys($snapshot['lines']??[])===[0] && Pilot::profile($site,$config,$p))
            $scope=match($snapshot['lines'][0]['imei']??''){
                Pilot::NEXT_IMEI=>Pilot::NEXT_SCOPE,Pilot::THIRD_IMEI=>Pilot::THIRD_SCOPE,default=>null};
        $conn=BoundManualMappings::connection($site,'boyuan',$business,$target,$scope);
        if(!$conn) return $native;
        foreach($groups as $type=>$wanted) {
            [$bridgeType,$kind,$resource]=self::TYPES[$type];
            $maps=BoundManualMappings::rows($site,$conn,$bridgeType,array_keys($wanted)); $ids=$codes=[];
            foreach($maps as $m) {
                if((string)$m['external_id']!=='') $ids[]=(string)$m['external_id'];
                if((string)$m['external_code']!=='') $codes[]=(string)$m['external_code'];
            }
            if($type==='staff') foreach($wanted as $ref) if(!empty($native['staff:'.$ref])) $ids[]=$native['staff:'.$ref];
            if(!$maps && !$ids) continue;
            $run=ReferenceService::latestRun($site,$key,$resource);
            $catalog=[];
            if($run!=='' && ($ids || $codes)) {
                $q=CatalogStore::query($resource)->where(['site_id'=>$site,'connection_key'=>$key,'enabled'=>1,'seen_run'=>$run]);
                if($ids && $codes) $q->where(function($q) use($ids,$codes) { $q->whereIn('external_id',$ids)->whereOr(function($q) use($codes){ $q->whereIn('code',$codes); }); });
                elseif($ids) $q->whereIn('external_id',$ids);
                else $q->whereIn('code',$codes);
                $catalog=$q->field('external_id,code')->select()->toArray();
            }
            $owners=$type==='staff' && $catalog ? BoundManualMappings::staffOwners($site,$conn,array_column($catalog,'external_id'),array_values(array_filter(array_column($catalog,'code'),'strlen'))) : [];
            $native=self::combine($native,$kind,$wanted,$maps,$catalog,$owners);
        }
        return $native;
    }

    public static function combine(array $native,string $kind,array $wanted,array $maps,array $catalog,array $owners): array
    {
        $byId=array_column($catalog,null,'external_id'); $byCode=$direct=[];
        foreach($catalog as $c) $byCode[$c['code']][]=$c['external_id'];
        foreach($maps as $m) $direct[(int)$m['local_id']][]=$m;
        foreach($wanted as $local=>$ref) {
            $k=$kind.':'.$ref; $id=$native[$k]??'';
            if(isset($direct[$local])) {
                $matches=$direct[$local]; $m=$matches[0]; $external=(string)$m['external_id']; $code=(string)$m['external_code'];
                if($external==='') $external=count($byCode[$code]??[])===1?(string)$byCode[$code][0]:'';
                if(count($matches)!==1 || $external==='' || !isset($byId[$external]) || ($code!=='' && $byId[$external]['code']!==$code) || ($id!=='' && $external!==$id)) { $native[$k]=''; continue; }
                $native[$k]=$id=$external;
            }
            if($kind!=='staff' || $id==='') continue;
            foreach($owners as $owner) if((int)$owner['local_id']!==$local && ((string)$owner['external_id']===$id ||
                ((string)$owner['external_code']!=='' && ($byId[$id]['code']??null)===$owner['external_code']))) { $native[$k]=''; break; }
        }
        return $native;
    }
}
