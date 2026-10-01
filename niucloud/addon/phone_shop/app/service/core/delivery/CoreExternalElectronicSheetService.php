<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\delivery;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\delivery\Company;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\shop_address\ShopAddress;
use addon\phone_shop\app\service\core\delivery\electronic_sheet\ElectronicSheetProviderRegistry;
use app\model\sys\SysArea;
use core\exception\CommonException;

/** A waybill task is not a shipment. This service deliberately never changes order delivery state. */
class CoreExternalElectronicSheetService
{
    public static function packageKey(int $orderId, array $goodsIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $goodsIds)));
        sort($ids, SORT_NUMERIC);
        if ($orderId <= 0 || !$ids || min($ids) <= 0) throw new CommonException('请选择本次包裹中的商品');
        return $orderId . ':' . substr(hash('sha256', implode(',', $ids)), 0, 32);
    }

    public function execute(int $siteId, array $params): array
    {
        $operation = (string) ($params['operation'] ?? 'query');
        if (!in_array($operation, ['create', 'query', 'refresh', 'reprint', 'cancel'], true)) throw new CommonException('不支持的面单操作');
        $orderId = (int) ($params['order_id'] ?? 0);
        $ids = $params['order_goods_ids'] ?? [];
        if (!is_array($ids)) $ids = explode(',', (string) $ids);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $businessId = self::packageKey($orderId, $ids);
        if ($operation === 'create' && empty($params['_waybill_create_lock_held'])) {
            $config = (new CoreElectronicSheetService())->getElectronicSheetConfig($siteId);
            $providerKey = (string) ($params['provider_key'] ?? $config['interface_type'] ?? '');
            if ($providerKey !== ($config['interface_type'] ?? '')) throw new CommonException('面单服务商设置已变化，请重新打开窗口');
            return (new ElectronicSheetProviderRegistry())->withBusinessLock($siteId, $providerKey, $orderId, function () use ($siteId, $params) {
                $params['_waybill_create_lock_held'] = true;
                // Re-read order and goods only after acquiring the same mutex as shipment/cancellation.
                return $this->execute($siteId, $params);
            });
        }
        $order = (new Order())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->with(['order_goods'])->findOrEmpty()->toArray();
        if (!$order) throw new CommonException('订单不存在或不属于当前站点');
        $goods = array_values(array_filter($order['order_goods'] ?? [], fn($row) => in_array((int) $row['order_goods_id'], $ids, true)));
        if (count($goods) !== count($ids)) throw new CommonException('选中的商品不属于当前订单，请刷新后重试');
        $config = (new CoreElectronicSheetService())->getElectronicSheetConfig($siteId);
        $providerKey = (string) ($params['provider_key'] ?? $config['interface_type'] ?? '');
        if ($operation === 'create' && $providerKey !== ($config['interface_type'] ?? '')) throw new CommonException('面单服务商设置已变化，请重新打开窗口');
        $registry = new ElectronicSheetProviderRegistry();
        $identity = ['business_type' => 'phone_shop', 'business_id' => $businessId];
        $existing = $registry->execute($siteId, $providerKey, 'query', $identity);
        if ($operation === 'query') return $this->decorate($siteId, $providerKey, $existing);
        if ($operation !== 'create') {
            if (empty($existing['task_id'])) throw new CommonException('本包裹尚未申请电子面单');
            if ($operation === 'cancel' && array_filter($goods, fn($row) => !empty($row['delivery_id']))) throw new CommonException('本包裹已经确认发货，不能在此取消；请先联系快递公司核实实际交件情况');
            // The saved task owns its channel, even after the site's selected channel changes.
            $providerKey = (string)($existing['provider_key'] ?? $existing['provider_code'] ?? $providerKey);
            return $this->decorate($siteId, $providerKey, $registry->execute($siteId, $providerKey, $operation, $identity + ['task_id' => (int) $existing['task_id'], 'reason' => trim((string) ($params['reason'] ?? '')), 'confirm' => (int) ($params['confirm'] ?? 0)]));
        }
        // Reopening or double clicking retrieves the same durable task; never creates a second waybill.
        if (!empty($existing['task_id']) && !in_array($existing['state'] ?? '', ['cancelled', 'failed'], true)) return $this->decorate($siteId, $providerKey, $existing);
        if (($order['delivery_type'] ?? '') !== 'express' || !in_array((int) $order['status'], [OrderDict::WAIT_DELIVERY, OrderDict::WAIT_TAKE], true)) throw new CommonException('仅物流配送且待发货的商品可申请面单');
        foreach ($goods as $row) {
            if ((int) $row['status'] !== 1 || !empty($row['delivery_id'])) throw new CommonException('选中商品已发货或正在退款，请重新选择未发货商品');
            $extend = is_array($row['extend'] ?? null) ? $row['extend'] : (json_decode((string)($row['extend'] ?? ''), true) ?: []);
            if (!empty($extend['erp_return'])) throw new CommonException('选中商品已退回 ERP 库存，不能继续申请面单，请刷新后重新选择');
        }
        $sender = (new ShopAddress())->where([['site_id', '=', $siteId], ['is_delivery_address', '=', 1], ['is_default_delivery', '=', 1]])->findOrEmpty()->toArray();
        if (!$sender) throw new CommonException('请先在商城地址库设置默认发货地址');
        $weight = (float) ($params['weight'] ?? 1);
        if ($weight <= 0 || $weight > 100) throw new CommonException('请输入实际包裹重量（大于 0 且不超过 100 千克）');
        $descriptor = $registry->all($siteId)[$providerKey] ?? [];
        if (!$this->companyId($siteId, (string) ($descriptor['carrier_code'] ?? ''), $descriptor['carrier_mapping'] ?? [])) throw new CommonException('尚未唯一匹配商城快递公司，请先配置所选服务商对应的物流公司编码；未申请运单');
        $payload = $identity + [
            'business_no' => (string) $order['order_no'],
            'order_id' => $orderId,
            'order_goods_ids' => $ids,
            'sender' => ['name' => $sender['contact_name'], 'mobile' => $sender['mobile'], 'address' => $this->address($sender['province_id'], $sender['city_id'], $sender['district_id'], $sender['address'])],
            'receiver' => ['name' => $order['taker_name'], 'mobile' => $order['taker_mobile'], 'address' => $this->address($order['taker_province'], $order['taker_city'], $order['taker_district'], $order['taker_address'])],
            'cargo' => mb_substr(implode('、', array_column($goods, 'goods_name')), 0, 100),
            'weight' => $weight,
            'count' => 1,
        ];
        return $this->decorate($siteId, $providerKey, $registry->execute($siteId, $providerKey, 'create', $payload));
    }

    public function assertReadyForDelivery(int $siteId, array $params): void
    {
        $task = $this->execute($siteId, [
            'operation' => 'query', 'provider_key' => $params['waybill_provider_key'],
            'order_id' => $params['order_id'], 'order_goods_ids' => $params['order_goods_ids'],
        ]);
        if (empty($task['task_id']) || empty($task['waybill_no']) || !in_array($task['state'] ?? '', ['ready', 'print_pending', 'printed', 'print_failed'], true)) {
            throw new CommonException('电子面单尚未确认有效，或已取消/状态不明，请先到物流任务核实；订单未发货');
        }
        if (($task['environment'] ?? '') === 'sandbox' || (array_key_exists('can_confirm_delivery', $task) && !$task['can_confirm_delivery'])) {
            throw new CommonException('此面单仅用于测试或尚未达到交件条件，不能用于正式订单发货');
        }
        if (!hash_equals((string) $task['waybill_no'], trim((string) ($params['express_number'] ?? '')))) throw new CommonException('运单号与本包裹的面单任务不一致，请重新获取任务后发货');
        if (empty($task['express_company_id']) || (int) $task['express_company_id'] !== (int) ($params['express_company_id'] ?? 0)) throw new CommonException('未唯一匹配商城快递公司，或承运商与电子面单不一致，请核对商城物流公司编码');
    }

    private function address($province, $city, $district, $detail): string
    {
        $areas = (new SysArea())->whereIn('id', [(int) $province, (int) $city, (int) $district])->column('name', 'id');
        return (string) ($areas[$province] ?? '') . ($areas[$city] ?? '') . ($areas[$district] ?? '') . (string) $detail;
    }

    private function decorate(int $siteId, string $providerKey, array $result): array
    {
        $providerKey = (string)($result['provider_key'] ?? $result['provider_code'] ?? $providerKey);
        $result['provider_key'] = $providerKey;
        $result['express_company_id'] = 0;
        $carrier = (string) ($result['carrier_code'] ?? '');
        if ($carrier !== '') {
            $descriptor = (new ElectronicSheetProviderRegistry())->all($siteId)[$providerKey] ?? [];
            $result['express_company_id'] = $this->companyId($siteId, $carrier, $result['carrier_mapping'] ?? $descriptor['carrier_mapping'] ?? []);
        }
        return $result;
    }

    private function companyId(int $siteId, string $carrier, array $mapping = []): int
    {
        // Optional, provider-neutral mapping. Field names are fixed to local carrier code columns.
        if ($mapping) {
            $field = (string)($mapping['field'] ?? '');
            $code = (string)($mapping['code'] ?? '');
            if (!in_array($field, ['express_no', 'express_no_electronic_sheet', 'kd100_express_no', 'kd100_express_no_electronic_sheet'], true) || $code === '') return 0;
            $companies = (new Company())->where([['site_id', '=', $siteId], [$field, '=', $code]])->column('company_id');
            return count($companies) === 1 ? (int)$companies[0] : 0;
        }
        if ($carrier === '') return 0;
        foreach (['kd100_express_no_electronic_sheet', 'kd100_express_no'] as $field) {
            $companies = (new Company())->where([['site_id', '=', $siteId], [$field, '=', $carrier]])->column('company_id');
            if (count($companies) > 1) return 0;
            if (count($companies) === 1) return (int) $companies[0];
        }
        return 0;
    }
}
