<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$service = (string)file_get_contents($root . '/app/service/admin/ErpWarehouseService.php');
$controller = (string)file_get_contents($root . '/app/adminapi/controller/ErpWarehouse.php');
$view = (string)file_get_contents($root . '/admin/views/erp/warehouse/list.vue');

$assert(str_contains($controller, "['manager_uid', 0]"), '仓库和库位接口必须接收负责人UID');
$assert(substr_count($controller, "['manager_uid', 0]") === 2, '仓库和库位接口都必须接收负责人UID');
$assert(str_contains($service, "resolve(\$managerUid, '仓库负责人')"), '仓库负责人必须校验为本站员工');
$assert(str_contains($service, "resolve(\$managerUid, '库位负责人')"), '库位负责人必须校验为本站员工');
$assert(str_contains($service, "'manager_source' =") === false, '负责人来源必须写入返回数据而非数据库条件');
$assert(str_contains($service, "'manager_source'] = \$locationManagerUid > 0 ? 'location'"), '库位必须区分独立负责人和继承仓库负责人');
$assert(str_contains($view, '不选择则继承仓库负责人'), '库位负责人继承规则必须在界面明确提示');
$assert(str_contains($view, '请选择仓库负责人'), '仓库保存前必须提示选择负责人');

echo "[PASS] ERP warehouse responsibility smoke test\n";
