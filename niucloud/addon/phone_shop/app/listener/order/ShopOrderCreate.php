<?php
declare ( strict_types = 1 );

namespace addon\phone_shop\app\listener\order;

use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\service\core\marketing\CoreManjianService;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use think\facade\Log;

class ShopOrderCreate
{

    public function handle($data)
    {
        Log::write('订单ShopOrderCreate' . json_encode($data));
        $basic = $data[ 'basic' ];
        $order_data = $data[ 'order_data' ];
        $order_goods_data = $data[ 'order_goods_data' ] ?? [];
        $site_id = $order_data[ 'site_id' ];

        // 随订单事务确定一次处理时限；队列延迟或失败不影响后续自动关闭。
        $timeout = (new CoreOrderConfigService())->pendingPaymentTimeout(
            (int)$site_id, (string)($order_data['payment_mode'] ?? 'online'), (int)$data['time']
        );
        (new Order())->where('site_id', $site_id)->where('order_id', $order_data['order_id'])
            ->where('status', \addon\phone_shop\app\dict\order\OrderDict::WAIT_PAY)->update(['timeout' => $timeout]);

        //满减赠送记录
        ( new CoreManjianService() )->addGiveRecords($data);
    }
}
