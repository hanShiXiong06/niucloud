<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};
$service = (string)file_get_contents($root . '/app/service/admin/ErpStocktakeService.php');
$route = (string)file_get_contents($root . '/app/adminapi/route/route.php');
$sql = (string)file_get_contents($root . '/sql/install.sql');
$dict = (string)file_get_contents($root . '/app/dict/ErpDict.php');
$menu = (string)file_get_contents($root . '/app/dict/menu/site.php');
$mobileMenu = (string)file_get_contents($root . '/app/dict/adminapp/app.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpStocktake.php');
$pages = (string)file_get_contents(dirname($root, 3) . '/site-uniapp/src/pages.json');

$assert(str_contains($sql, 'erp_stocktake') && str_contains($sql, 'erp_stocktake_item'), '盘点任务与明细必须进入安装结构');
$assert(str_contains($service, "'result' => 'missing'") && str_contains($service, "'result' => 'surplus'"), '盘点必须按设备识别盘亏和盘盈');
$assert(str_contains($service, 'ASSET_LOST') && str_contains($service, "'stocktake_location'"), '完成盘点必须写盘亏状态和库位修正流水');
$assert(!str_contains($service, 'ErpPayable') && !str_contains($service, 'ErpReceivable'), '库存盘点不得自动产生财务往来');
foreach (['stocktake/lists', 'stocktake/create', 'stocktake/:id/scan', 'stocktake/:id/complete'] as $uri) {
    $assert(str_contains($route, $uri), '缺少盘点接口：' . $uri);
}
$assert(str_contains($dict, '已盘亏') && str_contains($menu, '库存盘点') && str_contains($mobileMenu, '库存盘点'), '盘点字典和双端入口必须完整');
$assert(str_contains($controller, "success(['id' => \$id])"), '创建盘点必须在 data 中返回任务 ID');
$assert(str_contains($pages, 'pages/stocktake/list') && str_contains($pages, 'pages/stocktake/detail'), '移动端盘点页面必须注册到 pages.json');
echo "[PASS] ERP stocktake smoke test\n";
