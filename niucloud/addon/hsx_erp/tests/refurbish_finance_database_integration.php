<?php
declare(strict_types=1);

// 本地隔离样本，全程事务回滚；不调用真实支付渠道、不推送通知。
if (getenv('HSX_ERP_REPAIR_ROLLBACK_TEST') !== '1') {
    fwrite(STDERR, "Set HSX_ERP_REPAIR_ROLLBACK_TEST=1 to run.\n"); exit(2);
}
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\service\admin\ErpStockService;
use addon\hsx_erp\app\service\admin\ErpFinanceService;
use think\facade\Db;
use think\facade\Event;

(new think\App())->initialize();
if (!in_array(config('database.connections.mysql.hostname'), ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('仅允许本地库');
foreach (['erp_asset', 'erp_party', 'erp_payable', 'erp_capital_account', 'erp_asset_ledger', 'erp_account_ledger', 'erp_money_ledger', 'erp_settlement', 'erp_settlement_link', 'erp_outbox_event'] as $table) {
    $rows = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix') . $table]);
    if (strtoupper((string)($rows[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException($table . '不能回滚');
}
$site = 100000;
request()->siteId($site); request()->username('维修回滚测试'); request()->appType('adminapi');
$assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void {
    $assertions++; if (!$ok) throw new RuntimeException($message);
};
$reject = static function (callable $action, string $message) use ($assert): void {
    try { $action(); } catch (Throwable $e) { $assert(str_contains($e->getMessage(), $message), '错误提示：' . $e->getMessage()); return; }
    $assert(false, '未拦截：' . $message);
};
$tag = 'REPAIR' . date('His') . bin2hex(random_bytes(3));
// 仅当前进程的扩展字典，不改站点配置或缓存。
$noPayableCategory = 'test_repair.no_payable';
Event::listen('HsxErpFinanceCategories', static fn() => ['categories' => [[
    'key' => $noPayableCategory, 'name' => '测试禁止生成应付', 'direction' => 'expense',
    'scope' => 'refurbish', 'source_plugin' => 'test_repair', 'affects_asset_cost' => 1,
    'creates_finance' => 0, 'party_required' => 1, 'enabled' => 1,
]]]);
$created = [];
$insert = static function (string $table, array $data) use ($site, &$created): int {
    $id = (int)Db::name($table)->insertGetId(['site_id' => $site] + $data); $created[] = [$table, $id]; return $id;
};
$get = static fn(string $table, int $id): array => Db::name($table)->where('site_id', $site)->where('id', $id)->find();
$error = null;
Db::startTrans();
try {
    $supplier = $insert('erp_party', ['party_no' => $tag.'S', 'party_name' => '测试原卖家', 'party_type' => 'supplier', 'status' => 1]);
    $vendor = $insert('erp_party', ['party_no' => $tag.'V', 'party_name' => '测试维修单位', 'party_type' => 'supplier', 'status' => 1]);
    $account = $insert('erp_capital_account', ['account_name' => $tag, 'account_type' => 'bank', 'balance' => 10000, 'status' => 1]);
    $stock = ErpStockService::forSite($site, 0, '维修测试');
    $finance = ErpFinanceService::forSite($site, 0, '维修测试');
    foreach (['success', 'partial', 'failed'] as $resultIndex => $result) {
        $asset = $insert('erp_asset', ['asset_no' => $tag.$result, 'imei' => $tag.$result, 'model' => 'TEST维修样本',
            'party_id' => $supplier, 'party_name' => '测试原卖家', 'purchase_cost' => 4500, 'total_cost' => 4500,
            'status' => 'in_stock', 'refurbish_status' => 'pending', 'sale_target' => 'peer']);
        $purchasePayable = $insert('erp_payable', ['payable_no' => $tag.$result, 'party_id' => $supplier, 'party_name' => '测试原卖家',
            'source_type' => 'purchase_asset', 'source_id' => $asset, 'asset_id' => $asset,
            'amount' => 4500, 'settled_amount' => 4500, 'status' => 'settled']);
        $input = ['result' => $result, 'request_id' => $tag.$result.'C', 'remark' => '维修验证，不发送外部通知',
            'refurbish_items' => [['name' => '更换配件人工', 'amount' => 100, 'party_id' => $vendor]]];
        $bad = $input; $bad['refurbish_items'][0]['party_id'] = 0;
        $reject(static fn() => $stock->completeRefurbish($asset, $bad), '选择服务商');
        $assert($get('erp_asset', $asset)['total_cost'] == 4500, '缺少服务商不得增加成本');
        if ($result === 'success') {
            $bad = $input; $bad['expense_type_key'] = $noPayableCategory;
            $reject(static fn() => $stock->completeRefurbish($asset, $bad), '未开启生成应付');
            $assert($get('erp_asset', $asset)['refurbish_status'] === 'pending'
                && $get('erp_asset', $asset)['total_cost'] == 4500, '关闭应付时不保存完工或成本');
            foreach ([
                ['oops', '格式不正确'],
                [['oops'], '格式不正确'],
                [[['name' => '维修', 'amount' => 0, 'party_id' => $vendor]], '金额必须大于0'],
                [[['name' => '维修', 'amount' => -100, 'party_id' => $vendor]], '金额必须大于0'],
                [[['name' => '维修', 'amount' => 'abc', 'party_id' => $vendor]], '项目和金额'],
                [[['name' => '维修', 'amount' => '1e309', 'party_id' => $vendor]], '金额必须大于0'],
                [[['name' => '维修', 'amount' => 0.001, 'party_id' => $vendor]], '金额必须大于0'],
                [[['name' => '', 'amount' => 100, 'party_id' => $vendor]], '项目和金额'],
                [[['name' => '维修', 'amount' => 100, 'party_id' => 0]], '选择服务商'],
                [[['name' => '维修', 'amount' => 100, 'party_id' => $vendor],
                    ['name' => '抵扣', 'amount' => -100, 'party_id' => $vendor]], '金额必须大于0'],
            ] as [$badItems, $message]) {
                $bad = $input; $bad['refurbish_items'] = $badItems;
                $reject(static fn() => $stock->completeRefurbish($asset, $bad), $message);
                $a = $get('erp_asset', $asset);
                $assert($a['total_cost'] == 4500 && $a['refurbish_cost'] == 0 && $a['refurbish_status'] === 'pending', '错误明细不能变成免费完工');
            }
            foreach (['erp_asset_ledger', 'erp_account_ledger'] as $table) {
                $assert(Db::name($table)->where('site_id', $site)->where('asset_id', $asset)->count() == 0, '拒绝后无遗留账簿：'.$table);
            }
            $assert(Db::name('erp_payable')->where('asset_id', $asset)->where('source_type', 'refurbish')->count() == 0, '拒绝后无遗留应付');
            $assert($get('erp_capital_account', $account)['balance'] == 10000, '拒绝后资金不变');
        }
        $stock->completeRefurbish($asset, $input);
        $a = $get('erp_asset', $asset);
        $assert($a['purchase_cost'] == 4500 && $a['refurbish_cost'] == 100 && $a['total_cost'] == 4600, '4500+维修100=4600');
        $assert($a['refurbish_result'] === $result, '保留修好或修坏结果');
        $p = Db::name('erp_payable')->where('site_id', $site)->where('asset_id', $asset)->where('source_type', 'refurbish')->find();
        $assert($p !== null && $p['party_id'] == $vendor && $p['party_name'] === '测试维修单位', '应付绑定实际维修单位，不是原卖家');
        $assert($p['amount'] == 100 && $p['settled_amount'] == 0 && $p['status'] === 'pending', '维修完工形成待付100');
        $assert(str_contains($p['business_reason'], '测试维修单位'), '应付说明可追溯服务商与设备');
        $assert($get('erp_payable', $purchasePayable)['amount'] == 4500 && $get('erp_payable', $purchasePayable)['settled_amount'] == 4500, '不改变原卖家货款');
        $balanceBeforePayment = (float)$get('erp_capital_account', $account)['balance'];
        $assert($balanceBeforePayment == 10000 - $resultIndex * 100, '登记费用不扣资金');
        $stock->completeRefurbish($asset, $input);
        $assert(Db::name('erp_payable')->where('asset_id', $asset)->where('source_type', 'refurbish')->count() == 1, '完工重试只产生一笔应付');
        $assert($get('erp_asset', $asset)['total_cost'] == 4600, '完工重试不重复加成本');
        $page = $finance->payablePartyItems($vendor, ['source_type' => 'refurbish', 'purchase_order_id' => $p['source_id']]);
        $row = $page['data'][0] ?? [];
        $assert(($row['id'] ?? 0) == $asset && ($row['allocated_remain'] ?? 0) == 100, '财务付款页可查到设备及待付金额');
        $finance->confirmPaymentInTransaction((int)$p['id'], 40, ['capital_account_id' => $account, 'request_id' => $tag.$result.'P1']);
        $assert($get('erp_payable', (int)$p['id'])['status'] === 'partial', '支持先付40');
        $page = $finance->payablePartyItems($vendor, ['source_type' => 'refurbish', 'purchase_order_id' => $p['source_id']]);
        $row = $page['data'][0] ?? [];
        $assert(($row['allocated_paid'] ?? 0) == 40 && ($row['allocated_remain'] ?? 0) == 60, '财务页已付40待付60');
        $finance->confirmPaymentInTransaction((int)$p['id'], 60, ['capital_account_id' => $account, 'request_id' => $tag.$result.'P2']);
        $assert($get('erp_payable', (int)$p['id'])['status'] === 'settled', '付清后已结清');
        $assert($get('erp_capital_account', $account)['balance'] == $balanceBeforePayment - 100, '实际支付合计扣100');
        $assert($get('erp_asset', $asset)['total_cost'] == 4600, '付款不会再次增加成本');
        $assert(Db::name('erp_receivable')->where('site_id', $site)->where('asset_id', $asset)->count() == 0, '本店维修支出不凭空生成本店应收');
        $page = $finance->payablePartyItems($vendor, ['source_type' => 'refurbish', 'purchase_order_id' => $p['source_id']]);
        $assert(count($page['data'][0]['settlements'] ?? []) === 2, '付款历史展示两次支付');
        $reject(static fn() => $finance->confirmPaymentInTransaction((int)$p['id'], 100, ['capital_account_id' => $account]), '只能付款');
        $presentation = new ReflectionMethod($stock, 'enrichAssetAccountLedgers');
        $presentation->setAccessible(true);
        $presented = $presentation->invoke($stock, [
            ['biz_type'=>'internal_adjust','source_type'=>'internal_adjust','source_id'=>1,'amount'=>100,'direction'=>'increase'],
            ['biz_type'=>'supplier_adjust','source_type'=>'purchase_adjust','source_id'=>1,'amount'=>100,'direction'=>'increase'],
            ['biz_type'=>'unknown','source_type'=>'unknown','source_id'=>1,'amount'=>100,'direction'=>'increase'],
        ], $get('erp_asset', $asset));
        $assert($presented[0]['settlement_methods'] === [] && $presented[0]['business_state_text'] === '仅修正成本，不产生付款', '内部调整不冒充付款');
        $assert($presented[1]['settlement_methods'] === [] && $presented[1]['biz_type_text'] === '回收／采购调价', '采购调价事件不冒充实际付款');
        $assert($presented[2]['settlement_methods'] === [], '未知账务不借用采购付款状态');
    }
    $freeAsset = $insert('erp_asset', ['asset_no' => $tag.'free', 'imei' => $tag.'free', 'model' => 'TEST免费整备',
        'purchase_cost' => 4500, 'total_cost' => 4500, 'status' => 'in_stock', 'refurbish_status' => 'pending', 'sale_target' => 'peer']);
    $freeInput = ['result' => 'success', 'request_id' => $tag.'free', 'refurbish_items' => [], 'remark' => '本次无费用'];
    $stock->completeRefurbish($freeAsset, $freeInput);
    $stock->completeRefurbish($freeAsset, $freeInput);
    $assert($get('erp_asset', $freeAsset)['refurbish_status'] === 'done', '空费用列表允许免费完工');
    $assert($get('erp_asset', $freeAsset)['total_cost'] == 4500, '免费完工成本不变');
    $assert(Db::name('erp_payable')->where('asset_id', $freeAsset)->count() == 0, '免费完工不造应付');
    $assert(Db::name('erp_asset_ledger')->where('asset_id', $freeAsset)->where('action', 'refurbish_complete')->count() == 1, '免费完工重试不重复记账');
} catch (Throwable $e) { $error = $e; }
finally { Db::rollback(); }
foreach ($created as [$table, $id]) $assert(Db::name($table)->where('id', $id)->count() == 0, '测试数据已回滚：'.$table);
if (isset($account)) $assert(Db::name('erp_money_ledger')->where('capital_account_id', $account)->count() == 0, '测试资金流水已回滚');
if ($error) { fwrite(STDERR, $error->getMessage()."\n".$error->getTraceAsString()."\n"); exit(1); }
echo "PASS repair payable/payment: {$assertions} assertions; all fixtures rolled back; no external payment/notifications.\n";
