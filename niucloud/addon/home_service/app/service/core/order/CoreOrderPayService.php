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

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use core\base\BaseCoreService;
use core\exception\CommonException;
use function think\db\column;

/**
 *  订单支付服务层
 */
class CoreOrderPayService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 订单已支付操作
     * @param $order_id
     * @return void
     */
    public function pay(array $data)
    {
        $order_id = $data['trade_id'];
        $where = [
            [
                'order_id', '=', $order_id
            ]
        ];
        $order = $this->model->where($where)->findOrEmpty();
        if ($order->isEmpty())
            throw new CommonException('HOME_SERVICE_ORDER_EXPIRE');//订单不存在
        // 状态判断
        if (!in_array($order->order_status, [OrderDict::WAIT_PAY, OrderDict::CLOSE])) throw new CommonException('HOME_SERVICE_ORDER_IS_PAY_FINISH');//订单支付
        $out_trade_no = $data['out_trade_no'] ?? '';
        $is_enable_refund = (new CoreOrderService())->checkOrderIsEnableRefund($order_id, 1);
        //订单状态变成已支付
        $order_data = array(
            'order_status' => OrderDict::WAIT_DISPATCH,
            'pay_time' => time(),
            'out_trade_no' => $out_trade_no,
            'is_enable_refund' => $is_enable_refund,
            'check_code' => mt_rand(1000, 9999),
        );
        $this->model->where($where)->update($order_data);
        //订单到达待派单状态
        $this->orderGoodsPay([
            'order_id' => $order_id,
            'out_trade_no' => $out_trade_no,
            'is_enable_refund' => $is_enable_refund,
        ]);
        $data['order_data'] = $order->toArray();
        $data['order_id'] = $order_id;
        //订单支付后操作
        event('AfterHomeServiceOrderPay', $data);
        return true;
    }


    /**
     * 支付后订单操作
     * @param $data
     * @return true
     */
    public function orderGoodsPay($data)
    {
        //指定师傅直接为待服务，否则为待派单
        $order_id = $data['order_id'];
        $update_data = array(
            'is_enable_refund' => $data['is_enable_refund'] ?? 0,
            'out_trade_no' => $data['out_trade_no'],
            'pay_time' => time()
        );
        $where = array(
            ['order_id', '=', $order_id],
        );
        (new OrderItem())->where($where)->update($update_data);
        return true;
    }


    /**
     * 订单项已支付操作
     * @param array $data
     * @return void
     */
    public function itemPay(array $data)
    {
        $batch_id = $data['trade_id'];

        $order_items = (new OrderItem())->where([['batch_id', '=', $batch_id]])->select()->toArray();
        if (empty($order_items)) throw new CommonException('HOME_SERVICE_ORDER_NOT_FOUND');//订单不存在

        foreach($order_items as $value){
            if ($value['is_pay'] == 1) throw new CommonException('HOME_SERVICE_ORDER_IS_PAY_FINISH');//订单支付
        }
        $order_item_ids = array_column($order_items,'order_item_id');

        $order_id = $order_items[0]['order_id'] ?? 0;
        $order = $this->model->where([['order_id', '=', $order_id]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('HOME_SERVICE_ORDER_NOT_FOUND');

        $out_trade_no = $data['out_trade_no'] ?? '';

        $item_money_total = 0;
        foreach ($order_items as $item) {
            $item_money_total = bcadd($item_money_total, $item['item_money'], 2);
        }
        $order->pay_money = bcadd($order->pay_money, $item_money_total, 2);
        $order->save();

        //订单状态变成已支付
        $order_data = array(
            'is_pay' => 1,
            'pay_time' => time(),
            'out_trade_no' => $out_trade_no,
        );
        (new OrderItem())->where([[ 'order_item_id', 'in', $order_item_ids ]])->update($order_data);

        event('ComputeOrderItemCommission', [
            'order_id' => $order_id,
            'order_type' => OrderDict::ORDER_TYPE_ITEM,
            'order_item_list' => $order_items,
        ]);

        (new CoreOrderLogService())->addLog($order->site_id, $order->order_id, OrderLogDict::ORDER_ITEM_PAY, 'member', $order->member_id, OrderDict::getStatus(OrderDict::IN_SERVICE));

        event('NotificationEvent', [
            'identity' => [NoticeDict::TECHNICIAN],
            'type' => NoticeDict::ITEM_PAY_SUCCESS,
            'notice_source' => NoticeDict::ORDER,
            'order_id' => $order->order_id,
            'technician_id' => $order->technician_id,
            'member_id' => $order->member_id,
            'site_id' => $order->site_id,
        ]);

        return true;
    }

}
