<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice_template;

use addon\sd_xiaoyuan\app\model\order\Order;
use app\listener\notice_template\BaseNoticeTemplate;

class OrderCancelUser extends BaseNoticeTemplate
{
    private $key = 'sd_xiaoyuan_order_cancel_user';

    public function handle(array $params)
    {
        if ($this->key != $params['key']) {
            return;
        }
        $order_id = (int)($params['data']['order_id'] ?? 0);
        if (!$order_id) {
            return;
        }
        $order = (new Order())->where('id', $order_id)->find();
        if (empty($order)) {
            return;
        }
        $cancel_role = strtoupper((string)($params['data']['cancel_role'] ?? $order['cancel_role'] ?? 'USER'));
        $role_map = [
            'USER' => '用户',
            'RUNNER' => '接单员',
            'SYSTEM' => '系统',
            'ADMIN' => '管理员',
        ];
        $cancel_reason = trim((string)($params['data']['cancel_reason'] ?? $order['cancel_reason'] ?? ''));
        if ($cancel_reason === '') {
            $cancel_reason = '订单已取消';
        }
        $time_val = $order->getData('cancel_time') ?: time();
        if (is_numeric($time_val) && (int)$time_val > 946684800) {
            $time_ts = (int)$time_val;
        } elseif (!empty($time_val) && strtotime((string)$time_val)) {
            $time_ts = strtotime((string)$time_val);
        } else {
            $time_ts = time();
        }
        $wap_domain = get_wap_domain($order['site_id']);
        $detail_url = $wap_domain . '/addon/sd_xiaoyuan/pages/order/detail?id=' . $order_id;
        return $this->toReturn(
            [
                '__weapp_page' => 'addon/sd_xiaoyuan/pages/order/detail?id=' . $order_id,
                'order_no' => (string)$order['order_no'],
                'cancel_time' => date('Y-m-d H:i:s', $time_ts),
                'operator' => str_sub2($role_map[$cancel_role] ?? '用户', 20, false),
                'cancel_reason' => str_sub2($cancel_reason, 20, false),
                'url' => $detail_url,
            ],
            [
                'member_id' => (int)$order['member_id'],
            ]
        );
    }
}
