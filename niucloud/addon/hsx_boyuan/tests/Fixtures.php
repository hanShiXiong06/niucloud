<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\tests;
use think\facade\Db;
use addon\hsx_boyuan\app\service\{ConfigService,TaskService};
use addon\hsx_boyuan\app\support\Contract;
use addon\hsx_erp\app\service\admin\{ErpPurchaseService,ErpIntegrationService};
final class Fixtures
{
    public static function seed(int $site): void
    {
        (new \addon\hsx_boyuan\Addon())->install();
        (new ConfigService())->save($site,1,['revision'=>0,'mode'=>'mock','enabled'=>true,'paused'=>false]);
        foreach ([['SAMPLE_SUP','演示供应商','supplier'],['SAMPLE_CUS','演示客户','customer']] as [$no,$name,$type]) Db::name('erp_party')->insert(['site_id'=>$site,'party_no'=>$no,'party_name'=>$name,'party_type'=>$type,'status'=>1]);
        $warehouse=Db::name('erp_warehouse')->insertGetId(['site_id'=>$site,'warehouse_name'=>'演示设备仓','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
        Db::name('erp_warehouse_location')->insert(['site_id'=>$site,'warehouse_id'=>$warehouse,'location_name'=>'A区','status'=>1]);
        self::purchase($site,'success',true);
        for($i=0;$i<4;$i++) self::tick($site);
        self::purchase($site,'success',false);
        self::purchase($site,'timeout_after_commit',true);
        self::purchase($site,'partial',true);
        self::purchase($site,'rejected',true);
        for($i=0;$i<4;$i++) self::tick($site);
    }
    public static function purchase(int $site,string $scenario,bool $mapped=false): int
    {
        $service=new ConfigService(); $config=$service->get($site);
        $service->save($site,1,['revision'=>$config['revision'],'profile'=>['scenario'=>$scenario]]);
        $warehouse=Db::name('erp_warehouse')->where('site_id',$site)->find();
        $location=Db::name('erp_warehouse_location')->where('site_id',$site)->find(); $supplier=Db::name('erp_party')->where('site_id',$site)->where('party_type','supplier')->find();
        $token=strtoupper(bin2hex(random_bytes(4))); $items=[];
        foreach ([2000,1800] as $index=>$price) $items[]=['imei'=>'LOCAL_SAMPLE_'.$token.'_'.$index,'model'=>$mapped?'iPhone 15 Pro':'待对应测试型号 '.$token,'spec'=>'256GB','color'=>$mapped?'原色钛金属':'黑色',
            'purchase_cost'=>$price,'retail_price'=>$price+500,'warehouse_id'=>$warehouse['id'],'location_id'=>$location['id'],'image_urls'=>'["/static/fixture.png"]'];
        $id=ErpPurchaseService::forSite($site,1,'本地测试员')->create(['request_id'=>'DEMO_'.$token,'party_id'=>$supplier['id'],'party_name'=>$supplier['party_name'],'settle_method'=>'挂账','items'=>$items]);
        self::dispatch($site);
        if ($mapped) {
            $task=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('kind','purchase')->order('id desc')->find();
            foreach (Contract::decode($task['snapshot_json'])['lines'] as $line) {
                self::map($site,$task['connection_key'],'product',$line['product_ref'],$line['product_name']);
                self::map($site,$task['connection_key'],'warehouse',$line['warehouse_ref'],'演示设备仓');
            }
        }
        return $id;
    }
    private static function map(int $site,string $connection,string $kind,string $ref,string $name): void
    {
        if (Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$connection)->where('kind',$kind)->where('local_ref',$ref)->count()) return;
        Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$site,'connection_key'=>$connection,'kind'=>$kind,'local_ref'=>$ref,'local_name'=>$name,
            'external_id'=>'MOCK_'.strtoupper($kind).'_'.substr(hash('sha256',$ref),0,8),'external_code'=>'CODE_'.substr(hash('sha256',$ref),0,8),'external_name'=>$name,'extra_json'=>'{"unit_code":"TAI"}','update_at'=>time()]);
    }
    public static function dispatch(int $site): void
    {
        $service=ErpIntegrationService::forSite($site,1,'本地测试员');
        foreach (Db::name('erp_outbox_event')->where('site_id',$site)->whereIn('status',['pending','failed'])->column('id') as $id) $service->dispatchDomainEvent((int)$id);
    }
    public static function tick(int $site): void
    {
        self::dispatch($site);
        Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->whereIn('status',['queued','retry'])->update(['next_at'=>0]);
        (new \addon\hsx_boyuan\app\service\ReferenceService())->tick($site);
        // Virtual time is accelerated only in this isolated acceptance harness.
        Db::name('hsx_boyuan_task')->where('site_id',$site)->whereIn('status',['pending','waiting','confirming'])->update(['next_at'=>0]);
        (new TaskService())->tick($site,100);
    }
}
