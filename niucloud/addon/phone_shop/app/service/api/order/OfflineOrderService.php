<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\api\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use addon\phone_shop\app\service\core\order\CoreOrderLogService;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;
use app\service\core\notice\NoticeService;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Db;

/** 客户侧线下付款凭证提交。 */
class OfflineOrderService extends BaseApiService
{
    public function submitVoucher(int $orderId, array $data): array
    {
        $config = (new CoreOrderConfigService())->getOnlineTradeConfig($this->site_id);
        if ((int)($config['offline_voucher_enabled'] ?? 1) !== 1) {
            throw new ApiException('门店暂未开启客户付款凭证上传，请联系订单负责人处理');
        }
        $urls = array_values(array_unique(array_filter(array_map(static function ($url): string {
            return mb_substr(trim((string)$url), 0, 500);
        }, (array)($data['voucher_urls'] ?? [])))));
        if (!$urls) throw new ApiException('请至少上传一张付款凭证');
        if (count($urls) > 6) throw new ApiException('付款凭证最多上传6张');
        $remark = mb_substr(trim((string)($data['remark'] ?? '')), 0, 500);

        OfflineOrderSchemaService::ensure();
        $result = Db::transaction(function () use ($orderId, $urls, $remark, $config): array {
            $order = Order::where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['order_id', '=', $orderId],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) throw new ApiException('订单不存在');
            if ((int)$order['status'] !== OrderDict::WAIT_PAY || (string)$order['payment_mode'] !== 'offline_pending') {
                throw new ApiException('该订单当前不能提交付款凭证，请刷新查看最新状态');
            }

            $record = OrderOfflineRecord::where([
                ['site_id', '=', $this->site_id], ['order_id', '=', $orderId],
            ])->lock(true)->findOrEmpty();
            $recordData = [
                'status' => 'voucher_submitted',
                'voucher_urls' => $urls,
                'remark' => $remark,
                'update_time' => time(),
            ];
            if ($record->isEmpty()) {
                OrderOfflineRecord::create(array_merge([
                    'site_id' => $this->site_id, 'order_id' => $orderId,
                    'handler_uid' => 0, 'handler_name' => '', 'handler_mobile' => '',
                    'contact_at' => 0, 'close_reason' => '', 'create_time' => time(),
                ], $recordData));
            } else {
                $record->save($recordData);
            }

            // 是否在客户提交凭证后继续锁单由订单设置控制。
            if ((int)($config['offline_hold_on_progress'] ?? 1) === 1) $order->save(['timeout' => 0]);
            (new CoreOrderLogService())->add([
                'order_id' => $orderId,
                'status' => OrderDict::WAIT_PAY,
                'main_type' => OrderLogDict::MEMBER,
                'main_id' => $this->member_id,
                'type' => OrderDict::ORDER_REMARK_ACTION,
                'content' => '客户已提交线下付款凭证，等待门店审核',
            ]);

            return ['order_id' => $orderId, 'order_no' => (string)$order['order_no'], 'body' => (string)$order['body']];
        });

        foreach ((array)($config['offline_handlers'] ?? []) as $handler) {
            $uid = (int)($handler['uid'] ?? 0);
            if ($uid <= 0) continue;
            event('HsxBusinessTaskAssigned', [
                'event_id' => 'phone-shop-offline-voucher-' . $this->site_id . '-' . $orderId . '-' . $uid,
                'event_name' => 'task.assigned.v1',
                'site_id' => $this->site_id,
                'source_plugin' => 'phone_shop',
                'source_type' => 'offline_order',
                'source_id' => $orderId,
                'stage_key' => 'offline_payment_review',
                'assignee_uid' => $uid,
                'assignee_name' => (string)($handler['name'] ?? ''),
                'assigner_uid' => 0,
                'assigner_name' => '商城客户',
                'title' => '客户已上传付款凭证：' . ($result['body'] ?: $result['order_no']),
                'pending_count' => (int)OrderOfflineRecord::where([
                    ['site_id', '=', $this->site_id], ['handler_uid', '=', $uid],
                    ['status', 'in', ['pending', 'contacted', 'voucher_submitted', 'voucher_rejected']],
                ])->count(),
                'target' => [
                    'plugin' => 'phone_shop', 'route_key' => 'phone_shop.order.offline',
                    'params' => ['order_id' => $orderId],
                    'web_path' => 'site/phone_shop/order/offline?order_id=' . $orderId,
                    'miniapp_path' => 'addon/phone_shop/pages/order/list?order_id=' . $orderId,
                ],
                'target_path' => 'site/phone_shop/order/offline?order_id=' . $orderId,
                'occurred_at' => time(),
            ]);
        }
        NoticeService::send($this->site_id, 'phone_shop_offline_order_status', [
            'order_id' => $orderId,
            'status_name' => '付款凭证已提交',
            'status_remark' => '门店负责人会尽快审核，请留意订单状态。',
        ]);

        return ['order_id' => $orderId, 'status' => 'voucher_submitted'];
    }
}
