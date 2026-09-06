<?php
declare(strict_types=1);

// 仅本地开发库；新增隔离测试记录，最外层事务必回滚。不执行建表、真实转账或通知派发。
if (getenv('HSX_ERP_PRICE_ROLLBACK_TEST') !== '1') {
    fwrite(STDERR, "Set HSX_ERP_PRICE_ROLLBACK_TEST=1 to run local rollback-only integration test.\n"); exit(2);
}
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\service\admin\ErpPurchaseService;
use addon\hsx_erp\app\service\admin\ErpFinanceService;
use addon\hsx_erp\app\service\admin\ErpStockService;
use addon\hsx_erp\app\listener\RecycleErpPaymentOwnershipRequested;
use addon\hsx_recycle\app\listener\erp\ErpPurchasePriceAdjustmentRequested;
use addon\hsx_recycle\app\listener\erp\ErpSourcePaymentGuardRequested;
use addon\hsx_recycle\app\listener\downstream\ErpSettlementCompletedListener;
use think\facade\Db;
use think\facade\Event;

$app = new think\App(); $app->initialize();
if (!in_array(config('database.connections.mysql.hostname'), ['127.0.0.1','localhost'], true)) {
    throw new RuntimeException('只允许本地数据库');
}
$tables = ['recycle_order','recycle_device','recycle_device_log','recycle_device_payment', 'erp_party',
    'erp_capital_account','erp_purchase_order','erp_purchase_item','erp_asset','erp_payable','erp_asset_ledger',
    'erp_account_ledger','erp_money_ledger','erp_settlement','erp_settlement_link','erp_outbox_event'];
