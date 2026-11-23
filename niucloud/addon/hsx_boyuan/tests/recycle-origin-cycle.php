<?php
declare(strict_types=1);

// Invoked only by the offline opening cycle after its fresh database and opening archives are ready.
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService,ManualDocumentCommandService,PurchaseInventoryService,PurchaseSyncService,WholesaleSyncService};
use addon\hsx_boyuan\tests\LocalCycleExchange;
use addon\hsx_erp\app\service\admin\{ErpPurchaseService,ErpSaleService};
use addon\hsx_recycle\app\dict\order\{RecycleDownstreamDict,RecycleOrderDict};
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleDeviceService;
use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_erp\app\service\admin\{ErpCapitalAccountService,ErpFinanceService};
use addon\phone_shop\app\service\admin\cashier\CashierService;
use addon\phone_shop\app\service\admin\order\OrderFinishService;
use addon\phone_shop\app\dict\order\OrderDict;
use think\facade\Db;

function cycleRecycleOrigin(think\App $app,int $site,int $warehouse,int $location,array $customer,OpenApi $api,LocalCycleExchange $wire,ConfigService $config): array
{
    $erp = (require dirname(__DIR__,2).'/hsx_erp/app/event.php')['listen'];
    $recycle = (require dirname(__DIR__,2).'/hsx_recycle/app/event.php')['listen'];
    foreach (['ErpDeviceInboundRequested','RecycleErpPaymentOwnershipRequested','ErpWarehouseOptionsRequested',
        'ErpPartyResolveRequested','ErpSaleCreatedRequested','PhoneShopOrderReturnContext','PhoneShopOrderCreditStateRequested','HsxErpBasicListingEligibility'] as $name) {
        foreach ($erp[$name] as $listener) $app->event->listen($name,$listener);
    }
    $confirmation=null;
    foreach ($recycle['RecycleDeviceConfirmed'] as $listener) {
        $app->event->listen('RecycleDeviceConfirmed',static function(array $event) use ($listener,&$confirmation){
            return $confirmation=(new $listener())->handle($event);
        });
    }
    foreach (['HsxErpBusinessSourceOptions','HsxErpSourceCatalogRequested','ErpSourcePaymentGuardRequested','ErpDomainEvent'] as $name) {
        foreach ($recycle[$name] as $listener) $app->event->listen($name,$listener);
    }
    $mall=(require dirname(__DIR__,2).'/phone_shop/app/event.php')['listen'];
    foreach (['HsxErpBusinessSourceOptions','HsxErpMallInventory','HsxErpListingCatalog','HsxErpMarketplaceProviders','HsxErpPublishListing','ErpDomainEvent'] as $name) {
        foreach ($mall[$name] as $listener) $app->event->listen($name,$listener);
    }
    $rows=static fn(string $name)=>Db::name($name)->where('site_id',$site);
    $version=static fn(array $row)=>array_intersect_key($row,array_flip(['revision','mode','connection_key']));
    $taskRow=static fn(int $id)=>$rows('hsx_boyuan_task')->where('id',$id)->find();
    $imei='990000000000010'; $now=time();
    $member=Db::name('member')->insertGetId(['site_id'=>$site,'nickname'=>'测试回收客户','username'=>'recycle-fixture']);
    $brand=Db::name('recycle_device_model_dict')->insertGetId(['site_id'=>$site,'node_type'=>'brand','node_name'=>'验收牌']);
    $model=Db::name('recycle_device_model_dict')->insertGetId(['site_id'=>$site,'pid'=>$brand,'node_type'=>'model','node_name'=>'验收手机','model_full_name'=>'验收牌/验收手机']);
    $order=Db::name('recycle_order')->insertGetId(['site_id'=>$site,'member_id'=>$member,'order_no'=>'RECYCLE-LOCAL-01','customer_name'=>'测试回收客户',
        'flow_mode'=>'device','count'=>2,'status'=>RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,'create_at'=>$now]);
    $readings=['schema_version'=>'hsx.device.snapshot.v1','platform'=>'ios','identity'=>['imei'=>$imei],
        'hardware'=>['model_number'=>'TEST1','region_code'=>'CH/A','model_number_full'=>'TEST1CH/A']];
    $device=Db::name('recycle_device')->insertGetId(['site_id'=>$site,'order_id'=>$order,'member_id'=>$member,'imei'=>$imei,'category_id'=>$model,'model'=>'验收手机',
        'status'=>RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,'capacity'=>'256GB','color'=>'黑色','price_uid'=>1,'price_at'=>$now,'final_price'=>2000,
        'sell_price'=>2500,'sale_destination'=>'peer','target_warehouse_id'=>$warehouse,'target_location_id'=>$location,'create_at'=>$now,
        'info'=>json_encode(['device_readings'=>['local'=>['raw'=>$readings]]],JSON_THROW_ON_ERROR)]);

    $service=new CoreRecycleDeviceService();
    cycleAssert($service->confirmMemberPrice($device,$site,$member,true)['changed'],'实际确认报价服务接受本机报价');
    cycleAssert(!empty($confirmation['success']),'确认监听器响应：'.($confirmation['message']??'未响应'));
    cycleAssert(!$service->confirmMemberPrice($device,$site,$member,true)['changed'],'重复点击不触发第二次确认');
    $asset=$rows('erp_asset')->where('imei',$imei)->find();
    cycleAssert((bool)$asset && $rows('erp_asset')->count()===1 && $rows('erp_purchase_order')->count()===1,'一次确认生成唯一采购和资产；不需要店员二次录入');
    $aid=(int)$asset['id']; $in=(int)$asset['purchase_order_id'];
    $po=$rows('erp_purchase_order')->where('id',$in)->find();
    $item=$rows('erp_purchase_item')->where('purchase_order_id',$in)->find();
    $spec=json_decode($item['spec_json'],true,512,JSON_THROW_ON_ERROR);
    cycleAssert($po['origin_plugin']==='hsx_recycle' && (int)$po['origin_id']===$order && $spec['source_order_no']==='RECYCLE-LOCAL-01'
        && (int)$spec['source_device_id']===$device && (int)$spec['source_catalog']['model_id']===$model,'原回收订单、设备和型号身份传入 ERP 采购');
    $ap=$rows('erp_payable')->where('source_type','purchase_asset')->where('source_id',$aid)->find();
    cycleAssert($rows('erp_payable')->count()===1 && $ap && $ap['amount']==='2000.00' && $ap['settled_amount']==='0.00','回收确认只形成一笔该机未结应付');
    cycleDispatch($site);
    $task=$rows('hsx_boyuan_task')->where('kind','purchase')->find();
    cycleAssert($task && $task['status']==='awaiting_confirmation' && !isset($wire->calls['boyuan.biz.bill.purchase.generate']),'原采购产生待核任务，确认回收本身不外发');
    $tid=(int)$task['id']; $ps=new PurchaseSyncService(); $view=$ps->view($site,$tid);
    $selection=array_replace($view['selection'],['company_id'=>'COMP01','supplier_id'=>'SUP01','buyer_id'=>'EMP01','creator_id'=>'EMP01']);
    cycleAssert($selection['lines'][0]['warehouse_id']==='WH01','期初仓库对应自动带出');
    $selection['lines'][0]['product_id']='PRO01'; // First-time operator selection from the already synchronized archive.
    $commands=new ManualDocumentCommandService();
    $commands->run($site,$tid,1,'purchase',false,$version($task)+['selection'=>$selection,'remember'=>true,'confirm_pricing_buyer'=>true],false,$api);
    cycleAssert($rows('hsx_boyuan_mapping')->where('connection_key',$task['connection_key'])->where('kind','product')
        ->whereLike('local_ref','recycle-sku:%')->where('external_id','PRO01')->count()===1,'回收原始型号、容量、颜色及版本成为已确认的 SKU 对应，不使用名称匹配');
    $created=$taskRow($tid);
    cycleAssert($created['status']==='created_review' && $created['external_no']!=='','原采购创建仅有待核回执');
    $commands->run($site,$tid,1,'purchase',true,$version($created)+['external_no'=>$created['external_no']],false,$api);
    $verified=$taskRow($tid);
    cycleAssert($verified['status']==='succeeded','同串号采购明细和单号核对成功');
    $inventory=(new PurchaseInventoryService())->queue($site,$tid,1,$version($verified),true,$api);
    cycleAssert($inventory['inventory']['status']==='succeeded','同一串号已获测试对端的在库回执');

    $sales=ErpSaleService::forSite($site,1,'隔离验收');
    $second=$rows('recycle_device')->where('id',$device)->find();
    unset($second['id']); $otherImei='990000000000011';
    $second=array_replace($second,['imei'=>$otherImei,'status'=>RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
        'confirm_status'=>RecycleOrderDict::CONFIRM_STATUS_PENDING,'final_price'=>1000,'downstream_erp_asset_id'=>0,'downstream_stage'=>0,'info'=>'{}']);
    $otherDevice=(int)Db::name('recycle_device')->insertGetId($second);
    cycleAssert($service->confirmMemberPrice($otherDevice,$site,$member,true)['changed'],'同一回收单第二台独立确认入库');
    $other=(int)$rows('erp_asset')->where('imei',$otherImei)->value('id');
    $ap2=$rows('erp_payable')->where('source_type','purchase_asset')->where('source_id',$other)->find();
    cycleAssert($other>0 && $ap2 && $ap2['amount']==='1000.00' && $ap2['id']!==$ap['id'],'同单两串号，两台资产，两笔可分别付款的应付');
    $finance=ErpFinanceService::forSite($site,1,'测试财务'); $recyclePay=new RecycleDevicePaymentService();
    $account=(int)(new ErpCapitalAccountService())->save(['account_name'=>'测试记账账户 A','account_type'=>'cash','balance'=>5000]);
    $accountB=(int)(new ErpCapitalAccountService())->save(['account_name'=>'测试记账账户 B','account_type'=>'cash','balance'=>0]);
    $finance->confirmPayment((int)$ap['id'],2000,['request_id'=>'RECYCLE-CASH-A','capital_account_id'=>$account]);
    cycleDispatch($site);
    $part=$recyclePay->getPaymentSummary($order,$site);
    cycleAssert($part['paid_count']===1 && $part['unpaid_count']===1 && $part['unpaid_amount']===1000.0 && !$part['all_paid']
        && (int)$rows('recycle_order')->where('id',$order)->value('status')!==RecycleOrderDict::ORDER_STATUS_COMPLETED,'只付 A：B 仍待付，整单未完成');
    $buyer=(int)Db::name('member')->insertGetId(['site_id'=>$site,'nickname'=>'测试同行','username'=>'test-peer']);
    Db::name('erp_party_member')->insert(['site_id'=>$site,'party_id'=>$customer['id'],'member_id'=>$buyer,'status'=>1]);
    // 预置运营已选的仓库用途、图片、分类和售价；真正上架须走 ERP 公开服务和商城的建品监听器。
    $category=Db::name('phone_shop_goods_category')->insertGetId(['site_id'=>$site,'category_name'=>'验收手机','level'=>1,'is_show'=>1,'pid'=>0]);
    $rows('erp_warehouse')->where('id',$warehouse)->update(['default_sale_target'=>'mall']);
    $listingSpec=json_decode((string)$asset['spec_json'],true) ?: [];
    $listingSpec['_mall_listing']['category']=['value'=>$category,'path'=>[$category],'names'=>['验收手机']];
    $rows('erp_asset')->where('id',$aid)->update(['sale_target'=>'mall','image_urls'=>json_encode(['upload/test-fixture/device-a.jpg']),
        'retail_price'=>3000,'spec_json'=>json_encode($listingSpec,JSON_THROW_ON_ERROR)]);
    $published=\addon\hsx_erp\app\service\admin\ErpStockService::forSite($site,1,'测试运营')->syncListing($aid,false,true);
    $g=(int)($published['goods_id']??0);
    $sku=(int)$rows('phone_shop_goods_sku')->where(['erp_asset_id'=>$aid,'goods_id'=>$g,'sku_no'=>$imei])->value('sku_id');
    cycleAssert($published['status']==='published' && $sku>0 && $rows('erp_asset')->where('id',$aid)->value('listing_status')==='listed','设备 A 从 ERP 真正建品，与商城可售串号一致');
    // 追加同一商品的第二条 SKU，覆盖一个商品包含多台设备的商户结构。
    $skuB=(int)Db::name('phone_shop_goods_sku')->insertGetId(['site_id'=>$site,'goods_id'=>$g,'sku_no'=>$otherImei,'sku_name'=>'测试','price'=>3000,'stock'=>1,'is_unique'=>1,'erp_asset_id'=>$other]);
    $cashier=new CashierService();
    cycleAssert((int)$cashier->goods(['imei'=>$imei])['total']===1,'收银台按原串号能检索到实际发布的设备');
    $input=['member_id'=>$buyer,'sale_kind'=>'peer','request_id'=>'RECYCLE_ORIGIN_PEER_SALE','payment_mode'=>'offline_credit',
        'items'=>[['sku_id'=>$sku,'asset_id'=>$aid,'sale_price'=>2500]]];
    // 多选也是同一张交付原单。测试事务结束回滚，随后再分别验挂账与现结。
    Db::startTrans();
    try {
        $batch=$input; $batch['request_id']='MALL-BATCH-CHECK'; $batch['items'][]=['sku_id'=>$skuB,'asset_id'=>$other,'sale_price'=>1200];
        $two=$cashier->checkout($batch);
        cycleAssert($two['ok_count']===2 && $rows('phone_shop_order_goods')->where('order_id',$two['order_id'])->count()===2
            && $rows('erp_sale_item')->where('sale_order_id',$two['outbound_id'])->count()===2
            && (float)$rows('erp_receivable')->where(['source_type'=>'sale','source_id'=>$two['outbound_id']])->value('amount')===3700.0,
            '两机一次交付归在一张商城原单和一张销售单，各有原串号行，总应收正确');
    } finally { Db::rollback(); }
    $receipt=$cashier->checkout($input); $out=$receipt['outbound_id'];
    $repeat=$cashier->checkout($input);
    cycleAssert($repeat['outbound_no']===$receipt['outbound_no'] && $repeat['outbound_id']===$out
        && (int)$rows('phone_shop_order')->count()===1 && (int)$rows('phone_shop_goods_sku')->where('sku_id',$skuB)->value('stock')===1,
        '商城只交付所选设备，重复确认复用原单，另一台仍可售');
    $changed=$input; $changed['items'][0]['sale_price']=2499;
    try { $cashier->checkout($changed); throw new LogicException('变更价格却复用了原收银凭证'); } catch (\core\exception\AdminException $expected) {}
    $handoff=$rows('phone_shop_order')->where('order_id',$receipt['order_id'])->find();
    cycleAssert((int)$handoff['member_id']===$buyer && !empty($handoff['taker_name']) && (int)$handoff['staff_id']===1 && (int)$handoff['delivery_time']>0,'原单保留买家、交付人和实际交付时间');
    $sold=$rows('erp_asset')->where('id',$aid)->find();
    $ar=$rows('erp_receivable')->where('source_type','sale')->where('source_id',$out)->find();
    cycleAssert($sold['imei']===$imei && $sold['status']==='sold' && (int)$sold['sale_order_id']===$out && $rows('erp_sale_order')->count()===1
        && $rows('erp_receivable')->count()===1 && $ar && $ar['amount']==='2500.00' && $ar['settled_amount']==='0.00','原机同行挂账出库且形成一笔原销售单应收');
    cycleDispatch($site);
    $mirror=$rows('recycle_device')->where('id',$device)->find();
    $unsold=$rows('phone_shop_goods')->where('goods_id',$g)->find();
    cycleAssert((int)$unsold['status']===1 && $unsold['sale_status']==='available' && (int)$unsold['stock']===1
        && (int)$rows('phone_shop_goods_sku')->where('sku_id',$skuB)->value('stock')===1,'出库回流也保留同商品未选设备的在售资格');
    cycleAssert((int)$mirror['downstream_erp_asset_id']===$aid && (int)$mirror['downstream_stage']===RecycleDownstreamDict::STAGE_SOLD,'回收原设备已收到 ERP 同机已售回写');
    $w=$rows('hsx_boyuan_task')->where('kind',WholesaleSyncService::KIND)->find(); $sid=(int)($w['id']??0);
    cycleAssert($w && $w['status']==='awaiting_confirmation','同行销售原单生成待手动确认的博远批发任务');
    $commands->run($site,$sid,1,WholesaleSyncService::KIND,false,$version($w)+['creator_id'=>'SALEOP'],false,$api);
    $saleCreated=$taskRow($sid);
    cycleAssert($saleCreated['status']==='created_review','博远批发创建回执进入待核');
    $commands->run($site,$sid,1,WholesaleSyncService::KIND,true,$version($saleCreated),false,$api);
    $matched=$taskRow($sid);
    cycleAssert($matched['status']==='succeeded' && $matched['external_no']!=='' && $wire->calls['boyuan.biz.bill.purchase.generate']===1
        && $wire->calls['boyuan.biz.bill.sale.generate']===1,'同一个测试串号只有一次采购、一次同行销售写入');
    $tailSeen=false;
    $app->event->listen('ErpDomainEvent',static function(array $event) use (&$tailSeen):array {
        if (($event['event_id']??'')==='PROBE_ADAPTER_BAD_FACT') $tailSeen=true;
        return [];
    });
    $probe=(array)event('ErpDomainEvent',['event_name'=>'erp.integration.document.confirmed.v1','event_version'=>1,'event_id'=>'PROBE_ADAPTER_BAD_FACT',
        'site_id'=>$site,'occurred_at'=>time(),'payload'=>['kind'=>'sale','sale_type'=>'wholesale','payment_method_ref'=>'method:挂账','source_document_id'=>'invalid','lines'=>[]]]);
    cycleAssert($tailSeen && (bool)array_filter($probe,static fn($ack)=>($ack['consumer']??'')==='hsx_boyuan' && ($ack['status']??'')==='failed'),
        '异常博远事实明确待重试，后面的本地业务监听器仍运行');
    $config->persist($site,array_replace($config->get($site,false),['paused'=>true]));
    $buy=ErpPurchaseService::forSite($site,1,'隔离验收')->info($in);
    $sale=$sales->info($out);
    foreach ([[$buy,$verified],[$sale,$matched]] as [$doc,$task]) {
        $allowedReadLinks=$task['kind']==='purchase'?['boyuan_exchange']:[];
        cycleAssert(($doc['integration_results'][0]['external_no']??'')===$task['external_no'] && array_column($doc['integration_actions'],'key')===$allowedReadLinks,'暂停后原单可查已核单号和只读往来，不提示重新发送');
    }
    $finance->confirmPayment((int)$ap2['id'],1000,['request_id'=>'RECYCLE-CASH-B','capital_account_id'=>$account]);
    cycleDispatch($site);
    $all=$recyclePay->getPaymentSummary($order,$site);
    cycleAssert($all['paid_count']===2 && $all['unpaid_count']===0 && $all['all_paid']
        && (int)$rows('recycle_order')->where('id',$order)->value('status')===RecycleOrderDict::ORDER_STATUS_COMPLETED,'付完最后一台才结束回收单');
    $cash=['member_id'=>$buyer,'sale_kind'=>'retail','request_id'=>'RECYCLE_ORIGIN_CASH_SALE','payment_mode'=>'offline_cash',
        'items'=>[['sku_id'=>$skuB,'asset_id'=>$other,'sale_price'=>1200]],'payments'=>[['account_id'=>$account,'amount'=>200],['account_id'=>$accountB,'amount'=>1000]]];
    $invalid=$cash; $invalid['payments'][1]['account_id']=99999999;
    try { $cashier->checkout($invalid); throw new LogicException('invalid-account-accepted'); } catch (\core\exception\CommonException $expected) {}
    cycleAssert((int)$rows('phone_shop_order')->count()===1 && $rows('erp_asset')->where('id',$other)->value('status')==='in_stock','无效账户时整个出库回滚');
    $cashSale=$cashier->checkout($cash);
    cycleDispatch($site);
    $cashAr=$rows('erp_receivable')->where('source_type','sale')->where('source_id',$cashSale['outbound_id'])->find();
    cycleAssert($cashAr['settled_amount']==='1200.00' && $cashAr['amount']==='1200.00'
        && (float)$rows('erp_capital_account')->where('id',$accountB)->value('balance')===1000.0
        && (int)$rows('phone_shop_goods')->where('goods_id',$g)->value('status')===0
        && $rows('hsx_boyuan_task')->where('kind',WholesaleSyncService::KIND)->count()===1,'最后一台交付后才下架；博远暂停时零售仍可分账户现结，不向批发接口投递');
    // 模拟未安装博远的本站：保留 ERP、回收和商城正常监听，不删除任何业务/档案行。
    $rows('site')->update(['addons'=>json_encode(['hsx_erp','hsx_recycle','phone_shop'])]);
    \think\facade\Cache::delete('site_addon_key_cache'.$site);
    cycleAssert(!$config->installed($site),'站点确实不启用博远');
    $creditState=new \addon\phone_shop\app\service\core\order\CoreOrderCreditStateService();
    $mallOrder=static fn()=>$rows('phone_shop_order')->where('order_id',$receipt['order_id'])->find();
    $openCredit=$creditState->getState($mallOrder());
    cycleAssert($openCredit['matched'] && !$openCredit['can_complete'] && (float)$openCredit['remaining_amount']===2500.0,'交机后仍欠款，不可按整单完成');
    $finish=new OrderFinishService();
    try { $finish->finish($receipt['order_id']); throw new LogicException('欠款单竟然可以点击完成'); } catch (\core\exception\CommonException $expected) {}
    cycleAssert((int)$mallOrder()['status']===OrderDict::WAIT_TAKE,'财务收款前点完成，原单没有提前结束');
    $finance->confirmReceipt((int)$ar['id'],2500,['request_id'=>'MALL-BUYER-RECEIPT','capital_account_id'=>$account]);
    cycleDispatch($site);
    $closedCredit=$creditState->getState($mallOrder());
    cycleAssert($closedCredit['can_complete'] && (float)$closedCredit['remaining_amount']===0.0
        && (string)$mallOrder()['credit_status']==='settled','不接博远，ERP 财务收回挂账后商城原单可以完成');
    $finish->finish($receipt['order_id']);
    cycleAssert((int)$mallOrder()['status']===OrderDict::FINISH && (int)$mallOrder()['finish_time']>0,'财务收款后由店员完成商城原单并保存完成时间');
    return ['start'=>'CoreRecycleDeviceService::confirmMemberPrice，客户接受报价；回收确认、ERP 入库/出库/往来及博远适配器均为正式代码',
        'recycle_order'=>'RECYCLE-LOCAL-01','device_id'=>$device,'imei'=>$imei,'asset_id'=>$aid,'source_model_id'=>$model,'source_stage'=>'已售',
        'erp_purchase'=>$po['purchase_no'],'fixture_purchase'=>$verified['external_no'],'erp_credit_sale'=>$sale['sale_no'],'fixture_sale'=>$matched['external_no'],
        'first_payment'=>['paid'=>$part['paid_count'],'unpaid'=>$part['unpaid_count'],'remaining'=>$part['unpaid_amount']],
        'second_payment'=>['paid'=>$all['paid_count'],'unpaid'=>$all['unpaid_count'],'order_closed'=>$all['all_paid']],
        'mall_listing'=>['status'=>$published['status'],'goods_id'=>$g,'sku_id'=>$sku,'asset_id'=>$aid,'imei'=>$imei],
        'mall_credit'=>$receipt,'mall_cash'=>$cashSale,'second_imei'=>$otherImei,'receivable_at_handoff'=>cycleAmounts($ar),'cash_receivable'=>cycleAmounts($cashAr),
        'credit_after_collection_without_boyuan'=>['settled'=>$closedCredit['settled_amount'],'remaining'=>$closedCredit['remaining_amount'],'order_finished'=>(int)$mallOrder()['status']===OrderDict::FINISH],
        'remote'=>'进程内合成回执，未访问 UAT 或生产，不代表博远账套过账'];
}
