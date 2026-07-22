<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

/** 向外部插件暴露可入库仓库/库位的最小只读视图。 */
class ErpWarehouseOptionService extends ErpExternalContractService
{
    public const EVENT_NAME = 'ErpWarehouseOptionsRequested';
    public const CONTRACT_NAME = 'erp.warehouse.options_requested.v1';
    public const CONTRACT_VERSION = 1;

    public function consume(array $event): array
    {
        $envelope = $this->normalizeEnvelope($event, self::CONTRACT_NAME, self::CONTRACT_VERSION);
        $warehouseType = trim((string)($event['warehouse_type'] ?? ''));
        $ownershipType = trim((string)($event['ownership_type'] ?? ''));
        $list = array_values(array_filter((new ErpWarehouseService())->getOptions(), static function (array $warehouse) use ($warehouseType, $ownershipType): bool {
            if ($warehouseType !== '' && (string)($warehouse['warehouse_type'] ?? '') !== $warehouseType) return false;
            if ($ownershipType !== '' && (string)($warehouse['ownership_type'] ?? '') !== $ownershipType) return false;
            return !empty($warehouse['locations']);
        }));

        return [
            'consumer' => 'hsx_erp',
            'event_id' => (string)$envelope['event_id'],
            'status' => 'processed',
            'list' => array_map(static function (array $warehouse): array {
                return [
                    'id' => (int)$warehouse['id'],
                    'name' => (string)$warehouse['warehouse_name'],
                    'warehouse_type' => (string)($warehouse['warehouse_type'] ?? 'owned'),
                    'ownership_type' => (string)($warehouse['ownership_type'] ?? 'owned'),
                    'default_sale_target' => (string)($warehouse['default_sale_target'] ?? 'unset'),
                    'is_default' => (int)($warehouse['is_default'] ?? 0),
                    'locations' => array_map(static fn(array $location): array => [
                        'id' => (int)$location['id'],
                        'name' => (string)$location['location_name'],
                    ], array_values((array)$warehouse['locations'])),
                ];
            }, $list),
        ];
    }
}
