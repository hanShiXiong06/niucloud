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

namespace addon\recycle\app\service\core\order;

use addon\recycle\app\job\order_event\OrderAddAfter;
use addon\recycle\app\job\order_event\OrderAgreeAfter;
use addon\recycle\app\job\order_event\OrderPayAfter;
use addon\recycle\app\job\order_event\OrderSignAfter;
use addon\recycle\app\model\order\RecycleOrder;
use core\base\BaseCoreService;

/**
 * 回收订单事件服务层
 */
class CoreOrderEventService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleOrder();
    }

    /**
     * 订单下单事件
     * @param $data
     * @return true
     */
    public static function orderAdd($data){
        event('RecycleOrderAdd', $data);
        return true;
    }

    /**
     * 订单下单后事件
     * @param $data
     * @return true
     */
    public static function orderAddAfter($data){
        OrderAddAfter::dispatch(['data' => $data]);
        return true;
    }

    /**
     * 订单同意
     * @param $data
     * @return true
     */
    public static function orderAgree($data){
        event('RecycleOrderAgree', $data);
        return true;
    }

    /**
     * 订单同意后事件
     * @param $data
     * @return true
     */
    public static function orderAgreeAfter($data){
        OrderAgreeAfter::dispatch(['data' => $data]);
        return true;
    }

    /**
     * 订单打款
     * @param $data
     * @return true
     */
    public static function orderPay($data){
        event('RecycleOrderPay', $data);
        return true;
    }

    /**
     * 订单打款后事件
     * @param $data
     * @return true
     */
    public static function orderPayAfter($data){
        OrderPayAfter::dispatch(['data' => $data]);
        return true;
    }

    /**
     * 订单签收
     * @param $data
     * @return true
     */
    public static function orderSign($data){
        event('RecycleOrderSign', $data);
        return true;
    }

    /**
     * 订单签收后事件
     * @param $data
     * @return true
     */
    public static function orderSignAfter($data){
        OrderSignAfter::dispatch(['data' => $data]);
        return true;
    }
} 