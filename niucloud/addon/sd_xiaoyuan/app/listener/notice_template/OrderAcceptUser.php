<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\notice_template;

use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 跑手已接单提醒下单用户
 */
class OrderAcceptUser extends BaseNoticeTemplate
{
    private $key = 'sd_xiaoyuan_order_accept_user';

    /**
     * 组装公众号模板变量
     */
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
        $runner = (new Runner())->where('id', (int)$order['runner_id'])->find();
        if (empty($runner)) {
            return;
        }
        $typeMap = [
            'EXPRESS' => '快递代取',
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
        $service_type = $typeMap[$order['task_type']] ?? '校园帮订单';
        if (!empty($order['goods_name'])) {
            $service_type = $order['goods_name'];
        } elseif (!empty($order['task_desc'])) {
            $service_type = $order['task_desc'];
        }
        $time_val = $order->getData('accept_time') ?: time();
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
                '__wechat_page' => $detail_url,
                '__weapp_page' => 'addon/sd_xiaoyuan/pages/order/detail?id=' . $order_id,
                'order_no' => (string)$order['order_no'],
                'task_name' => str_sub2((string)$service_type, 20, false),
                'service_type' => str_sub2((string)$service_type, 5, false),
                'runner_name' => str_sub2((string)$runner['real_name'], 20, false),
                'runner_mobile' => (string)$runner['mobile'],
                'accept_time' => date('Y-m-d H:i:s', $time_ts),
                'url' => $detail_url,
            ],
            [
                'member_id' => (int)$order['member_id'],
            ]
        );
    }
}
