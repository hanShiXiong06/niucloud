<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_recycle\app\support\RecyclePurchaseSettlementPolicy;
use core\exception\CommonException;
use think\facade\Db;

/** ERP 负责采购调价，回收仅承接真实采购本金；不接受整备/内部成本冒充客户补款。 */
class CoreRecycleErpPurchasePriceService
{
    public function apply(array $event): array
    {
        if (($event['event_name'] ?? '') !== 'erp.purchase.price_adjustment.requested.v1'
            || ($event['event_version'] ?? 0) !== 1 || (int)($event['site_id'] ?? 0) <= 0
            || (int)($event['asset_id'] ?? 0) <= 0 || trim((string)($event['reason'] ?? '')) === '') {
            throw new CommonException('ERP 采购调价参数不完整');
        }
        $siteId = (int)$event['site_id'];
        $ids = RecyclePaymentOwnershipService::deviceIds([$event['source_device_id'] ?? 0]);
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('采购调价必须在同一业务事务中执行');
        $device = Db::name('recycle_device')->where('site_id', $siteId)->where('id', $ids[0])->find();
        if (!$device) throw new CommonException('回收来源设备不存在');
        $order = Db::name('recycle_order')->where('site_id', $siteId)->where('id', (int)$device['order_id'])->lock(true)->find();
        if (!$order) throw new CommonException('回收来源订单不存在');
        $scope = (new RecyclePaymentOwnershipService())->claim($siteId, (int)$device['order_id'], $ids, 'self_erp');
        $fact = $scope['devices'][$ids[0]]['erp'] ?? [];
        if (($fact['asset_ids'] ?? []) !== [(int)$event['asset_id']] || empty($fact['has_payable'])) {
            throw new CommonException('采购调价的设备或应付关联不一致，本次未保存');
        }
        $device = Db::name('recycle_device')->where('site_id', $siteId)->where('id', $ids[0])->lock(true)->find();
        $update = RecyclePurchaseSettlementPolicy::adjustment($device, $event);
        Db::name('recycle_device')->where('site_id', $siteId)->where('id', $ids[0])->update($update + ['update_at' => time()]);
        Db::name('recycle_device_log')->insert([
            'site_id' => $siteId, 'order_id' => (int)$device['order_id'], 'device_id' => $ids[0],
            'operation_type' => 'erp_purchase_price_adjust', 'action' => '采购调价',
            'old_status' => (int)$device['status'], 'new_status' => (int)$device['status'],
            'operator_id' => (int)($event['operator_uid'] ?? 0), 'operator_name' => (string)($event['operator_name'] ?? 'ERP'),
            'remark' => sprintf('采购价 %.2f → %.2f，累计已结算 %.2f，剩余待付 %.2f；历史付款保留，调价不代表付款。原因：%s',
                (float)$event['before_amount'], $update['final_price'], $update['pay_amount'],
                $update['final_price'] - $update['pay_amount'], (string)$event['reason']),
            'create_at' => time(),
        ]);
        (new RecycleDevicePaymentService())->refreshErpPaymentSummary((int)$device['order_id'], $siteId);
        return ['consumer' => 'hsx_recycle', 'status' => 'processed', 'source_device_id' => $ids[0]];
    }
}
