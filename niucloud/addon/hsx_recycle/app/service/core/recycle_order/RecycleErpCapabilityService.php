<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use core\exception\CommonException;
use think\facade\Log;

/** 站点配置用于新业务提示；付款安全边界必须使用订单/设备实际责任。 */
class RecycleErpCapabilityService
{
    public const ERP_ADDON = 'hsx_erp';
    public const ERP_PAYABLE_PATH = '/site/hsx_erp/payable';
    public const ERP_PAYMENT_MANAGED_MESSAGE = '当前站点财务已由 ERP 接管，回收插件禁止本地打款，请到“二手机 ERP - 应付款”完成付款';

    public function isEnabled(int $siteId): bool
    {
        if ($siteId <= 0) return false;
        $config = (new RecycleErpIntegrationService())->get($siteId);
        return $config['mode'] === 'self_erp' && $config['installed'];
    }

    public function isPaymentManaged(int $siteId, int $orderId = 0, array $deviceIds = []): bool
    {
        if ($orderId <= 0 && $deviceIds === []) return $this->isEnabled($siteId);
        $result = (new RecyclePaymentOwnershipService())->inspect($siteId, $orderId, $deviceIds);
        if (in_array($result['owner'], ['unknown', 'mixed'], true)) throw new CommonException($result['message']);
        return $result['owner'] === 'self_erp';
    }

    /**
     * 本地打款的后端最终闸门。
     *
     * 页面按钮和控制器分流只用于改善交互，不能作为财务安全边界。旧版移动端、
     * 历史接口或内部服务直接调用最终都会经过此处。接管状态无法确认时采用
     * fail-closed，宁可暂停本次操作，也不能冒险在回收插件和 ERP 重复记账、付款。
     */
    public function assertLocalPaymentAllowed(int $siteId, int $orderId = 0, array $deviceIds = []): void
    {
        if ($siteId <= 0) {
            throw new CommonException('无法确认当前站点，已为避免重复打款暂停本次操作');
        }

        try {
            (new RecyclePaymentOwnershipService())->claim($siteId, $orderId, $deviceIds, 'local');
        } catch (\Throwable $e) {
            Log::error('回收插件无法确认ERP财务接管状态，本地打款已安全拦截', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            if ($e instanceof CommonException) throw $e;
            throw new CommonException('无法确认 ERP 财务接管状态，已为避免重复打款暂停本次操作，请稍后重试');
        }
    }

    /**
     * 校验 ERP 入库位置，并以 ERP 主数据中的名称为准。
     * 通过事件契约保持回收插件可独立安装。
     */
    public function validateInboundPlacement(int $siteId, int $warehouseId, int $locationId, string $expectedWarehouseType = '', bool $existingErpResponsibility = false): array
    {
        if (!$existingErpResponsibility && !$this->isEnabled($siteId)) return [];
        if ($warehouseId <= 0 || $locationId <= 0) {
            throw new CommonException('ERP 联动已开启，请选择入库仓库和具体库位');
        }
        $warehouses = $this->warehouseOptions($siteId, $expectedWarehouseType);
        foreach ($warehouses as $warehouse) {
            if ((int)($warehouse['id'] ?? 0) !== $warehouseId) continue;
            foreach ((array)($warehouse['locations'] ?? []) as $location) {
                if ((int)($location['id'] ?? 0) !== $locationId) continue;
                return [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => (string)($warehouse['name'] ?? ''),
                    'warehouse_type' => (string)($warehouse['warehouse_type'] ?? ''),
                    'ownership_type' => (string)($warehouse['ownership_type'] ?? ''),
                    'location_id' => $locationId,
                    'location_name' => (string)($location['name'] ?? ''),
                ];
            }
        }
        throw new CommonException($expectedWarehouseType === 'consignment'
            ? '客户代卖设备只能进入启用中的代卖仓及其库位'
            : '所选ERP仓库或库位不存在、已停用或类型不匹配');
    }

    /** 历史异常数据手动重同步时，回退到默认仓库的首个可用库位。 */
    public function defaultInboundPlacement(int $siteId, string $warehouseType = ''): array
    {
        if (!(new RecycleErpIntegrationService())->isInstalled($siteId)) return [];
        $warehouses = $this->warehouseOptions($siteId, $warehouseType);
        if (empty($warehouses)) return [];
        $warehouse = null;
        foreach ($warehouses as $item) {
            if ((int)($item['is_default'] ?? 0) === 1 && !empty($item['locations'])) {
                $warehouse = $item;
                break;
            }
        }
        if ($warehouse === null) {
            foreach ($warehouses as $item) {
                if (!empty($item['locations'])) {
                    $warehouse = $item;
                    break;
                }
            }
        }
        if ($warehouse === null) return [];
        $location = array_values($warehouse['locations'])[0];
        return [
            'target_warehouse_id' => (int)$warehouse['id'],
            'target_location_id' => (int)$location['id'],
            'target_warehouse_name' => (string)($warehouse['name'] ?? ''),
            'target_location_name' => (string)($location['name'] ?? ''),
        ];
    }

    public function warehouseOptions(int $siteId, string $warehouseType = ''): array
    {
        if (!(new RecycleErpIntegrationService())->isInstalled($siteId)) return [];
        $eventId = 'recycle-warehouse-options-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $responses = (array)event('ErpWarehouseOptionsRequested', [
            'event_id' => $eventId,
            'event_name' => 'erp.warehouse.options_requested.v1',
            'event_version' => 1,
            'site_id' => $siteId,
            'source_plugin' => 'hsx_recycle',
            'occurred_at' => time(),
            'warehouse_type' => $warehouseType,
            'ownership_type' => $warehouseType === 'consignment' ? 'consigned' : '',
        ]);
        foreach ($responses as $response) {
            if (is_array($response) && (string)($response['consumer'] ?? '') === 'hsx_erp') {
                return array_values((array)($response['list'] ?? []));
            }
        }
        throw new CommonException('ERP仓库服务未响应，请检查插件安装和事件缓存');
    }

    public function paymentCapability(int $siteId, int $orderId = 0, array $deviceIds = []): array
    {
        $config = (new RecycleErpIntegrationService())->get($siteId);
        $result = $orderId > 0 || $deviceIds !== []
            ? (new RecyclePaymentOwnershipService())->inspect($siteId, $orderId, $deviceIds)
            : ['owner' => $config['mode'], 'message' => $config['message'], 'devices' => []];
        $owner = $result['owner'];
        $managed = $owner === 'self_erp';
        return [
            'erp_connected' => (bool)$config['installed'],
            'payment_managed_by_erp' => $managed,
            'payment_owner' => $owner,
            // 无具体业务的能力查询只作展示，不授予付款许可。
            'local_allowed' => $owner === 'local' && ($orderId > 0 || $deviceIds !== []),
            'payment_path' => $managed ? self::ERP_PAYABLE_PATH : '',
            'message' => $result['message'],
            'devices' => array_values($result['devices']),
        ];
    }
}
