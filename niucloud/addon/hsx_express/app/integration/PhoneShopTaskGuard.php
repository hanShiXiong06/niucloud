<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

use core\exception\CommonException;

/** Stops task-centre cancellation from bypassing the business shipment state. */
class PhoneShopTaskGuard
{
    public function handle($data = null): ?bool
    {
        $task = $data['task'] ?? [];
        $operation = (string) ($data['operation'] ?? '');
        if (($task['business_type'] ?? '') !== 'phone_shop' || !in_array($operation, ['cancel', 'recover'], true)) return null;
        if (!class_exists(\addon\phone_shop\app\model\order\Order::class)) throw new CommonException('商城插件不可用，无法核实是否已交件；请恢复商城后再处理');
        $siteId = (int) ($data['site_id'] ?? 0);
        $refs = $task['business_refs'] ?? [];
        $orderId = (int) ($refs['order_id'] ?? explode(':', (string) ($task['business_id'] ?? ''))[0]);
        $ids = array_map('intval', $refs['order_goods_ids'] ?? []);
        if ($siteId <= 0 || $orderId <= 0 || !$ids) throw new CommonException('无法核实原包裹，请联系管理员核实后处理，系统未取消运单');
        $order = (new \addon\phone_shop\app\model\order\Order())->where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->with(['order_goods'])->findOrEmpty()->toArray();
        if (!$order) throw new CommonException('原商城订单不存在，无法确认交件状态；请先人工核实');
        if ($operation === 'recover' && (($order['delivery_type'] ?? '') !== 'express' || (int) $order['status'] !== \addon\phone_shop\app\dict\order\OrderDict::WAIT_DELIVERY)) throw new CommonException('原商城订单已关闭、已发货或不再待发货，不能恢复为可发货运单');
        $found = [];
        foreach ($order['order_goods'] ?? [] as $goods) {
            if (!in_array((int) $goods['order_goods_id'], $ids, true)) continue;
            $found[] = (int) $goods['order_goods_id'];
            if (!empty($goods['delivery_id'])) throw new CommonException('商城已确认该包裹发货，不能取消或恢复此运单；请联系承运商及业务人员处理');
            if ($operation === 'recover' && (int) ($goods['status'] ?? 0) !== 1) throw new CommonException('原商品已退款或正在退款，不能恢复为可发货运单');
        }
        if (array_diff($ids, $found)) throw new CommonException('原包裹部分商品不存在，不能自动判断交件状态，请人工核实');
        return true;
    }
}
