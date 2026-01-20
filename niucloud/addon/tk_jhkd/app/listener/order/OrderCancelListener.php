<?php


namespace addon\tk_jhkd\app\listener\order;
use addon\tk_jhkd\app\service\api\OrderService as ApiOrderService;
/**
 * 订单关闭
 */
class OrderCancelListener
{
    public function handle($data)
    {
        (new ApiOrderService())->applyRefund($data);
    }
}
