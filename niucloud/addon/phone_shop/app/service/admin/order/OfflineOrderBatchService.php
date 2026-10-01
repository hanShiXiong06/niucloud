<?php
declare(strict_types=1);
namespace addon\phone_shop\app\service\admin\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\model\delivery\Company;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 批量逐单执行原发货/完成服务；不调用付费取号，不把某一单失败掩盖为全部成功。 */
class OfflineOrderBatchService extends BaseAdminService
{
    public function process(array $data): array
    {
        if (!in_array($data['confirmed'] ?? false, [true, 1, '1'], true)) throw new CommonException('请确认实际交件或客户已收货后再操作');
        $action = (string)($data['action'] ?? '');
        if (!in_array($action, ['batch_delivery', 'batch_finish'], true)) throw new CommonException('批量操作不正确');
        $items = array_values((array)($data['items'] ?? []));
        if (!$items || count($items) > 50) throw new CommonException('每次请选择 1 至 50 笔订单');
        $ids = array_map(static fn($row) => (int)($row['order_id'] ?? 0), $items);
        if (min($ids) <= 0 || count(array_unique($ids)) !== count($ids)) throw new CommonException('订单选择无效或重复');
        $result = [];
        foreach ($items as $item) {
            try {
                // 与单笔取号、取消、交件保持同一把业务锁，再开启数据库事务。
                $message = (new \addon\phone_shop\app\service\core\delivery\electronic_sheet\ElectronicSheetProviderRegistry())
                    ->withOrderLocks((int)$this->site_id, (int)$item['order_id'], fn() => Db::transaction(fn() => $this->one($action, $item)));
                $result[] = ['order_id' => (int)$item['order_id'], 'success' => true, 'message' => $message];
            } catch (\Throwable $e) {
                $result[] = ['order_id' => (int)$item['order_id'], 'success' => false, 'message' => $e->getMessage()];
            }
        }
        $success = count(array_filter($result, static fn($row) => $row['success']));
        return ['success_count' => $success, 'failed_count' => count($result) - $success, 'items' => $result];
    }

    private function one(string $action, array $item): string
    {
        $orderId = (int)$item['order_id'];
        $order = Order::where('site_id', $this->site_id)->where('order_id', $orderId)->lock(true)->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('订单不存在或不属于本站');
        if (!in_array((string)$order->payment_mode, ['offline_cash', 'offline_credit'], true) && empty($order->relate_source)) {
            throw new CommonException('尚未确认线下收款或挂账，不能批量交付');
        }
        if ($action === 'batch_finish') {
            if ((int)$order->status === OrderDict::FINISH) return '订单已完成，未重复执行';
            if ((int)$order->status !== OrderDict::WAIT_TAKE) throw new CommonException('只有已发货的待收货订单可以完成');
            (new OrderFinishService())->finish($orderId);
            OrderOfflineRecord::where('site_id', $this->site_id)->where('order_id', $orderId)->update(['status' => 'delivered', 'update_time' => time()]);
            return '已确认客户收货，订单完成；未新增收款';
        }
        if ((int)$order->status !== OrderDict::WAIT_DELIVERY) throw new CommonException('订单已发货、完成或关闭，请核对当前状态；未重复发货');
        if ((string)$order->delivery_type === OrderDeliveryDict::STORE) {
            (new OfflineOrderService())->process(['order_id' => $orderId, 'action' => 'confirm_delivery', 'remark' => '批量确认：已当面核对客户并交付']);
            return '已当面交付，自提订单完成';
        }
        if ((string)$order->delivery_type !== OrderDeliveryDict::EXPRESS) throw new CommonException('同城配送或特殊订单请进入单笔发货，不批量发起付费配送');
        $companyId = (int)($item['express_company_id'] ?? 0);
        $number = trim((string)($item['express_number'] ?? ''));
        if ($companyId <= 0 || !preg_match('/\A[A-Za-z0-9-]{6,60}\z/', $number)) throw new CommonException('请选择快递公司并填写正确的实际运单号');
        if (!Company::where('site_id', $this->site_id)->where('company_id', $companyId)->count()) throw new CommonException('快递公司不存在或不属于本站');
        $lines = OrderGoods::where('site_id', $this->site_id)->where('order_id', $orderId)->lock(true)->select();
        $lineIds = [];
        foreach ($lines as $line) {
            if ((int)$line->is_gift === 1) continue;
            if (!empty(ErpDeviceSnapshot::decode($line->extend)['erp_return'])) continue;
            if ((string)$line->delivery_status === OrderDeliveryDict::WAIT_DELIVERY) $lineIds[] = (int)$line->order_goods_id;
        }
        if (!$lineIds) throw new CommonException('没有待发货的有效设备');
        (new OrderDeliveryService())->delivery([
            'order_id' => $orderId, 'order_goods_ids' => $lineIds, 'delivery_ids' => [],
            'delivery_type' => OrderDeliveryDict::EXPRESS, 'delivery_way' => 'manual_write',
            'express_company_id' => $companyId, 'express_number' => $number, 'electronic_sheet_id' => 0,
            'remark' => '批量登记实际交件，不申请电子面单',
        ]);
        return '已登记实际发货，等待客户收货；未申请或扣费取号';
    }
}
