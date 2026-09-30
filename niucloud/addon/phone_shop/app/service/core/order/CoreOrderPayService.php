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

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderGoods;
use core\base\BaseCoreService;
use core\exception\CommonException;
use app\model\pay\Pay;
use app\dict\pay\PayDict;
use think\facade\Db;
use think\facade\Log;

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
     * @param array $data
     * @return void
     */
    public function pay(array $data)
    {
        return Db::transaction(function () use ($data) {
            return $this->confirmPayment($data);
        });
    }

    private function confirmPayment(array $data)
    {
        $order_id = $data[ 'trade_id' ];
        $where = [
            [
                'order_id', '=', $order_id
            ],
            ['site_id', '=', (int)($data['site_id'] ?? 0)],
        ];
        $order = $this->model->where($where)->lock(true)->findOrEmpty();
        if ($order->isEmpty())
            throw new CommonException('SHOP_ORDER_NOT_FOUND');//订单不存在

        //todo 状态判断
        if ((int)$order['status'] === OrderDict::CLOSE) {
            throw new CommonException('原订单已关闭，不能继续确认发货；如有实际扣款，请联系商家核对支付流水并办理退款，请勿再次付款');
        }
        if ((int)$order['status'] !== OrderDict::WAIT_PAY) throw new CommonException('SHOP_ORDER_IS_PAY_FINISH');//订单支付
        $out_trade_no = $data[ 'out_trade_no' ] ?? '';
        $receivedMoney = (string)$order['order_money'];
        // 第三方托管单的支付流水属于源业务，由源业务核对；普通商城线上单
        // 必须使用本站、本订单已完成的支付流水，不能拿应付金额冒充实收。
        if (empty($order['relate_source']) && $out_trade_no !== '') {
            $payment = (new Pay())->where([
                ['site_id', '=', (int)$order['site_id']],
                ['trade_type', '=', OrderDict::TYPE], ['trade_id', '=', $order_id],
                ['out_trade_no', '=', $out_trade_no], ['status', '=', PayDict::STATUS_FINISH],
            ])->findOrEmpty();
            if ($payment->isEmpty()
                || bccomp((string)$payment['money'], (string)$order['order_money'], 2) !== 0) {
                Log::error('[phone_shop 支付金额核对失败] ' . json_encode([
                    'site_id' => $order['site_id'], 'order_id' => $order_id,
                    'out_trade_no' => $out_trade_no, 'order_money' => $order['order_money'],
                    'payment_money' => $payment->isEmpty() ? null : $payment['money'],
                ], JSON_UNESCAPED_UNICODE));
                throw new CommonException('支付流水与订单金额不一致，请联系商家核对，请勿重复付款');
            }
            $receivedMoney = (string)$payment['money'];
        } elseif (empty($order['relate_source']) && (float)$order['order_money'] > 0
            && !(($data['payment_mode'] ?? '') === 'offline_cash'
                && ($order['payment_mode'] ?? '') === 'offline_cash')) {
            throw new CommonException('缺少已完成的支付流水，不能确认付款；线下收款请通过后台确认');
        }
        //订单状态变成已支付
        $order_data = array(
            'status' => OrderDict::WAIT_DELIVERY,
            'pay_time' => time(),
            'timeout' => 0,
            'out_trade_no' => $out_trade_no,
            'pay_money' => (float)$receivedMoney,
            'is_enable_refund' => 1,
        );
        $this->model->where($where)->update($order_data);

        //订单到达待发货状态
        $this->orderGoodsPay([ 'order_id' => $order_id, 'site_id' => $order[ 'site_id' ] ]);

        $data[ 'order_data' ] = array_merge($order->toArray(), $order_data);
        $data[ 'order_id' ] = $order_id;

//        event('AfterPhoneShopOrderPay', $data);
        //订单支付操作
        CoreOrderEventService::orderPay($data);
        //订单支付后操作
        CoreOrderEventService::orderPayAfter($data);
        return true;
    }


    /**
     * 订单项支付操作
     * @param $data
     * @return true
     */
    public function orderGoodsPay($data)
    {
        $order_id = $data[ 'order_id' ];
        $site_id = $data[ 'site_id' ];
        $update_data = [
            'delivery_status' => OrderDeliveryDict::WAIT_DELIVERY,
            'is_enable_refund' => 1
        ];
        $where = [
            [ 'order_id', '=', $order_id ],
            [ 'site_id', '=', $site_id ],
        ];
        ( new OrderGoods() )->where($where)->update($update_data);
        return true;
    }

    /**
     * to待配送状态
     * @param $data
     * @return void
     */
    public function toDelivery($data)
    {
        //todo 根据订单项类判断各个商品的配送操作

    }
}
