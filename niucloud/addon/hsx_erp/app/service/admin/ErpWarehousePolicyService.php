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
                ->field('id,warehouse_name,warehouse_type,ownership_type,need_photo,need_pricing,allow_direct_sale,allow_transfer,default_sale_target,status')
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
            $row['can_warehouse_action'] = $policy['can_warehouse_action'];
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
        // 代卖设备不能做普通调拨，但允许从统一入口发起“买断转自有”。
        $canWarehouseAction = $inStock && !$refurbishBlocking
            && ($canTransfer || $warehouseType === 'consignment' || (string)($warehouse['ownership_type'] ?? '') === 'consigned');
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
            'can_warehouse_action' => $canWarehouseAction ? 1 : 0,
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

    /**
     * 判断一台设备从来源仓到目标仓应执行的业务动作。
     *
     * 调拨只改变保管位置，绝不能隐式改变物权。代卖转自有会形成新的采购与
     * 设备级应付，因此返回 buyout，由独立事务处理。所有端统一消费本结论。
     *
     * @return array{action:string,allowed:int,label:string,reason:string,missing_fields:array,missing_labels:array,requires_finance:int}
     */
    public function transferDecision(array $asset, ?array $sourceWarehouse, array $targetWarehouse): array
    {
        $sourceType = (string)($sourceWarehouse['warehouse_type'] ?? '');
        $targetType = (string)($targetWarehouse['warehouse_type'] ?? '');
        $sourceOwnership = $this->assetOwnership($asset, $sourceWarehouse);
        $targetOwnership = $targetType === 'consignment'
            ? 'consigned'
            : (string)($targetWarehouse['ownership_type'] ?? 'owned');

        if ((int)($targetWarehouse['status'] ?? 0) !== 1) {
            return $this->transferResult('blocked', false, '不可调入', '目标仓库已停用', [], [], false);
        }
        if ($sourceOwnership !== 'consigned' && $targetOwnership === 'consigned') {
            return $this->transferResult(
                'blocked', false, '禁止调入代卖仓',
                '自有设备不能通过库存调拨变成客户物权；如需委托代卖，请使用独立的委托业务',
                [], [], false
            );
        }
        if ($sourceOwnership !== 'consigned' && (int)($sourceWarehouse['allow_transfer'] ?? 0) !== 1) {
            return $this->transferResult(
                'blocked', false, '不可调拨',
                '当前来源仓库未开启调拨权限，请先调整仓库规则或使用对应业务流程',
                [], [], false
            );
        }
        if ($sourceOwnership === 'consigned' && $targetOwnership !== 'consigned') {
            if (!in_array($targetType, ['owned', 'peer'], true)) {
                return $this->transferResult(
                    'blocked', false, '不可调入',
                    '代卖设备只能在确认回收价后转入二手机仓或同行仓；异常处理请使用设备异常流程',
                    [], [], false
                );
            }
            return $this->transferResult(
                'buyout', true, '转为自有',
                '该设备属于客户，转入自有仓前需要确认回收价并生成设备级采购应付',
                [], [], true
            );
        }
        if ($sourceOwnership === 'consigned' && $targetOwnership === 'consigned') {
            if ((int)($sourceWarehouse['allow_transfer'] ?? 0) !== 1) {
                return $this->transferResult('blocked', false, '不可调拨', '当前代卖仓未开启同物权位置调拨', [], [], false);
            }
            return $this->transferResult('transfer', true, '普通调拨', '仅改变代卖设备保管位置，物权主体保持不变', [], [], false);
        }

        if ($sourceType === 'peer' && $targetType === 'owned') {
            // 同行仓追求快速入库，通常不会强制维护商城资料；转入二手机仓时
            // 则必须具备最基本的销售能力。这里按业务硬约束图片和零售价，
            // 不能仅依赖目标仓是否开启商城，否则配置变化会造成漏判。
            $missingFields = [];
            $missingLabels = [];
            if (!$this->hasImages($asset['image_urls'] ?? '')) {
                $missingFields[] = 'image';
                $missingLabels[] = '图片';
            }
            if ((float)($asset['retail_price'] ?? 0) <= 0) {
                $missingFields[] = 'retail_price';
                $missingLabels[] = '零售价';
            }
            $projected = array_merge($asset, [
                'warehouse_id' => (int)($targetWarehouse['id'] ?? 0),
                'warehouse_name' => (string)($targetWarehouse['warehouse_name'] ?? ''),
            ]);
            $policy = $this->evaluate($projected, $targetWarehouse);
            foreach ((array)($policy['missing_fields'] ?? []) as $index => $field) {
                if (in_array($field, $missingFields, true)) continue;
                $missingFields[] = $field;
                $missingLabels[] = (string)(($policy['missing_labels'] ?? [])[$index] ?? $field);
            }
            if ($missingFields !== []) {
                return $this->transferResult(
                    'complete_profile', false, '先完善资料',
                    '同行仓转入二手机仓前，请先补齐：' . implode('、', $missingLabels),
                    $missingFields, $missingLabels, false
                );
            }
        }

        return $this->transferResult('transfer', true, '普通调拨', '物权不变，仅调整库存位置', [], [], false);
    }

    private function assetOwnership(array $asset, ?array $sourceWarehouse): string
    {
        $sourceConsigned = (string)($sourceWarehouse['ownership_type'] ?? '') === 'consigned'
            || (string)($sourceWarehouse['warehouse_type'] ?? '') === 'consignment';
        $ownership = trim((string)($asset['ownership_type'] ?? ''));
        // 新字段上线前，旧代卖设备会被数据库默认值填成 owned。来源仓仍是最可靠的兼容证据。
        if ($sourceConsigned && trim((string)($asset['ownership_source_type'] ?? '')) === '') return 'consigned';
        if (in_array($ownership, ['owned', 'consigned', 'pending'], true)) return $ownership;
        return $sourceConsigned ? 'consigned' : 'owned';
    }

    private function transferResult(
        string $action,
        bool $allowed,
        string $label,
        string $reason,
        array $missingFields,
        array $missingLabels,
        bool $requiresFinance
    ): array {
        return [
            'action' => $action,
            'allowed' => $allowed ? 1 : 0,
            'label' => $label,
            'reason' => $reason,
            'missing_fields' => array_values($missingFields),
            'missing_labels' => array_values($missingLabels),
            'requires_finance' => $requiresFinance ? 1 : 0,
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
