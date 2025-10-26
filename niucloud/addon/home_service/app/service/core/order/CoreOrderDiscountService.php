<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\service\core\order;

use addon\home_service\app\model\order\OrderDiscounts;
use addon\home_service\app\service\core\coupon\CoreCouponMemberService;
use core\base\BaseCoreService;

/**
 *  订单完成服务层
 */
class CoreOrderDiscountService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderDiscounts();
    }


    /**
     * 订单优惠
     * @param array $data
     * @return true
     */
    public function addAll(array $data)
    {
        $this->model->insertAll($data);
        return true;
    }

    /**
     * 订单优惠
     * @param int $order_id
     * @return true
     */
    public function recoverDiscount($order_id)
    {
        $order_discount_where = array(
            ['order_id', '=', $order_id]
        );

        $order_discount = (new OrderDiscounts())->where($order_discount_where)->select();

        if (!$order_discount->isEmpty()) {
            $recover_list = [];
            foreach ($order_discount as $v) {
                $item_discount_type = $v['discount_type'];
                $recover_list[$item_discount_type][] = $v['discount_type_id'];
            }

            foreach ($recover_list as $item_discount_type => $discount_type_ids) {
                switch ($item_discount_type) {
                    case 'coupon'://优惠券
                        (new CoreCouponMemberService())->recover($discount_type_ids);
                        break;
                }
            }
        }
        return true;
    }


}
