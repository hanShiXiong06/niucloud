<?php
declare(strict_types=1);

if (getenv('HSX_ERP_LIFECYCLE_ROLLBACK_TEST') !== '1') { fwrite(STDERR,"Set HSX_ERP_LIFECYCLE_ROLLBACK_TEST=1\n"); exit(2); }
require dirname(__DIR__,3).'/vendor/autoload.php';
use think\facade\Db;
use think\facade\Event;
use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use addon\hsx_erp\app\service\admin\ErpSaleService;
use addon\hsx_erp\app\service\admin\ErpSaleReturnService;
use addon\hsx_erp\app\service\admin\ErpFinanceService;

(new think\App())->initialize();
set_exception_handler(static function(Throwable $e):void { fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1); });
if (!in_array(config('database.connections.mysql.hostname'),['127.0.0.1','localhost'],true)) throw new RuntimeException('仅允许本地库');
// 专用空站点，无打印机、公众号或企业微信配置；领域消费者在本测试进程关闭。
$site=900000001;
if (Db::name('site')->where('site_id',$site)->count() || Db::name('erp_asset')->where('site_id',$site)->count()) throw new RuntimeException('隔离站点已被使用');
foreach (Db::query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ?', [config('database.connections.mysql.prefix').'erp_%']) as $row) {
    if (strtoupper((string)$row['ENGINE'])!=='INNODB') throw new RuntimeException('ERP表不支持回滚：'.$row['TABLE_NAME']);
}
Event::remove('ErpDomainEvent');
request()->siteId($site); request()->uid(1); request()->username('完整链路回滚测试'); request()->appType('adminapi');
$count=0;
$assert=static function(bool $ok,string $message) use (&$count):void{$count++;if(!$ok)throw new RuntimeException($message);};
$tag='FLOW'.date('His').bin2hex(random_bytes(3));
$add=static fn($table,$data)=>(int)Db::name($table)->insertGetId(['site_id'=>$site]+$data);
$get=static fn($table,$id)=>Db::name($table)->where('site_id',$site)->where('id',$id)->find();
Db::startTrans();
try {
    $supplier=$add('erp_party',['party_no'=>$tag.'S','party_name'=>'测试供货商','party_type'=>'supplier','status'=>1]);
    $customer=$add('erp_party',['party_no'=>$tag.'C','party_name'=>'测试销售客户','party_type'=>'customer','status'=>1]);
    $warehouse=$add('erp_warehouse',['warehouse_name'=>'测试同行仓','warehouse_type'=>'peer','status'=>1,'allow_direct_sale'=>1]);
    $location=$add('erp_warehouse_location',['warehouse_id'=>$warehouse,'location_name'=>'测试库位','status'=>1]);
    $account=$add('erp_capital_account',['account_name'=>$tag,'account_type'=>'bank','balance'=>20000,'status'=>1]);
    $purchase=ErpPurchaseService::forSite($site,1,'测试');
    $sales=ErpSaleService::forSite($site,1,'测试');
    $finance=ErpFinanceService::forSite($site,1,'测试');
    $returns=new ErpSaleReturnService();
    $expectedBalance=20000.0;
    foreach ([0,2000,5000] as $index=>$received) {
        $purchaseData=['party_id'=>$supplier,'party_name'=>'测试供货商','request_id'=>$tag.'P'.$index,'settle_method'=>'挂账',
            'items'=>[['imei'=>$tag.$index,'model'=>'测试手机','spec'=>'256G 黑色','image_urls'=>json_encode(['/static/test-only.png']),'retail_price'=>5000,'purchase_cost'=>4500,'warehouse_id'=>$warehouse,'location_id'=>$location]]];
        $po=$purchase->create($purchaseData);
        $assert($purchase->create($purchaseData)===$po,'采购重复提交不重复入库');
        $asset=Db::name('erp_asset')->where('site_id',$site)->where('purchase_order_id',$po)->find();
        $assert($asset && $asset['status']==='in_stock' && (float)$asset['total_cost']===4500.0,'采购生成可追溯库存成本');
        $payable=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->where('source_id',$asset['id'])->find();
        $assert($payable && (float)$payable['amount']===4500.0 && (int)$payable['party_id']===$supplier,'入库生成对应供应商应付');
        $finance->confirmPaymentInTransaction((int)$payable['id'],4500,['capital_account_id'=>$account,'request_id'=>$tag.'PAY'.$index]);
        $expectedBalance-=4500;
        $assert((float)$get('erp_capital_account',$account)['balance']===$expectedBalance,'采购付款扣减账户');
        $saleData=['party_id'=>$customer,'party_name'=>'测试销售客户','request_id'=>$tag.'SALE'.$index,'settle_method'=>'挂账',
            'items'=>[['asset_id'=>$asset['id'],'sale_price'=>5000]]];
        $so=$sales->create($saleData);
        $assert($sales->create($saleData)===$so,'销售重复提交不重复出库');
        $assert($get('erp_asset',$asset['id'])['status']==='sold','销售后设备退出库存');
        $receivable=Db::name('erp_receivable')->where('site_id',$site)->where('source_type','sale')->where('source_id',$so)->find();
        $assert($receivable && (float)$receivable['amount']===5000.0,'销售产生客户应收5000');
        if($received>0){
            $finance->confirmReceivableItemsInTransaction($customer,[['receivable_id'=>$receivable['id'],'amount'=>$received]],['capital_account_id'=>$account,'request_id'=>$tag.'REC'.$index]);
            $expectedBalance+=$received;
        }
        $assert((float)$get('erp_capital_account',$account)['balance']===$expectedBalance,'登记收款只增加实际到账金额');
        $returnData=['sale_order_id'=>$so,'request_id'=>$tag.'RET'.$index,'refund_mode'=>'payable','items'=>[['asset_id'=>$asset['id'],'return_price'=>5000,'reason'=>'本地回归测试']]];
        $ret=$returns->create($returnData);
        $assert($get('erp_asset',$asset['id'])['status']==='sold','仅申请退货不提前回库');
        $returns->confirm($ret);
        $returns->confirm($ret);
        $assert($get('erp_asset',$asset['id'])['status']==='in_stock','确认收到设备后回庫且重试幂等');
        $returnPayables=Db::name('erp_payable')->where('site_id',$site)->where('source_type','sale_return')->where('source_id',$ret)->select()->toArray();
        $assert(count($returnPayables)===($received>0?1:0),'只对已收到的金额生成退款应付');
        $assert((float)$get('erp_receivable',$receivable['id'])['amount']===(float)$received,'退货冲销未收款，不抹掉已收事实');
        if($received>0){
            $assert((float)$returnPayables[0]['amount']===(float)$received && (int)$returnPayables[0]['party_id']===$customer,'退款金额及对象准确');
            $finance->confirmPaymentInTransaction((int)$returnPayables[0]['id'],(float)$received,['capital_account_id'=>$account,'request_id'=>$tag.'REF'.$index]);
            $expectedBalance-=$received;
        }
        $assert((float)$get('erp_capital_account',$account)['balance']===$expectedBalance,'退款后资金闭环');
        $assert((float)$get('erp_asset',$asset['id'])['total_cost']===4500.0,'退回设备保留真实采购成本');
    }
} finally { Db::rollback(); }
$assert(Db::name('erp_asset')->where('site_id',$site)->count()===0,'隔离样本完全回滚');
echo "PASS purchase/sale/return finance lifecycle: {$count} assertions; fixtures rolled back; external consumers disabled.\n";
