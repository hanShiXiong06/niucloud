<?php
declare ( strict_types = 1 );

namespace addon\phone_shop\app\listener\refund;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderRefundDict;
use addon\phone_shop\app\dict\order\OrderRefundLogDict;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\service\core\CoreStatService;
use addon\phone_shop\app\service\core\goods\CoreGoodsStatService;
// use addon\phone_shop\app\service\core\marketing\CoreManjianService;
use addon\phone_shop\app\service\core\order\CoreOrderCloseService;
use addon\phone_shop\app\service\core\order\CoreOrderDeliveryService;
use addon\phone_shop\app\service\core\refund\CoreRefundLogService;
use think\facade\Log;

/**
 * 退款完成后操作
 */
class AfterShopOrderRefundFinish
{

    public function handle($data)
    {
        Log::write('订单AfterShopOrderRefundFinish $data---' . $$data);
        
        
        $refund_data = $data[ 'refund_data' ];

        $order = ( new Order() )->where([ [ 'order_id', '=', $refund_data[ 'order_id' ] ] ])->findOrEmpty();

        //满减送订单退款完成后退还赠品
        // ( new CoreManjianService() )->refundGift($refund_data);

        if (!$order->isEmpty()) {
            
             Log::write('订单AfterShopOrderRefundFinish $order[ status ]---' . $order[ 'status' ]. 'OrderDict::WAIT_DELIVERY' .OrderDict::WAIT_DELIVERY);
            if ($order[ 'status' ] == OrderDict::WAIT_DELIVERY) {
                //校验一下订单项是否全部发货
                ( new CoreOrderDeliveryService() )->checkFinish(
                    [ 'order_id' => $refund_data[ 'order_id' ], 'site_id' => $order[ 'site_id' ] ]
                );
            }
            //校验一下是否全部退款
            if ($order[ 'status' ] != OrderDict::CLOSE) {
                ( new CoreOrderCloseService() )->checkAllClose([ 'site_id' => $order[ 'site_id' ], 'order_id' => $refund_data[ 'order_id' ] ]);
            }
        }
        //商城统计
        CoreStatService::addStat([ 'site_id' => $order[ 'site_id' ], 'refund_money' => $refund_data[ 'money' ] ]);

        ( new CoreGoodsStatService() )->saveGoodsRefundNumAndMoneyByOrderId($refund_data); // 商品退款数量 金额统计数据

        //日志
        $main_type = $data[ 'main_type' ] ?? OrderRefundLogDict::SYSTEM;
        $main_id = $data[ 'main_id' ] ?? 0;
        //日志
        ( new CoreRefundLogService() )->add([
            'order_refund_no' => $refund_data[ 'order_refund_no' ],
            'status' => $refund_data[ 'status' ],
            'main_type' => $main_type,
            'main_id' => $main_id,
            'type' => OrderRefundDict::FINISH_ACTION,
            'content' => ''
        ]);
        //消息发送
    }
}
