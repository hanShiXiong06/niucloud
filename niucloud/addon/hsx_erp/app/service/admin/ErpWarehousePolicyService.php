<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpWarehouse;
use core\base\BaseAdminService;

/**
 * 仓库能力统一判断。
 *
 * 仓库配置是销售、调拨和商城准备规则的唯一事实来源。PC、移动端和服务端
 * 都消费这里返回的结论，避免各端根据仓库名称或设备状态自行猜测。
 */
class ErpWarehousePolicyService extends BaseAdminService
{
    private ?array $marketplaceProviders = null;

    public function decorate(array $rows): array
    {
        if ($rows === []) return [];
        $warehouseIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int)($row['warehouse_id'] ?? 0),
            $rows
        ))));
        $warehouseMap = [];
        if ($warehouseIds !== []) {
            $warehouses = ErpWarehouse::where('site_id', '=', $this->site_id)
                ->whereIn('id', $warehouseIds)
                ->field('id,warehouse_name,warehouse_type,need_photo,need_pricing,allow_direct_sale,allow_transfer,default_sale_target,status')
                ->select()->toArray();
            foreach ($warehouses as $warehouse) {
                $warehouseMap[(int)$warehouse['id']] = $warehouse;
            }
        }
        foreach ($rows as &$row) {
            $policy = $this->evaluate($row, $warehouseMap[(int)($row['warehouse_id'] ?? 0)] ?? null);
            // 兼容旧数据：历史状态可能仍是 need_photo/need_price，展示与操作必须以当前真实资料为准。
            if ((int)($policy['can_list_mall'] ?? 0) === 1 && (string)($row['listing_status'] ?? 'none') !== 'listed') {
                $row['listing_status'] = 'ready';
            }
            $row['warehouse_policy'] = $policy;
            $row['can_direct_sale'] = $policy['can_direct_sale'];
            $row['can_transfer'] = $policy['can_transfer'];
            $row['can_list_mall'] = $policy['can_list_mall'];
            $row['missing_listing_fields'] = $policy['missing_fields'];
        }
        unset($row);
        return $rows;
    }

    public function evaluate(array $asset, ?array $warehouse = null): array
    {
        $inStock = (string)($asset['status'] ?? '') === ErpDict::ASSET_IN_STOCK;
        $warehouseActive = $warehouse !== null && (int)($warehouse['status'] ?? 0) === 1;
        $warehouseType = (string)($warehouse['warehouse_type'] ?? '');
        $allowDirectSale = $warehouseActive && (int)($warehouse['allow_direct_sale'] ?? 0) === 1;
        $allowTransfer = $warehouseActive && (int)($warehouse['allow_transfer'] ?? 0) === 1;
        $allowMall = $warehouseActive && (string)($warehouse['default_sale_target'] ?? 'unset') === 'mall';
        $needPhoto = $allowMall && (int)($warehouse['need_photo'] ?? 0) === 1;
        $needPricing = $allowMall && (int)($warehouse['need_pricing'] ?? 0) === 1;
        $refurbishStatus = (string)($asset['refurbish_status'] ?? 'none');
        $refurbishBlocking = in_array($refurbishStatus, ['pending', 'processing', 'failed'], true);

        $missingFields = [];
        $missingLabels = [];
        if ($allowMall) {
            if ((int)($asset['catalog_product_id'] ?? 0) <= 0) {
                $missingFields[] = 'catalog_product';
                $missingLabels[] = '商品型号';
            }
            if (trim((string)($asset['spec'] ?? '')) === '') {
                $missingFields[] = 'spec';
                $missingLabels[] = '规格';
            }
            if ($needPhoto && !$this->hasImages($asset['image_urls'] ?? '')) {
                $missingFields[] = 'image';
                $missingLabels[] = '图片';
            }
            if ($needPricing && (float)($asset['retail_price'] ?? 0) <= 0) {
                $missingFields[] = 'retail_price';
                $missingLabels[] = '零售价';
            }
        }

        $canDirectSale = $inStock && $allowDirectSale && !$refurbishBlocking;
        $canTransfer = $inStock && $allowTransfer && !$refurbishBlocking;
        $canPrepareMall = $inStock && $allowMall && !$refurbishBlocking;
        $canListMall = $canPrepareMall && $missingFields === [];
        $marketplaceAvailable = $this->marketplaceAvailable();

        [$actionKey, $actionLabel, $actionReason] = $this->primaryAction(
            $asset,
            $warehouseActive,
            $refurbishStatus,
            $missingLabels,
            $canDirectSale,
            $canTransfer,
            $canPrepareMall,
            $canListMall,
            $marketplaceAvailable
        );

        return [
            'warehouse_id' => (int)($warehouse['id'] ?? $asset['warehouse_id'] ?? 0),
            'warehouse_name' => (string)($warehouse['warehouse_name'] ?? $asset['warehouse_name'] ?? ''),
            'warehouse_type' => $warehouseType,
            'warehouse_active' => $warehouseActive ? 1 : 0,
            'allow_direct_sale' => $allowDirectSale ? 1 : 0,
            'allow_transfer' => $allowTransfer ? 1 : 0,
            'allow_mall' => $allowMall ? 1 : 0,
            'need_photo' => $needPhoto ? 1 : 0,
            'need_pricing' => $needPricing ? 1 : 0,
            'can_direct_sale' => $canDirectSale ? 1 : 0,
            'can_transfer' => $canTransfer ? 1 : 0,
            'can_prepare_mall' => $canPrepareMall ? 1 : 0,
            'can_list_mall' => $canListMall ? 1 : 0,
            'marketplace_available' => $marketplaceAvailable ? 1 : 0,
            'missing_fields' => $missingFields,
            'missing_labels' => $missingLabels,
            'primary_action' => $actionKey,
            'primary_action_label' => $actionLabel,
            'primary_action_reason' => $actionReason,
        ];
    }

    private function primaryAction(
        array $asset,
        bool $warehouseActive,
        string $refurbishStatus,
        array $missingLabels,
        bool $canDirectSale,
        bool $canTransfer,
        bool $canPrepareMall,
        bool $canListMall,
        bool $marketplaceAvailable
    ): array {
        if ((string)($asset['status'] ?? '') !== ErpDict::ASSET_IN_STOCK) {
            return ['view', '查看档案', '设备已退出当前库存'];
        }
        if (!$warehouseActive) {
            return ['resolve_warehouse', '处理仓库', '当前仓库已停用或不存在'];
        }
        if ($refurbishStatus === 'pending') {
            return ['start_refurbish', '开始整备', '设备待整备，完成前不能销售'];
        }
        if ($refurbishStatus === 'processing') {
            return ['complete_refurbish', '登记完工', '设备正在整备，完成后重新进入销售流程'];
        }
        if ($refurbishStatus === 'failed') {
            return ['resolve_refurbish', '处理整备异常', '整备异常，需要登记结果或转入异常仓'];
        }
        if ($canPrepareMall && $missingLabels !== []) {
            return ['complete_listing', '完善商品资料', '缺少' . implode('、', $missingLabels)];
        }
        if ($canListMall && (string)($asset['listing_status'] ?? 'none') !== 'listed' && $marketplaceAvailable) {
            return ['publish_listing', '上架商城', '商品资料已完整，点击后由 ERP 直接上架商城'];
        }
        if ($canDirectSale) {
            return ['direct_sale', '销售出库', '当前设备允许直接销售'];
        }
        if ($canListMall && (string)($asset['listing_status'] ?? 'none') === 'listed') {
            return ['listing_published', '商城已上架', '商品已在商城展示，可查看设备档案'];
        }
        if ($canListMall && !$marketplaceAvailable) {
            return ['listing_ready', '资料完整', '当前未安装商城，ERP 保留完整商品资料，可直接销售或调拨'];
        }
        if ($canTransfer) {
            return ['transfer', '调拨处理', '当前仓库不可直接销售，可调拨至可售仓库'];
        }
        return ['view', '查看原因', '当前仓库规则不允许直接销售或调拨'];
    }

    private function marketplaceAvailable(): bool
    {
        if ($this->marketplaceProviders === null) {
            $providers = [];
            foreach ((array)event('HsxErpMarketplaceProviders', ['site_id' => $this->site_id]) as $result) {
                foreach ((array)($result['providers'] ?? []) as $provider) {
                    if (!is_array($provider) || empty($provider['key']) || (int)($provider['enabled'] ?? 1) !== 1) continue;
                    if ((int)($provider['supports_direct_listing'] ?? 0) !== 1) continue;
                    $providers[(string)$provider['key']] = $provider;
                }
            }
            $this->marketplaceProviders = array_values($providers);
        }
        return $this->marketplaceProviders !== [];
    }

    private function hasImages(mixed $value): bool
    {
        if (is_array($value)) return count(array_filter($value, static fn($item): bool => trim((string)$item) !== '')) > 0;
        $text = trim((string)$value);
        if ($text === '') return false;
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return count(array_filter($decoded, static fn($item): bool => trim((string)$item) !== '')) > 0;
        return count(array_filter(preg_split('/[,\r\n]+/', $text) ?: [])) > 0;
    }
}
