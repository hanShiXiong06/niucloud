<?php


namespace addon\tk_jhkd\app\listener\order;
use addon\tk_jhkd\app\service\core\OrderFinishService;
/**
 * 订单关闭
 */
class OrderFinishListener
{
    public function handle($data)
    {
        (new OrderFinishService())->orderFinish($data);
        return true;
    }
}
