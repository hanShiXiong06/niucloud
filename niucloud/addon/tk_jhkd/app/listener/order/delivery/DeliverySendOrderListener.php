<?php


namespace addon\tk_jhkd\app\listener\order\delivery;

use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\delivery\DeliveryLoader;
/**
 * 提交订单事件
 */
class DeliverySendOrderListener
{
    public function handle($data)
    {
        $config = (new commonService())->getSiteDriver($data['site_id'],$data['data']['platform'])['params'];
        return (new DeliveryLoader($data['data']['platform'], $config))->sendOrder($data['data']);
    }
}

