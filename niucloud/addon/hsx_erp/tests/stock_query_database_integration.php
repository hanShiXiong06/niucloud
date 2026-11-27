<?php
declare(strict_types=1);

// 只操作本地库的隔离样本，所有新增数据均回滚，不触发外部动作。
if (getenv('HSX_ERP_STOCK_ROLLBACK_TEST') !== '1') {
    fwrite(STDERR, "Set HSX_ERP_STOCK_ROLLBACK_TEST=1 to run.\n"); exit(2);
}
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_erp\app\service\admin\ErpStockService;
use addon\hsx_erp\app\service\admin\ErpTurnoverService;
use think\facade\Db;

(new think\App())->initialize();
set_exception_handler(static function(Throwable $e):void { fwrite(STDERR,$e->getMessage()."\n"); exit(1); });
if (!in_array(config('database.connections.mysql.hostname'), ['localhost', '127.0.0.1'], true)) throw new RuntimeException('仅允许本地库');
$engine = Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [config('database.connections.mysql.prefix').'erp_asset']);
if (strtoupper((string)($engine[0]['ENGINE'] ?? '')) !== 'INNODB') throw new RuntimeException('样本表不支持回滚');
$site = 100000;
request()->siteId($site); request()->uid(1); request()->username('库存排序回滚测试'); request()->appType('adminapi');
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void { $count++; if (!$ok) throw new RuntimeException($message); };
$tag = 'QUERY'.date('His').bin2hex(random_bytes(3));
$ids = [];
Db::startTrans();
try {
    $service = ErpStockService::forSite($site, 1, '库存排序回滚测试');
    $turnover = new ErpTurnoverService();
    $rules = $turnover->rules();
    $ages = [1, (int)($rules['attention_days'] ?? 7) + 2, (int)($rules['warning_days'] ?? 15) + 2, (int)($rules['critical_days'] ?? 30) + 2];
    $fixtures = [];
    foreach (array_merge($ages, [$ages[3], $ages[3]]) as $i => $age) {
        $time = time() - $age * 86400 - 60;
        $row = ['site_id'=>$site, 'asset_no'=>$tag.$i, 'imei'=>$tag.$i, 'model'=>$tag, 'spec'=>'256G',
            'status'=>$i===5 ? 'sold' : 'in_stock', 'stock_in_at'=>$i===4 ? 0 : $time, 'create_at'=>$time,
            'purchase_cost'=>4500, 'total_cost'=>4600, 'retail_price'=>5000, 'estimate_sale_price'=>4000,
            'warehouse_name'=>'排序测试仓', 'listing_status'=>'none', 'sale_target'=>'peer'];
        $row['id'] = (int)Db::name('erp_asset')->insertGetId($row);
        $ids[] = $row['id']; $fixtures[] = $row;
    }
    foreach (['', 'healthy', 'attention', 'warning', 'critical', 'risk'] as $level) {
        $expected = array_values(array_filter($fixtures, static function ($row) use ($level, $turnover, $rules): bool {
            if ($level === '') return true;
            $meta = $turnover->meta($row['stock_in_at'] ?: $row['create_at'], $row['status'], $rules);
            return $level === 'risk' ? in_array($meta['turnover_level'], ['warning','critical'], true) : $meta['turnover_level'] === $level;
        }));
        usort($expected, static fn($a,$b) => $level === '' ? $b['id'] <=> $a['id'] : ((($a['stock_in_at'] ?: $a['create_at']) <=> ($b['stock_in_at'] ?: $b['create_at'])) ?: ($a['id'] <=> $b['id'])));
        $page = $service->getPage(['keyword'=>$tag, 'turnover_level'=>$level, 'limit'=>100]);
        $assert(array_map('intval', array_column($page['data'], 'id')) === array_column($expected,'id'), '等级筛选与排序：'.$level);
        $assert((int)$page['total'] === count($expected), '等级总数：'.$level);
        if ($level !== '') foreach ($page['data'] as $row) $assert($row['status'] === 'in_stock', '周转预警不能包含已售设备');
    }
    $page = $service->getPage(['keyword'=>$tag, 'turnover_level'=>'critical', 'page'=>2, 'limit'=>1]);
    $assert(count($page['data'])===1 && (int)$page['data'][0]['id']===$ids[4], '相同库龄以ID稳定分页，入库时间为空回退创建时间');
    $page = $service->getPage(['keyword'=>$tag, 'status'=>'in_stock', 'min_price'=>4900, 'max_price'=>5100]);
    $assert((int)$page['total']===5, '价格范围使用零售价且叠加在库状态');
    $page = $service->getPage(['keyword'=>$tag, 'min_price'=>3900, 'max_price'=>4100]);
    $assert((int)$page['total']===0, '不能用预计售价绕过已设置的零售价筛选');
    $page = $service->getPage(['keyword'=>$tag.'4', 'stock_age_min'=>$ages[3]-1, 'stock_age_max'=>$ages[3]+1]);
    $assert((int)$page['total']===1 && (int)$page['data'][0]['id']===$ids[4], '串号和库龄组合检索兼容无入库时间');
    $page = ErpStockService::forSite($site+9876, 1)->getPage(['keyword'=>$tag]);
    $assert((int)$page['total']===0, '站点隔离');
} finally {
    Db::rollback();
}
$assert(Db::name('erp_asset')->whereIn('id',$ids)->count()===0, '隔离数据完全回滚');
echo "PASS stock query database integration: {$count} assertions; fixtures rolled back.\n";
