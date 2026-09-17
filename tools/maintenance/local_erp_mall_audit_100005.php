<?php
declare(strict_types=1);

// 本地 100005 的只读检查；不调用导入、确认、付款、上架或事件重试。
if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__, 2);
if (realpath($root) !== '/Users/a123/Documents/1-work/niucloud/niucloud') throw new RuntimeException('仅限指定本地工作区');
require $root . '/niucloud/vendor/autoload.php';
(new think\App())->initialize();
if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)
    || config('database.connections.mysql.database') !== 'saas_' || config('database.connections.mysql.prefix') !== 'ns_') {
    throw new RuntimeException('不是已授权的本地数据库');
}
request()->siteId(100005); request()->uid(1); request()->username('本地只读检查'); request()->appType('adminapi');
think\facade\Db::query('SELECT 1');
$pdo = think\facade\Db::connect()->getPdo();
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
$query = static function (string $sql, array $params = []) use ($pdo): array {
    $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
};
$count = static fn(string $sql): int => (int)array_values($query($sql)[0])[0];
$call = static function (callable $fn): array {
    try {
        $value = $fn();
        return ['ok' => true, 'total' => $value['total'] ?? count($value['data'] ?? []), 'returned' => count($value['data'] ?? []), 'summary' => $value['summary'] ?? []];
    } catch (Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
};
try {
    $report = ['site_id' => 100005, 'database' => 'local saas_ / ns_', 'read_only' => true, 'checked_at' => date(DATE_ATOM)];
    foreach (['phone_shop_goods', 'phone_shop_goods_sku', 'phone_shop_order', 'erp_asset', 'erp_purchase_order', 'erp_payable', 'erp_sale_order', 'erp_receivable', 'erp_settlement'] as $table) {
        $report['counts'][$table] = $count("SELECT COUNT(*) FROM ns_$table WHERE site_id = 100005");
    }
    $report['goods_states'] = $query('SELECT delete_time > 0 AS deleted, status, sale_status, is_proxy, source, COUNT(*) AS n FROM ns_phone_shop_goods WHERE site_id = 100005 GROUP BY deleted, status, sale_status, is_proxy, source');
    $report['binding'] = $query('SELECT COUNT(*) AS linked_skus, COALESCE(SUM(a.id IS NULL),0) AS stale_links FROM ns_phone_shop_goods_sku s LEFT JOIN ns_erp_asset a ON a.id = s.erp_asset_id AND a.site_id = s.site_id WHERE s.site_id = 100005 AND s.erp_asset_id > 0')[0];
    $report['active_goods_without_sku'] = $count('SELECT COUNT(*) FROM ns_phone_shop_goods g WHERE g.site_id = 100005 AND g.delete_time = 0 AND NOT EXISTS (SELECT 1 FROM ns_phone_shop_goods_sku s WHERE s.site_id = g.site_id AND s.goods_id = g.goods_id)');
    $base = "FROM ns_phone_shop_goods_sku s INNER JOIN ns_phone_shop_goods g ON s.goods_id = g.goods_id AND s.site_id = g.site_id WHERE s.site_id = 100005 AND g.delete_time = 0 AND s.stock > 0 AND g.source IN ('','0','1','100005')";
    $rows = $query('SELECT s.sku_id,s.goods_id,s.sku_no,s.is_unique,s.stock,s.cost_price,s.erp_asset_id,s.device_snapshot,g.status,g.sale_status,g.source,g.is_proxy ' . $base);
    $stats = ['candidate_skus' => count($rows), 'available_single_stock' => 0, 'listed_available_single_stock' => 0, 'full_identity' => 0, 'six_digit_sku' => 0, 'missing_full_identity' => 0, 'non_positive_cost' => 0, 'identity_conflict' => 0, 'duplicate_identity_groups' => 0];
    $identities = [];
    foreach ($rows as $row) {
        $device = addon\phone_shop\app\service\core\order\ErpDeviceSnapshot::fromSku($row, $row);
        $identity = addon\hsx_erp\app\support\ErpMallDevicePolicy::identity($device);
        $full = $identity['imei'] !== '' || $identity['sn'] !== '';
        $stats[$full ? 'full_identity' : 'missing_full_identity']++;
        if (preg_match('/^\d{6}$/D', trim($row['sku_no']))) $stats['six_digit_sku']++;
        if ((float)$row['cost_price'] <= 0) $stats['non_positive_cost']++;
        if ($device['identity_conflict']) $stats['identity_conflict']++;
        if ($row['sale_status'] === 'available' && (int)$row['stock'] === 1) {
            $stats['available_single_stock']++;
            if ((int)$row['status'] === 1) $stats['listed_available_single_stock']++;
        }
        foreach ($identity as $kind => $value) if ($value !== '') $identities[$kind . ':' . $value][] = (int)$row['sku_id'];
    }
    $stats['duplicate_identity_groups'] = count(array_filter($identities, static fn(array $ids): bool => count($ids) > 1));
    $report['self_owned_stock'] = $stats;
    $recent = $query('SELECT s.sku_id,s.goods_id,s.sku_no,s.is_unique,s.stock,s.cost_price,s.price,s.erp_asset_id,s.device_snapshot,g.status,g.sale_status,g.source,g.is_proxy,g.create_time ' . $base . " AND g.status = 1 AND g.sale_status = 'available' AND s.stock = 1 ORDER BY g.create_time DESC,g.goods_id DESC,s.sku_id DESC LIMIT 100");
    $recentStats = ['count' => count($recent), 'full_identity' => 0, 'positive_cost' => 0, 'both_valid' => 0, 'six_digit' => 0];
    foreach ($recent as $row) {
        $device = addon\phone_shop\app\service\core\order\ErpDeviceSnapshot::fromSku($row, $row);
        $identity = addon\hsx_erp\app\support\ErpMallDevicePolicy::identity($device);
        $full = ($identity['imei'] !== '' || $identity['sn'] !== '') && !$device['identity_conflict'];
        $recentStats['full_identity'] += (int)$full;
        $recentStats['positive_cost'] += (int)((float)$row['cost_price'] > 0);
        $recentStats['both_valid'] += (int)($full && (float)$row['cost_price'] > 0);
        $recentStats['six_digit'] += (int)(bool)preg_match('/^\d{6}$/D', trim($row['sku_no']));
    }
    $report['recent_100_listed_stock'] = $recentStats;

    // 实际预览服务全量分页，只读取每项状态、原因，不调用 confirm。
    $service = addon\hsx_erp\app\service\admin\ErpMallInventoryService::forSite(100005, 1, '只读检查');
    $summary = []; $reasons = []; $samples = []; $seen = []; $total = 0;
    try {
        for ($page = 1; $page <= 1000; $page++) {
            $result = $service->preview(['page' => $page, 'limit' => 50, 'include_linked' => 1]);
            $total = (int)$result['total'];
            foreach ($result['data'] as $row) {
                if (isset($seen[$row['sku_id']])) throw new RuntimeException('分页返回重复 SKU');
                $seen[$row['sku_id']] = true;
                $state = $row['state']; $message = $row['message'];
                $summary[$state] = ($summary[$state] ?? 0) + 1;
                $reasons[$message] = ($reasons[$message] ?? 0) + 1;
                $samples[$message] ??= ['goods_id' => $row['goods_id'], 'sku_id' => $row['sku_id']];
            }
            if ($page * 50 >= $total) break;
        }
        if (count($seen) !== $total) throw new RuntimeException('预览分页未覆盖全部库存');
        $report['stock_preview'] = ['ok' => true, 'total' => $total, 'checked' => count($seen), 'states' => $summary, 'reasons' => $reasons, 'example_ids' => $samples];
    } catch (Throwable $e) { $report['stock_preview'] = ['ok' => false, 'checked' => count($seen), 'error' => $e->getMessage()]; }
    $report['sold_preview'] = $call(static fn(): array => $service->preview(['scope' => 'sold', 'limit' => 50]));
    $stock = addon\hsx_erp\app\service\admin\ErpStockService::forSite(100005, 1, '只读检查');
    $report['stock_list'] = $call(static fn(): array => $stock->getPage(['page' => 1, 'limit' => 15]));
    $finance = addon\hsx_erp\app\service\admin\ErpFinanceService::forSite(100005, 1, '只读检查');
    $report['payable_list'] = $call(static fn(): array => $finance->payablePage(['page' => 1, 'limit' => 15]));
    $report['receivable_list'] = $call(static fn(): array => $finance->receivablePage(['page' => 1, 'limit' => 15]));
    foreach (['erp_payable', 'erp_receivable'] as $table) {
        $report['finance'][$table] = $query("SELECT status,COUNT(*) AS n,SUM(amount) AS amount,SUM(settled_amount) AS settled_amount FROM ns_$table WHERE site_id = 100005 GROUP BY status");
    }
    $report['mall_origin_sales_without_order'] = $count("SELECT COUNT(*) FROM ns_erp_sale_order e WHERE e.site_id = 100005 AND e.origin_plugin = 'phone_shop' AND NOT EXISTS (SELECT 1 FROM ns_phone_shop_order o WHERE o.site_id = e.site_id AND CAST(o.order_id AS BINARY) = CAST(e.origin_id AS BINARY))");
    $report['asset_status'] = $query('SELECT status,COUNT(*) AS n FROM ns_erp_asset WHERE site_id = 100005 GROUP BY status');
    $report['warehouse'] = $query('SELECT id,status,allow_direct_sale,default_sale_target FROM ns_erp_warehouse WHERE site_id = 100005');
    foreach (['erp_inbox_event', 'erp_outbox_event'] as $table) $report['events'][$table] = $query("SELECT status,COUNT(*) AS n FROM ns_$table WHERE site_id = 100005 GROUP BY status");
    $report['marketplace'] = addon\hsx_erp\app\service\admin\ErpConfigService::forSite(100005)->getRules()['marketplace'];
    $pdo->rollBack();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL); exit(1);
}
