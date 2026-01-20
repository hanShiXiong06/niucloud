<?php


namespace addon\tk_jhkd\app\listener\order\delivery;

use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\delivery\DeliveryLoader;
use app\service\core\sys\CoreConfigService;
use Exception;

/**
 * 取消订单事件
 */
class DeliveryCancelOrderListener
{
    public function handle($data)
    {
        $config = (new commonService())->getSiteDriver($data['site_id'],$data['data']['platform'])['params'];
        return (new DeliveryLoader($data['data']['platform'], $config))->cancelOrder($data['data']);
    }
}

