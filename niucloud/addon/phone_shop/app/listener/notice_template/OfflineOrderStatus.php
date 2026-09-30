<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\notice_template;

use addon\phone_shop\app\model\order\Order;
use app\listener\notice_template\BaseNoticeTemplate;
use app\model\site\SiteMerchantBind;

/** 线下订单提交凭证、审核及关闭的统一客户状态通知。 */
class OfflineOrderStatus extends BaseNoticeTemplate
{
    public function handle(array $params)
    {
        if (($params['key'] ?? '') !== 'phone_shop_offline_order_status') return null;
        $payload = (array)($params['data'] ?? []);
        $orderId = (int)($payload['order_id'] ?? 0);
        $order = Order::where('order_id', $orderId)
            ->field('order_id,site_id,member_id,order_no,body,order_money,create_time')
            ->findOrEmpty()->toArray();
        if (!$order) return null;

        $statusName = mb_substr(trim((string)($payload['status_name'] ?? '订单状态更新')), 0, 20);
        $statusRemark = mb_substr(trim((string)($payload['status_remark'] ?? '请进入订单详情查看')), 0, 50);
        $page = 'addon/phone_shop/pages/order/detail?order_id=' . $orderId;
        $wapDomain = get_wap_domain((int)$order['site_id']);

        return $this->toReturn([
            '__wechat_page' => $wapDomain . '/' . $page,
            '__weapp_page' => $page,
            'order_no' => (string)$order['order_no'],
            'order_money' => (string)$order['order_money'],
            'body' => str_sub((string)$order['body']),
            'create_time' => $order['create_time'],
            'status_name' => $statusName,
            'status_remark' => $statusRemark,
            // 兼容小程序“审核结果通知”模板变量。
            'review_result' => $statusName,
            'review_time' => date('Y-m-d H:i:s'),
            'review_content' => mb_substr((string)($order['body'] ?: $order['order_no']), 0, 20),
            'review_reason' => $statusRemark,
            'url' => $wapDomain . '/' . $page,
        ], [
            'member_id' => (int)$order['member_id'],
            'merchant_id' => (new SiteMerchantBind())->where('site_id', (int)$order['site_id'])->value('id') ?? 0,
        ]);
    }
}
