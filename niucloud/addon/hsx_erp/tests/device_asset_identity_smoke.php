<?php
declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$message}\n"); exit(1); }
};

$root = dirname(__DIR__);
$deviceServicePath = dirname($root) . '/hsx_device_asset/app/service/core/DeviceAssetErpEventService.php';
$assert(is_file($deviceServicePath), '设备资产中台ERP事件服务不存在');
$source = (string)file_get_contents($deviceServicePath);
$assert(str_contains($source, 'erp_source_uid') && str_contains($source, 'erpSourceUid'), '跨插件资产必须保存稳定来源UID');
$assert(str_contains($source, 'crc32($uid') && str_contains($source, "'asset_no'"), 'ERP直接入库设备必须按资产号生成稳定占位键');
$assert(!str_contains($source, 'return -(int)$snapshot[\'asset_id\'];'), '不得继续用可重置的ERP自增ID作为跨插件唯一键');
$assert(str_contains($source, 'findExistingAsset') && str_contains($source, 'storedAssetNo'), '升级时必须兼容旧映射并拒绝资产号不一致的错误重绑');

$stock = (string)file_get_contents($root . '/app/service/admin/ErpStockService.php');
$assert(str_contains($stock, 'appendListingSyncState') && str_contains($stock, "'last_error'"), '库存端必须向业务用户展示拍照定价同步状态和失败原因');

echo "[PASS] ERP/device-asset stable identity smoke test\n";
