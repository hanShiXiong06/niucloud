<?php
declare (strict_types=1);

namespace addon\home_service\app\listener\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\job\order\OrderPayRemind;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\CoreOrderLogService;
use addon\home_service\app\model\order\Order;

class AfterHomeServiceOrderCreate
{

    public function handle($data)
    {

        $basic = $data['basic'] ?? [];
        $order_data = $data['order_data'] ?? [];
        $order_goods_data = $data['order_goods_data'] ?? [];
        $site_id = $order_data['site_id'];
        $main_type = $order_data['other_array']['main_type'] ?? 'member';
        $main_id = $order_data['other_array']['main_id'] ?? $order_data['member_id'];
        (new CoreOrderLogService())->addLog($site_id, $order_data['order_id'], OrderLogDict::ORDER_CREATE, $main_type, (int)$main_id, OrderDict::getStatus(OrderDict::WAIT_PAY));
        $core_order_config_service = new CoreOrderConfigService();
        $order_config = $core_order_config_service->getOrderConfig($site_id)['order_close'] ?? [];
        if ($order_config && $order_config['is_close'] == 1) {
            if ($order_config['close_length'] > 0) {
                (new Order())->where([['order_id', '=', $order_data['order_id']]])->update([
                    'auto_close_time' => time() + $order_config['close_length'] * 60
                ]);
            }
        }
        // todo
        // 订单催付通知
        // OrderPayRemind::dispatch(['site_id' => $site_id, 'order_id' => $order_data['order_id']], secs: 1800);
    }
}
