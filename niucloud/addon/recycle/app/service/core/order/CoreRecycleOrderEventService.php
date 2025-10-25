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

use addon\recycle\app\job\order_event\OrderSignAfter;
use addon\recycle\app\job\order_event\OrderAddAfter;
use addon\recycle\app\job\order_event\OrderAgreeAfter;
use addon\recycle\app\job\order_event\OrderPayAfter;
use addon\recycle\app\model\RecycleOrder;
use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 回收订单事件服务层
 */
class CoreRecycleOrderEventService extends BaseCoreService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleOrder();
    }

    /**
     * 订单签收事件
     * @param $data
     * @return true
     */
    public static function orderSign($data){
        Log::record('【回收通知】订单签收事件触发: ' . json_encode($data), 'notice');
        event('RecycleOrderSign', $data);
        return true;
    }

    /**
     * 订单签收后事件
     * @param $data
     * @return true
     */
    public static function orderSignAfter($data){
        Log::record('【回收通知】订单签收后事件触发: ' . json_encode($data), 'notice');
        OrderSignAfter::dispatch(['data' => $data]);
        return true;
    }

    /**
     * 订单添加事件
     * @param $data
     * @return true
     */
    public static function orderAdd($data){
        Log::record('【回收通知】订单添加事件触发: ' . json_encode($data), 'notice');
        event('RecycleOrderAdd', $data);
        return true;
    }

    /**
     * 订单添加后事件
     * @param $data
     * @return true
     */
    public static function orderAddAfter($data){
        Log::record('【回收通知】订单添加后事件触发: ' . json_encode($data), 'notice');
        OrderAddAfter::dispatch(['data' => $data]);
        return true;
    }

    /**
     * 订单同意事件
     * @param array $data
     * @return true
     */
    public static function orderAgree($data){
        Log::record('【回收通知】订单同意事件触发: ' . json_encode($data), 'notice');
        try {
            // 确保发送正确的数据给事件
            event('RecycleOrderAgree', $data);
            Log::record('【回收通知】订单同意事件发送完成', 'notice');
        } catch (\Exception $e) {
            Log::record('【回收通知】订单同意事件异常: ' . $e->getMessage(), 'error');
        }
        return true;
    }

    /**
     * 订单同意后事件
     * @param array $data
     * @return true
     */
    public static function orderAgreeAfter($data){
        Log::record('【回收通知】订单同意后事件触发: ' . json_encode($data), 'notice');
        try {
            // 修改为直接派发作业，不需要关注async参数
            OrderAgreeAfter::dispatch(['data' => $data]);
            Log::record('【回收通知】订单同意后事件派发完成', 'notice');
        } catch (\Exception $e) {
            Log::record('【回收通知】订单同意后事件异常: ' . $e->getMessage(), 'error');
        }
        return true;
    }

    /**
     * 订单打款事件
     * @param $data
     * @return true
     */
    public static function orderPay($data){
        Log::record('【回收通知】订单打款事件触发: ' . json_encode($data), 'notice');
        event('RecycleOrderPay', $data);
        return true;
    }

    /**
     * 订单打款后事件
     * @param $data
     * @return true
     */
    public static function orderPayAfter($data){
        Log::record('【回收通知】订单打款后事件触发: ' . json_encode($data), 'notice');
        OrderPayAfter::dispatch(['data' => $data]);
        return true;
    }
} 