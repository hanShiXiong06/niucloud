<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\order;

use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;
use app\service\core\notice\NoticeService;
use think\facade\Log;

/** 客户提交线下订单后建立负责人、企微待办和客户通知。 */
class OfflineOrderSubmitted
{
    public function handle(array $event = []): bool
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $orderId = (int)($event['order_id'] ?? 0);
        if ($siteId <= 0 || $orderId <= 0) return true;

        try {
            OfflineOrderSchemaService::ensure();
            $order = Order::where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])
                ->field('order_id,order_no,member_id,body,order_money,taker_name,taker_mobile,delivery_type,take_store_id,payment_mode,status')
                ->findOrEmpty();
            if ($order->isEmpty() || (string)$order->payment_mode !== 'offline_pending') return true;

            $config = (new CoreOrderConfigService())->getOnlineTradeConfig($siteId);
            $handlers = array_values((array)($config['offline_handlers'] ?? []));
            $defaultUid = (int)($config['offline_default_handler_uid'] ?? 0);
            $defaultHandler = [];
            foreach ($handlers as $handler) {
                if ((int)($handler['uid'] ?? 0) === $defaultUid) {
                    $defaultHandler = $handler;
                    break;
                }
            }
            if (!$defaultHandler && $handlers) $defaultHandler = $handlers[0];

            $now = time();
            $handlerUid = (int)($defaultHandler['uid'] ?? 0);
            $handlerName = (string)($config['offline_contact_name'] ?? ($defaultHandler['name'] ?? ''));
            $handlerMobile = (string)($config['offline_contact_mobile'] ?? ($defaultHandler['mobile'] ?? ''));
            $record = OrderOfflineRecord::where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->findOrEmpty();
            $recordData = [
                'handler_uid' => $handlerUid,
                'handler_name' => $handlerName,
                'handler_mobile' => $handlerMobile,
                'status' => 'pending',
                'update_time' => $now,
            ];
            if ($record->isEmpty()) {
                OrderOfflineRecord::create(array_merge($recordData, [
                    'site_id' => $siteId,
                    'order_id' => $orderId,
                    'contact_at' => 0,
                    'voucher_urls' => [],
                    'close_reason' => '',
                    'remark' => '',
                    'create_time' => $now,
                ]));
            } else {
                $record->save($recordData);
            }
            if ($handlerUid > 0) {
                Order::where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->update(['staff_id' => $handlerUid]);
            }

            foreach ($handlers as $handler) {
                $uid = (int)($handler['uid'] ?? 0);
                if ($uid <= 0) continue;
                try {
                    $params = ['order_id' => $orderId];
                    $query = '?' . http_build_query($params);
                    event('HsxBusinessTaskAssigned', [
                        'event_id' => 'phone-shop-offline-' . $siteId . '-' . $orderId . '-' . $uid,
                        'event_name' => 'task.assigned.v1',
                        'site_id' => $siteId,
                        'source_plugin' => 'phone_shop',
                        'source_type' => 'offline_order',
                        'source_id' => $orderId,
                        'stage_key' => 'offline_order_contact',
                        'assignee_uid' => $uid,
                        'assignee_name' => (string)($handler['name'] ?? ''),
                        'assigner_uid' => 0,
                        'assigner_name' => (string)($order->taker_name ?: $order->taker_mobile ?: '商城客户'),
                        'title' => '新线下自提订单：' . (string)($order->body ?: $order->order_no),
                        'pending_count' => (int)OrderOfflineRecord::where([
                            ['site_id', '=', $siteId], ['handler_uid', '=', $uid], ['status', 'in', ['pending', 'contacted', 'voucher_submitted', 'voucher_rejected']],
                        ])->count(),
                        'target' => [
                            'plugin' => 'phone_shop',
                            'route_key' => 'phone_shop.order.offline',
                            'params' => $params,
                            'web_path' => 'site/phone_shop/order/offline' . $query,
                            'miniapp_path' => 'addon/phone_shop/pages/order/list' . $query,
                        ],
                        'target_path' => 'site/phone_shop/order/offline' . $query,
                        'occurred_at' => $now,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[phone_shop] 线下订单任务通知失败：order_id=' . $orderId
                        . '，handler_uid=' . $uid . '，message=' . $e->getMessage());
                }
            }

            try {
                NoticeService::send($siteId, 'phone_shop_offline_order_submitted', ['order_id' => $orderId]);
            } catch (\Throwable $e) {
                Log::warning('[phone_shop] 线下订单客户通知失败：order_id=' . $orderId
                    . '，message=' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('[phone_shop] 线下订单责任记录失败：order_id=' . $orderId
                . '，message=' . $e->getMessage()
                . '，file=' . $e->getFile()
                . '，line=' . $e->getLine());
        }
        return true;
    }
}
