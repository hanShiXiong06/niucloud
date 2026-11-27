<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\service\ConfigService;
use addon\hsx_boyuan\app\support\Contract;
use app\model\sys\SysConfig;
use core\exception\CommonException;
use think\facade\Db;

/** Warehouse master ownership is independent of transaction delivery/queue state. No network requests. */
final class WarehouseMasterPolicy
{
    public function handle(array $event): array
    {
        $site=(int)($event['site_id']??0);
        if($site<=0 || !(new ConfigService())->installed($site)) return [];
        $q=SysConfig::where('site_id',$site)->where('config_key',ConfigService::KEY)->master()->limit(2);
        $writing=in_array($event['action']??'',['manual_create','import_warehouse'],true);
        if($writing) $q->lock(true);
        $rows=$q->select()->toArray();
        if(count($rows)>1) throw new CommonException('博远账套配置重复，请核对归属后再维护仓库');
        $c=$rows[0]['value']??[]; $mode=$c['mode']??'mock';
        if(!in_array($mode,['test','production'],true)) return [];
        $p=$c['profiles'][$mode]??[]; $key=(string)($p['connection_key']??'');
        if(($p['protocol']??'')!=='openapi_v153' || ($p['account_id']??'')==='' || $key==='' || empty($p['probe_at'])) return [];
        $runs=Db::name('hsx_boyuan_reference_sync')->master()->where('site_id',$site)->where('connection_key',$key)->where('mode',$mode)->where('resource','warehouses');
        if(empty($p['contract_confirmed']) && !(clone $runs)->where('status','completed')->count()) return [];
        $claim=['provider'=>'hsx_boyuan','label'=>'博远 ERP','reason'=>'本站仓库由博远维护。请同步博远仓库档案，在「博远对接 → 期初建账」确认建仓。新仓会自动配一个本站默认库位。'];
        if(($event['action']??'')!=='import_warehouse' || ($event['provider']??'')!=='hsx_boyuan' || $key!==($event['connection']??null)) return $claim;
        // A caller cannot bless an arbitrary warehouse name, stale archive, disabled record, other company or old mapping.
        $run=$runs->order('id desc')->lock(true)->find();
        $id=$event['external_id']??null;
        if(!is_string($id)||$id===''||strlen($id)>128||($run['status']??'')!=='completed'||($run['run_key']??'')==='') return $claim;
        $a=CatalogStore::query('warehouses')->master()->where(['site_id'=>$site,'connection_key'=>$key,'external_id'=>$id,'enabled'=>1,'seen_run'=>$run['run_key']])->lock(true)->find();
        if(!$a || $a['external_id']!==$id || trim((string)$a['name'])!==($event['warehouse_name']??null)
            || (string)$a['code']!==($event['warehouse_code']??null) || !empty(Contract::decode($a['extra_json'])['source_name_missing'])) return $claim;
        // Do not substitute a similarly named warehouse in another branch, even when the code or name appears the same on screen.
        $others=CatalogStore::query('warehouses')->master()->where(['site_id'=>$site,'connection_key'=>$key,'enabled'=>1,'seen_run'=>$run['run_key']])->where('id','<>',$a['id']);
        if((clone $others)->where('name',$a['name'])->lock(true)->find() || ($a['code']!=='' && (clone $others)->where('code',$a['code'])->lock(true)->find())) return $claim;
        if(Db::name('hsx_boyuan_mapping')->master()->where(['site_id'=>$site,'connection_key'=>$key,'kind'=>'warehouse','external_id'=>$id])->lock(true)->find()) return $claim;
        return $claim+['import_verified'=>true];
    }
}
