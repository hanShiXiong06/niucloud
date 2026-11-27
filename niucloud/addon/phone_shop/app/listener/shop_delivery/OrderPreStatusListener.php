<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\listener\shop_delivery;

use addon\phone_shop\app\dict\local_delivery\LocalDeliveryStatusDict;
use addon\phone_shop\app\dict\shop_delivery\ShopDeliveryDict;

/**
 * 配送订单操作前置状态
 * @package addon\phone_shop\app\listener\shop_delivery
 */
class OrderPreStatusListener
{

    public function handle($params)
    {
        if (!empty($params['service']) && $params['service'] == ShopDeliveryDict::MERCHANT) {
            return [
                'finish' => [
                    LocalDeliveryStatusDict::IN_DELIVERY
                ], //完成订单
                'cancel' => [
                    LocalDeliveryStatusDict::IN_DELIVERY
                ], //取消订单
            ];
        }
    }
}
