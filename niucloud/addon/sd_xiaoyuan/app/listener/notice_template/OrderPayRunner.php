<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice_template;

use addon\sd_xiaoyuan\app\model\order\Order;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 新订单通知接单员
 */
class OrderPayRunner extends BaseNoticeTemplate
{
    private $key = 'sd_xiaoyuan_order_pay_runner';

    /**
     * 组装公众号模板变量
     */
    public function handle(array $params)
    {
        if ($this->key != $params['key']) {
            return;
        }
        $order_id = (int)($params['data']['order_id'] ?? 0);
        $member_id = (int)($params['data']['member_id'] ?? 0);
        if (!$order_id || !$member_id) {
            return;
        }
        $order = (new Order())->where('id', $order_id)->find();
        if (empty($order)) {
            return;
        }
        $typeMap = [
            'EXPRESS' => '代取快递',
            'BUY' => '代买',
            'ERRAND' => '跑腿',
            'QUEUE' => '代排队',
            'CLASS' => '代上课',
            'PRINT' => '代打印',
            'SEAT' => '代占座',
            'CLEAN' => '代清洁',
            'TRASH' => '扔垃圾',
            'CARRY' => '帮搬运',
            'HELP' => '帮帮忙',
            'GAME' => '游戏陪玩',
            'GROUP' => '拼单',
            'PARTTIME' => '兼职招聘',
            'COMPANION' => '约伴组局',
        ];
        $service_name = $typeMap[$order['task_type']] ?? '校园帮订单';
        if (!empty($order['goods_name'])) {
            $service_name = $order['goods_name'];
        } elseif (!empty($order['task_desc'])) {
            $service_name = $order['task_desc'];
        }
        $delivery_address = $order['receive_address'] ?: $order['pickup_address'];
        $wap_domain = get_wap_domain($order['site_id']);
        $time_val = $order->getData('pay_time') ?: $order->getData('create_time');
        if (is_numeric($time_val) && (int)$time_val > 946684800) {
            $time_ts = (int)$time_val;
        } elseif (!empty($time_val) && strtotime((string)$time_val)) {
            $time_ts = strtotime((string)$time_val);
        } else {
            $time_ts = time();
        }
        $fee = (float)($order['actual_fee'] ?: $order['total_fee']);
        return $this->toReturn(
            [
                '__wechat_page' => $wap_domain . '/addon/sd_xiaoyuan/pages/runner/order-hall',
                '__weapp_page' => 'addon/sd_xiaoyuan/pages/runner/order-hall',
                'order_no' => (string)$order['order_no'],
                'order_amount' => number_format($fee, 2, '.', ''),
                'order_money' => number_format($fee, 2) . '元',
                'service_type' => str_sub2((string)$service_name, 20, false),
                'service_name' => str_sub2((string)$service_name, 20, false),
                'delivery_address' => str_sub2((string)$delivery_address, 20, false),
                'create_time' => date('Y-m-d H:i:s', $time_ts),
                'url' => $wap_domain . '/addon/sd_xiaoyuan/pages/runner/order-hall',
            ],
            [
                'member_id' => $member_id,
            ]
        );
    }
}