foreach ($tables as $table) {
    $name = config('database.connections.mysql.prefix') . $table;
    $rows = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name]);
    if (strtoupper((string)($rows[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException($table . ' 不支持事务回滚');
}
$site = 100000;
request()->siteId($site); request()->username('采购补差回滚测试'); request()->appType('adminapi');
foreach (['RecycleErpPaymentOwnershipRequested'=>RecycleErpPaymentOwnershipRequested::class,
    'ErpPurchasePriceAdjustmentRequested'=>ErpPurchasePriceAdjustmentRequested::class,
    'ErpSourcePaymentGuardRequested'=>ErpSourcePaymentGuardRequested::class] as $event=>$listener) {
    Event::remove($event); Event::listen($event,$listener);
}
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++; if (!$ok) throw new RuntimeException($message);
};
$reject = static function (callable $fn, string $contains) use ($assert): void {
    try { $fn(); } catch (Throwable $e) { $assert(str_contains($e->getMessage(),$contains), $contains . '；实际：' . $e->getMessage()); return; }
    $assert(false,'未阻止：' . $contains);
};
$tag = 'TP' . date('His') . bin2hex(random_bytes(4));
$created = [];
$insert = static function (string $table, array $data) use ($site, &$created): int {
    $id = (int)Db::name($table)->insertGetId(['site_id'=>$site] + $data);
    $created[] = [$table,$id]; return $id;
};
$get = static fn(string $table,int $id): array => Db::name($table)->where('site_id',$site)->where('id',$id)->find();
$error = null;
Db::startTrans();
try {
    $party = $insert('erp_party',['party_no'=>$tag,'party_name'=>$tag,'party_type'=>'supplier','status'=>1]);
    $account = $insert('erp_capital_account',['account_name'=>$tag,'account_type'=>'bank','balance'=>10000,'status'=>1]);
    $sourceOrder = $insert('recycle_order',['order_no'=>$tag,'flow_mode'=>'device','status'=>7,'pay_status'=>1,'total_amount'=>4600,'device_count'=>1,'member_id'=>0]);
    $device = $insert('recycle_device',['order_id'=>$sourceOrder,'imei'=>$tag,'model'=>'TEST','status'=>5,
        'confirm_status'=>1,'final_price'=>4600,'pay_status'=>1,'pay_amount'=>4600,'pay_time'=>time()-100,'pay_no'=>$tag.'OLD']);
    $oldPayment = $insert('recycle_device_payment',['order_id'=>$sourceOrder,'device_id'=>$device,'pay_no'=>$tag.'OLD','amount'=>4600]);
    $purchase = $insert('erp_purchase_order',['purchase_no'=>$tag,'party_id'=>$party,'party_name'=>$tag,
        'total_cost'=>4600,'paid_amount'=>4600,'payable_amount'=>0,'finance_status'=>'settled','status'=>'completed',
        'source_plugin'=>'hsx_recycle','source_type'=>'order','source_id'=>$sourceOrder]);
    $spec = json_encode(['source_device_id'=>$device,'source_plugin'=>'hsx_recycle']);
    $asset = $insert('erp_asset',['asset_no'=>$tag,'purchase_order_id'=>$purchase,'party_id'=>$party,'party_name'=>$tag,
        'imei'=>$tag,'model'=>'TEST','spec_json'=>$spec,'purchase_cost'=>4600,'total_cost'=>4600,'status'=>'in_stock',
        'source_plugin'=>'hsx_recycle','source_type'=>'order','source_id'=>$sourceOrder]);
    $item = $insert('erp_purchase_item',['purchase_order_id'=>$purchase,'item_type'=>'device','asset_id'=>$asset,
        'spec_json'=>$spec,'purchase_cost'=>4600,'total_cost'=>4600,'status'=>'completed']);
    Db::name('erp_asset')->where('id',$asset)->update(['purchase_item_id'=>$item]);
    $payable = $insert('erp_payable',['payable_no'=>$tag,'party_id'=>$party,'party_name'=>$tag,
        'source_type'=>'purchase_asset','source_id'=>$asset,'source_no'=>$tag,'asset_id'=>$asset,
        'origin_plugin'=>'hsx_recycle','origin_type'=>'order','origin_id'=>$sourceOrder,'origin_no'=>$tag,
        'amount'=>4600,'settled_amount'=>4600,'status'=>'settled']);
    $service = ErpPurchaseService::forSite($site,0,'采购补差测试');
    $finance = ErpFinanceService::forSite($site,0,'采购补差测试');
    $moneyCount = (int)Db::name('erp_money_ledger')->where('capital_account_id',$account)->count();
    $service->adjustCost($item,100,'客户议价补100',true,$tag.'ADJ','purchase_adjust',4600);
    $assert($get('erp_asset',$asset)['total_cost'] == 4700,'设备成本4700');
    $p = $get('erp_payable',$payable);
    $assert($p['amount'] == 4700 && $p['settled_amount'] == 4600 && $p['status'] === 'partial','原应付待付100');
    $d = $get('recycle_device',$device);
    $assert($d['final_price'] == 4700 && $d['pay_amount'] == 4600 && $d['pay_status'] == 2,'回收价格4700已付4600');
    $assert($get('recycle_order',$sourceOrder)['pay_status'] == 2,'订单重新显示部分付款');
    $assert($get('recycle_order',$sourceOrder)['status'] == 7,'不倒退业务完结状态');
    $assert($get('erp_purchase_order',$purchase)['payable_amount'] == 100,'原采购待付100');
    $assert(Db::name('erp_money_ledger')->where('capital_account_id',$account)->count() == $moneyCount,'调价不产生资金流水');
    $service->adjustCost($item,100,'客户议价补100',true,$tag.'ADJ','purchase_adjust',4600);
    $assert(Db::name('erp_asset_ledger')->where('asset_id',$asset)->count() == 1,'调价重复请求幂等');
    $stock = ErpStockService::forSite($site,0,'采购补差测试');
    $assert($stock->adjustCost($asset,4700,'重试相同目标价格',true,$tag.'ADJ','purchase_adjust'),'PC/移动端目标价格重复提交返回成功');
    $reject(static fn()=>$stock->adjustCost($asset,4800,'错误重试',true,$tag.'ADJ','purchase_adjust'),'其他调价');
    $page = $finance->payablePartyItems($party,['source_type'=>'purchase','purchase_order_id'=>$purchase,'page'=>1,'limit'=>15]);
    $row = $page['data'][0] ?? [];
    $assert(($row['payable_amount'] ?? 0) == 4700 && ($row['allocated_paid'] ?? 0) == 4600 && ($row['allocated_remain'] ?? 0) == 100,'付款页面实际查询返回4700/4600/100');
    $assert(($row['latest_purchase_adjustment']['cost_delta'] ?? 0) == 100 && ($row['latest_purchase_adjustment']['remark'] ?? '') === '客户议价补100','页面返回明确调价原因与差额');
    $reject(static fn()=>$service->adjustCost($item,101,'不一致',true,$tag.'ADJ'), '其他金额');
    $reject(static fn()=>$service->adjustCost($item,100,'过期页面',true,$tag.'STALE','purchase_adjust',4600),'刷新');
    $events = [];
    foreach ([4650,4700] as $index=>$cumulative) {
        $settlement = $finance->confirmPaymentInTransaction($payable,50,['capital_account_id'=>$account,'request_id'=>$tag.'PAY'.$index,'remark'=>'仅补差50']);
        $outbox = Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.settlement.completed.v1')->order('id desc')->find();
        $event = json_decode($outbox['payload_json'],true);
        $assert((int)$event['payload']['settlement_id'] === $settlement,'读取本次实际结算发件箱');
        $events[] = $event;
        $ack = (new ErpSettlementCompletedListener())->handle($event);
        $assert($ack['status'] === 'processed','来源回写成功：'.json_encode($ack,JSON_UNESCAPED_UNICODE));
        $d = $get('recycle_device',$device);
        $assert($d['pay_amount'] == $cumulative && $d['pay_status'] == ($index===0?2:1),'累计金额与部分/全额状态正确');
        $assert($get('erp_payable',$payable)['settled_amount'] == $cumulative,'两边累计一致');
        $payment = Db::name('recycle_device_payment')->where('device_id',$device)->where('pay_no',$event['payload']['settlement_no'])->find();
        $assert($payment['amount'] == 50,'本次流水只记50');
        $assert((new ErpSettlementCompletedListener())->handle($event)['status'] === 'duplicate','回写重试不重复记录');
    }
    $assert($get('erp_capital_account',$account)['balance'] == 9900,'账户只减少100');
    $page = $finance->payablePartyItems($party,['source_type'=>'purchase','purchase_order_id'=>$purchase]);
    $assert(count($page['data'][0]['settlements'] ?? []) === 2,'付款页面查询可查看两次实际补付记录');
    $assert(Db::name('erp_money_ledger')->where('capital_account_id',$account)->sum('amount') == 100,'ERP资金流水合计仅100');
    $assert(Db::name('recycle_device_payment')->where('device_id',$device)->sum('amount') == 4700,'回收原流水4600+两次50');
    $assert($get('recycle_device_payment',$oldPayment)['amount'] == 4600,'原付款未改');
    $assert($get('erp_purchase_order',$purchase)['paid_amount'] == 4700 && $get('erp_purchase_order',$purchase)['payable_amount'] == 0,'采购总计已付4700待付0');
    $assert($get('recycle_order',$sourceOrder)['pay_status'] == 1,'回收订单再次结清');
    $reject(static fn()=>$finance->confirmPaymentInTransaction($payable,100,['capital_account_id'=>$account]),'只能付款');
    $reject(static fn()=>$service->adjustCost($item,-100,'退差',true,$tag.'REDUCE'),'退差款');
    Event::remove('ErpPurchasePriceAdjustmentRequested');
    $reject(static fn()=>$service->adjustCost($item,100,'模拟回收插件未响应',true,$tag.'NOACK'),'未响应');
    $assert($get('erp_payable',$payable)['amount'] == 4700 && $get('recycle_device',$device)['final_price'] == 4700,'缺少回执整笔未保存');
    Event::listen('ErpPurchasePriceAdjustmentRequested',ErpPurchasePriceAdjustmentRequested::class);
    $service->adjustCost($item,100,'再次补差',true,$tag.'ADJ2');
    $assert($get('erp_payable',$payable)['amount'] == 4800 && $get('erp_payable',$payable)['settled_amount'] == 4700,'支持多次调价仍用同一应付');
    $assert((new ErpSettlementCompletedListener())->handle($events[0])['status'] === 'duplicate','旧事件重试不能覆盖新价格');
    $assert($get('recycle_device',$device)['final_price'] == 4800 && $get('recycle_device',$device)['pay_amount'] == 4700,'新价格与历史累计保留');
    $bad = $events[0]; $bad['payload']['targets'][] = $bad['payload']['targets'][0];
    $assert((new ErpSettlementCompletedListener())->handle($bad)['status'] === 'failed','同设备多份快照不能覆盖混算');
} catch (Throwable $e) { $error = $e; }
finally { Db::rollback(); }
foreach ($created as [$table,$id]) $assert(Db::name($table)->where('id',$id)->count() == 0,'测试记录已回滚：'.$table);
foreach (['erp_asset_ledger','erp_account_ledger'] as $table) {
    if (isset($asset)) $assert(Db::name($table)->where('asset_id',$asset)->count() == 0,'附加账本已回滚');
}
if (isset($account)) $assert(Db::name('erp_money_ledger')->where('capital_account_id',$account)->count() == 0,'资金流水已回滚');
if (isset($device)) foreach (['recycle_device_payment','recycle_device_log'] as $table) {
    $assert(Db::name($table)->where('device_id',$device)->count() == 0,'来源记录已回滚');
}
if ($error) { fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n"); exit(1); }
echo "PASS local purchase price adjustment integration: {$count} assertions; all fixtures rolled back; no DDL/notifications/external payments.\n";
