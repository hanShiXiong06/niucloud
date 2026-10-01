<?php
declare(strict_types=1);
namespace addon\phone_shop\app\service\admin\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\order\CoreOrderDeviceReturnService;
use addon\phone_shop\app\service\core\order\CoreOrderLogService;
use addon\phone_shop\app\service\core\order\ErpDeviceSnapshot;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

class OfflineOrderReturnService extends BaseAdminService
{
    public function preview(int $orderId): array
    {
        [$order, $lines, $event] = $this->context($orderId, false);
        $plan = $this->erp($event + ['action' => 'preview']);
        $map = array_column($plan['items'], null, 'order_goods_id');
        $rows = [];
        foreach ($lines as $line) {
            if ((int)$line->is_gift === 1) continue;
            $sku = GoodsSku::where('site_id', $this->site_id)->where('sku_id', (int)$line->sku_id)->findOrEmpty();
            $identity = ErpDeviceSnapshot::fromOrderLine($line->toArray(), $sku->toArray());
            $return = ErpDeviceSnapshot::decode($line->extend)['erp_return'] ?? [];
            $row = $map[(int)$line->order_goods_id] ?? ['can_return' => false, 'reason' => 'ERP 未返回该设备的原销售记录'];
            if (!empty($return['received'])) {
                $row['can_return'] = false;
                if (empty($row['returned'])) $row['reason'] = '已退回，原记录保留；请核对 ERP 原退货单';
            }
            $rows[] = array_merge($row, [
                'order_goods_id' => (int)$line->order_goods_id, 'goods_name' => (string)$line->goods_name,
                'sku_name' => (string)$line->sku_name, 'imei' => (string)($identity['imei'] ?: $identity['sn'] ?: $identity['sku_no']),
                'num' => (int)$line->num, 'received' => !empty($return['received']),
            ]);
        }
        return ['order_id' => $orderId, 'order_no' => (string)$order->order_no, 'preview_token' => $plan['preview_token'],
            'payment_mode' => (string)$order->payment_mode, 'items' => $rows];
    }

    public function confirm(array $data): array
    {
        if (!in_array($data['received'] ?? false, [true, 1, '1'], true)) throw new CommonException('请先确认已核对串号并实际收回所选设备');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($data['order_goods_ids'] ?? [])))));
        if (!$ids || count($ids) > 100) throw new CommonException('请选择 1 至 100 台退回设备');
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 255) throw new CommonException('请填写退回原因，不超过 255 字');
        OfflineOrderSchemaService::ensure();
        return Db::transaction(function () use ($data, $ids, $reason) {
            [$order, $lines, $event] = $this->context((int)($data['order_id'] ?? 0), true);
            $byId = [];
            foreach ($lines as $line) $byId[(int)$line->order_goods_id] = $line;
            foreach ($ids as $id) if (!isset($byId[$id]) || (int)$byId[$id]->is_gift === 1) throw new CommonException('退回设备不属于当前订单');
            $pending = array_values(array_filter($ids, static fn($id) => empty(ErpDeviceSnapshot::decode($byId[$id]->extend)['erp_return']['received'])));
            if (!$pending) return ['duplicate' => true, 'message' => '所选设备已退回，未重复冲账或增加库存'];
            // 一次请求部分成功不应发生；发现状态变化要求刷新，避免对剩余设备使用过期金额。
            if (count($pending) !== count($ids)) throw new CommonException('部分设备已退回，请刷新后选择剩余设备');
            $result = $this->erp(array_merge($event, [
                'action' => 'confirm', 'order_goods_ids' => $ids, 'preview_token' => (string)($data['preview_token'] ?? ''),
                'reason' => $reason, 'warehouse_id' => (int)($data['warehouse_id'] ?? 0), 'location_id' => (int)($data['location_id'] ?? 0),
            ]));
            $returned = [];
            foreach ($result['items'] as $item) {
                $id = (int)$item['order_goods_id'];
                if (!in_array($id, $ids, true) || isset($returned[$id])) throw new CommonException('ERP 退回响应明细不一致，本次已回滚');
                (new CoreOrderDeviceReturnService())->receiveOffline((int)$this->site_id, $id, $item, (string)$this->username);
                $returned[$id] = $item;
            }
            if (count($returned) !== count($ids)) throw new CommonException('ERP 退回响应不完整，本次已回滚');
            (new CoreOrderLogService())->add([
                'order_id' => (int)$order->order_id, 'status' => (int)$order->status,
                'main_type' => OrderLogDict::STORE, 'main_id' => (int)$this->uid, 'type' => OrderDict::ORDER_REMARK_ACTION,
                'content' => (string)$this->username . '已核对并收回 ' . count($ids) . ' 台；原因：' . $reason . '；已收款转财务退款，未进行自动转账。',
            ]);
            return ['message' => '设备已收回并恢复待上架；已收款转财务退款，不会自动转账', 'items' => array_values($returned),
                'offset_amount' => round(array_sum(array_column($returned, 'offset_amount')), 2),
                'refund_amount' => round(array_sum(array_column($returned, 'refund_amount')), 2)];
        });
    }

    private function context(int $orderId, bool $lock): array
    {
        $order = Order::where('site_id', $this->site_id)->where('order_id', $orderId)->lock($lock)->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('订单不存在');
        if (empty($order->relate_source) && !in_array((string)$order->payment_mode, ['offline_cash', 'offline_credit'], true)) {
            throw new CommonException('此入口用于已收款或已挂账的线下订单；未成交订单请关闭，线上付款请走原路退款');
        }
        if (!in_array((int)$order->status, [OrderDict::WAIT_DELIVERY, OrderDict::WAIT_TAKE, OrderDict::FINISH, OrderDict::CLOSE], true)) {
            throw new CommonException('原订单尚未完成成交确认，不能办理退货');
        }
        $lines = OrderGoods::where('site_id', $this->site_id)->where('order_id', $orderId)->order('order_goods_id asc')->lock($lock)->select();
        $eventLines = [];
        foreach ($lines as $line) {
            if ((int)$line->is_gift === 1) throw new CommonException('该订单含赠品，请先核对赠品退回方式后通过原售后流程办理，未自动关闭或恢复库存');
            $sku = GoodsSku::where('site_id', $this->site_id)->where('sku_id', (int)$line->sku_id)->findOrEmpty();
            $identity = ErpDeviceSnapshot::fromOrderLine($line->toArray(), $sku->toArray());
            $eventLines[] = ['order_goods_id' => (int)$line->order_goods_id, 'num' => (int)$line->num,
                'asset_id' => (int)($identity['erp_asset_id'] ?? 0)];
        }
        return [$order, $lines, ['site_id' => (int)$this->site_id, 'source_plugin' => 'phone_shop', 'order_id' => $orderId,
            'relate_source' => (string)$order->relate_source, 'member_id' => (int)$order->member_id, 'lines' => $eventLines]];
    }

    private function erp(array $event): array
    {
        $matched = [];
        foreach ((array)event('ErpOfflineSaleReturnRequested', $event) as $response) {
            if (is_array($response) && ($response['consumer'] ?? '') === 'hsx_erp') $matched[] = $response;
        }
        if (count($matched) !== 1 || !is_array($matched[0]['items'] ?? null)) {
            throw new CommonException('ERP 退货服务未就绪，请同步更新 hsx_erp 并刷新插件事件缓存；未关单、未冲账');
        }
        return $matched[0];
    }
}
