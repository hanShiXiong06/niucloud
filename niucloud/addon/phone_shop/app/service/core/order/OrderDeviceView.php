<?php
declare(strict_types=1);
namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\dict\order\OrderGoodsDict;

final class OrderDeviceView
{
    public static function decorate(array $line, array $order): array
    {
        $extend = ErpDeviceSnapshot::decode($line['extend'] ?? []);
        $sku = (array)($line['sku'] ?? []);
        $identity = ErpDeviceSnapshot::fromOrderLine($line, $sku);
        $line['device_identity'] = [
            'imei' => (string)($identity['imei'] ?? ''), 'sn' => (string)($identity['sn'] ?? ''),
            'sku_no' => (string)($identity['sku_no'] ?? ''),
            'source' => isset($extend['erp_device']) ? '原订单记录' : '当前商品资料（原订单未保存快照）',
        ];
        $isDevice = (int)($sku['is_unique'] ?? 0) === 1 || (int)($sku['erp_asset_id'] ?? 0) > 0 || $line['device_identity']['imei'] !== '';
        $record = $extend['erp_return'] ?? [];
        $line['return_state'] = $record ? (!empty($record['received']) ? 'returned' : 'awaiting_receipt') : '';
        $line['return_receiver'] = (string)($record['receiver'] ?? '');
        $line['can_confirm_received'] = $isDevice && empty($order['relate_source'])
            && !in_array((string)($order['payment_mode'] ?? ''), ['offline_cash', 'offline_credit'], true)
            && empty($record['received']) && ((int)($line['status'] ?? 0) === OrderGoodsDict::REFUND_FINISH || $line['return_state'] === 'awaiting_receipt');
        return $line;
    }
}
