<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\model\OrderDeliveryReal;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use addon\tk_jhkd\app\service\api\OrderService as ApiOrderService;
use app\service\core\notice\NoticeService;
use core\base\BaseApiService;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use think\Response;

/**
 * 亿速回掉对接 0509
 */
class YisuNoticeService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new Tkjhkdorder();
        $this->deliveryModel = new OrderDelivery();
        $this->deliveryRealModel = new OrderDeliveryReal();
    }

    public function notice($data)
    {
        Log::write('=====亿速OPEN回掉信息=====' . date('Y-m-d H:i:s', time()));
        Log::write($data);
        $type = $data['pushType'];
        //状态变更
        if ($type == 1) {
            $this->changeOrderStatus($data);
        }
        //计费变更
        if ($type == 2) {
            return $this->changeWeight($data);
        }
        //快递员变更
        if ($type == 3) {
            return $this->doPick($data);
        }
        //订单变更
        if ($type == 4) {
            return $this->changeOrder($data);
        }
    }

    /**
     * 订单状回调
     */
    public function changeOrderStatus($data)
    {
        Db::startTrans();
        try {
            $order_no = $data['orderNo'];
            $deliveryInfo = $this->deliveryModel->where(['order_no' => $order_no])->findOrEmpty();
            if ($deliveryInfo->isEmpty()) return [];
            $deliveryInfo->save([
                'order_status_desc' => $data['data']['desc']
            ]);
            //运输中
            if ($data['data']['status'] == 2) {
                $orderInfo = $this->orderModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
                $orderInfo->save(['order_status' => JhkdOrderDict::FINISH_PICK]);
                (new NoticeService())->send($orderInfo['site_id'], 'tk_jhkd_order_pick', ['order_id' => $orderInfo['order_id']]);
            }
            //订单完成
            if ($data['data']['status'] == 5) {
                $orderInfo = $this->orderModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
                if ($orderInfo['order_status'] == JhkdOrderDict::FINISH) return true;
                $orderInfo->save(['order_status' => JhkdOrderDict::FINISH]);
                (new NoticeService())->send($orderInfo['site_id'], 'tk_jhkd_order_sign', ['order_id' => $orderInfo['order_id']]);
                (new OrderFinishService())->orderFinish($orderInfo);
            }
            //快递方取消订单同步发起退款
            if ($data['data']['status'] == 6 || $data['data']['status'] == 7 || $data['data']['status'] == 8 || $data['data']['status'] == 9) {
                $orderInfo = $this->orderModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
                $orderInfo->save(['order_status' => JhkdOrderDict::CLOSE]);
                //自动原路发起退款
                $data = [
                    "id" => $orderInfo['id'],
                    "close_reason" => $data['data']['status'] ?? '快递方取消订单'
                ];
                (new ApiOrderService())->applyRefund($data);
            }
            $this->deliveryModel->where(['order_no' => $order_no])->update([
                'delivery_id' => $data['waybillNo'],
            ]);
            Db::commit();

        } catch (Exception $e) {
            Db::rollback();
        }
    }

    public function changeWeight($data)
    {
        Db::startTrans();
        try {
            $order_no = $data['orderNo'];
            $info = $data['data'];
            //计算总支付费用
            $total_fee = 0;
            foreach ($info['feeList'] as $k => $v) {
                if ($info['feeList'][$k]['type'] == 1) {
                    $info['feeList'][$k]['type'] = 0;
                }
                $total_fee += $v['fee'];
            }
            $deliveryInfo = $this->deliveryModel->where(['order_no' => $order_no])->findOrEmpty();
            if ($deliveryInfo->isEmpty()) return [];
            $realInfo = $this->deliveryRealModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
            $weightFee = $info['weightFee'] ?? 1;
            $realInfo->where(['order_id' => $deliveryInfo['order_id']])->update([
                'order_id' => $deliveryInfo['order_id'],
                'weight' => $info['weightFinal'] ?? 1,
                'volume' => $info['volume'] ?? 1,
                'fee_weight' => $weightFee,
                'package_count' => $info['packageCount'] ?? 1,
                'fee_blockList' => json_encode($info['feeList']),
                'total_fee' => $total_fee,
            ]);
            $orderInfo = $this->orderModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
            //修改订单状态
            $orderInfo->save(['order_status' => JhkdOrderDict::FINISH_PICK]);
            //生成补差价订单
            $add = 3;   //初始续费add
            if (isset($deliveryInfo['price_rule']) && $deliveryInfo['price_rule'] != '') {
                $price_rule = json_decode($deliveryInfo['price_rule'], true);
                $add = $price_rule['add'] ?? 3;
            }
            $price_add = 0;
            if ($weightFee > $deliveryInfo['weight']) {
                $calc_weight = $weightFee - $deliveryInfo['weight'];
                if (isset($price_rule['discount']) && $price_rule['discount'] < 1 && $price_rule['discount'] > 0) {
                    $new_total_price = $total_fee - $orderInfo['order_money'];
                    $config = $this->getConfig($orderInfo['site_id']);
                    if ($config['floatWay'] == 'floatWayFixed') {
                        $price_add = ceil($new_total_price + $config['floatAmount']);
                    }
                    if ($config['floatWay'] == 'floatWayRate') {
                        $float = $config['floatRate'] / 100;
                        $price_add = ceil(($total_fee - $orderInfo['order_money'] / (1 + $float)) * (1 + $float));
                    }
                    if ($config['floatWay'] == 'floatWayBetwn') {
                        $firstAmount = $config['firstAmount'];
                        $secondAmount = $config['secondAmount'];
                        $price_add = ceil($total_fee - $orderInfo['order_money'] + $firstAmount + $calc_weight * $secondAmount);
                    }
                } else {
                    $price_add = ceil($calc_weight) * $add;
                }
            }
            $reduce_price = 0;
            if ($weightFee < $deliveryInfo['weight']) {
                $calc_weight = $deliveryInfo['weight'] - $weightFee;
                $reduce_price = ceil($calc_weight) * $add;
            }
            $price_add = $price_add - $reduce_price;
            if ($price_add > 0) {
                $addModel = new OrderAdd();
                $addInfo = $addModel->where(['order_id' => $orderInfo['order_id']])->findOrEmpty();
                if ($addInfo->isEmpty()) {
                    (new OrderAdd())->create([
                        'site_id' => $orderInfo['site_id'],
                        'member_id' => $orderInfo['member_id'],
                        'order_no' => create_no(),
                        'order_id' => $orderInfo['order_id'],
                        'order_money' => $price_add,
                        'ip' => request()->ip() ?? '',
                    ]);
                    (new NoticeService())->send($orderInfo['site_id'], 'tk_jhkd_order_add', ['order_id' => $orderInfo['order_id']]);
                }
            }
            $this->deliveryModel->where(['order_no' => $order_no])->update([
                'delivery_id' => $data['waybillNo'],
            ]);
            Db::commit();
            event('DeliveryUploadShipping', ['site_id' => $orderInfo['site_id'], 'order_id' => $orderInfo['order_id']]);
        } catch (Exception $e) {
            Db::rollback();
            Log::write('亿速回调处理-重量推送-异常' . $e->getMessage());
            throw new Exception($e->getMessage());
        }

    }

    public function getConfig($site_id)
    {
        return (new CommonService())->getConfig($site_id);
    }

    public function doPick($data)
    {
        $order_no = $data['orderNo'];
        $info = $data['data'];
        $deliveryInfo = $this->deliveryModel->where(['order_no' => $order_no])->findOrEmpty();
        if ($deliveryInfo->isEmpty()) return [];
        $deliveryInfo->save(['courier_context' => json_encode([
            'courierName' => $info['courierInfo'],
            'courierPhone' => $info['courierPhone']
        ])]);
        $this->deliveryModel->where(['order_no' => $order_no])->update([
            'delivery_id' => $data['waybillNo'],
        ]);
    }

    public function changeOrder($data)
    {
        try {
            $order_no = $data['orderNo'];
            $info = $data['data'];
            $deliveryInfo = $this->deliveryModel->where(['order_no' => $order_no])->findOrEmpty();
            if ($deliveryInfo->isEmpty()) return [];
            $this->deliveryModel->where(['order_no' => $order_no])->update([
                'delivery_id' => $info['newWaybillNo'],
            ]);
        } catch (Exception $e) {
            Log::write('亿速回调处理-运单号推送-异常' . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }
}