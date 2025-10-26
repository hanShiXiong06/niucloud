<?php
declare (strict_types=1);

namespace addon\home_service\app\listener\card;

use addon\home_service\app\dict\card\CardOrderDict;
use addon\home_service\app\dict\card\CardOrderLogDict;
use addon\home_service\app\model\card\CardOrder;
use addon\home_service\app\service\core\card\CoreCardOrderLogService;
use addon\home_service\app\service\core\order\CoreOrderConfigService;

class AfterHomeServiceCardOrderCreate
{

    public function handle($data)
    {
        $order_data = $data['order_data'] ?? [];
        $site_id = $order_data['site_id'];
        //发布日志
        $main_type = $data['main_type'] ?? 'member';
        $main_id = $data['main_id'] ?? $order_data['member_id'];
        (new CoreCardOrderLogService())->addLog($site_id, $order_data['order_id'], CardOrderLogDict::ORDER_CREATE, $main_type, $main_id, CardOrderDict::getStatus(CardOrderDict::WAIT_PAY));
        $core_order_config_service = new CoreOrderConfigService();
        $order_config = $core_order_config_service->getOrderConfig(0)['order_close'] ?? [];
        if ($order_config && $order_config['is_close'] == 1) {
            if ($order_config['close_length'] > 0) {
                (new CardOrder())->where([['order_id', '=', $order_data['order_id']]])->update([
                    'auto_close_time' => time() + $order_config['close_length'] * 60
                ]);
            }
        }
    }
}
