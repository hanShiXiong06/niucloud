<?php

namespace addon\fast_pay\app\service\core\order;

use addon\fast_pay\app\dict\order\OrderDict;
use addon\fast_pay\app\model\order\Order;
use app\model\pay\Pay;
use app\model\pay\Refund;
use core\base\BaseApiService;
use core\exception\CommonException;
use Exception;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单公共方法
 */
class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * @Notes:支付成功处理
     * @Interface paySuccess
     * @param $pay_info
     * @return true
     * @author: TK
     * @Time: 2024/7/12   上午8:07
     */
    public function paySuccess($pay_info)
    {
        Db::startTrans();
        try {
            $trade_id = $pay_info['trade_id'] ?? 0;
            $order_model = new Order();
            $order_info = $order_model->where([['site_id', '=', $pay_info['site_id']], ['id', '=', $trade_id]])->findOrEmpty();
            if (empty($order_info))
                throw new CommonException('ORDER_NOT_EXIST');
            if ($order_info['status'] != OrderDict::WAIT_PAY) return true;
            $order_data = [
                'pay_time' => time(),
                'order_status' => OrderDict::FINISH,
                'out_trade_no' => $pay_info['out_trade_no']//支付后的交易流水号
            ];
            $order_info->save($order_data);
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * @Notes:退款成功
     * @Interface refundSuccess
     * @param $data
     * @author: TK
     * @Time: 2024/7/12   上午12:57
     */
    public function refundSuccess($data)
    {
        Db::startTrans();
        try {
            $this->model = new Refund();
            $this->OrderModel = new Order();
            $this->PayModel = new Pay();
            $refund_no = $data['refund_no'];
            $trade_type = $data['trade_type'];
            $trade_id = $data['trade_id'];
            $site_id = $data['site_id'];
            $payInfo = $this->PayModel->where(['site_id' => $site_id, 'trade_id' => $trade_id,'trade_type'=>OrderDict::getOrderType()['type']])->where('status', '<>', -1)->findOrEmpty();
            if ($payInfo->isEmpty()) throw new CommonException('select pay is empty');
            $payInfo->save([
                'status' => -1,
                'type' => '',
                'pay_time' => ''
            ]);
            $refundInfo = $this->model->where(['site_id' => $site_id, 'refund_no' => $refund_no, 'trade_id' => $trade_id])->findOrEmpty();
            if ($refundInfo->isEmpty()) throw new CommonException('select refund is empty');
            $refundInfo->save(['trade_type' => $trade_type]);
            $orderInfo = $this->OrderModel->where(['site_id' => $site_id, 'id' => $trade_id])->findOrEmpty();
            if ($orderInfo->isEmpty()) throw new CommonException('select order is empty');
            $orderInfo->save([
                'refund_status' => OrderDict::CLOSE,
                'refundstatus' => 4,
                'close_time' => time(),
                'pay_time' => '',
                'status' => OrderDict::CLOSE,
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }
}