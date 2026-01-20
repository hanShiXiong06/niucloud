<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\model\OrderDeliveryReal;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use app\service\core\notice\NoticeService;
use core\base\BaseApiService;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use think\Response;

/**
 * 快递鸟回掉对接
 */
class KdniaoNoticeService extends BaseApiService
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
        Log::write('=====快递鸟回掉信息=====' . date('Y-m-d H:i:s', time()));
        Log::write($data);
        if ($data['RequestType'] != 103) return Response::create(['EBusinessID' => $data['RequestData']['EBusinessID'] ?? '100000', 'UpdateTime' => date('Y-m-d H:i:s', time()), 'Success' => true]);
        $requestData = json_decode($data['RequestData'], true);//请求的DATA
        $params = $requestData['Data'];
        $sign = $data['DataSign'];
        foreach ($params as $k => $v) {
            $this->sub($v, $requestData, $sign);
        }
        return Response::create([
            'EBusinessID' => $data['RequestData']['EBusinessID'] ?? '100000',
            'UpdateTime' => date('Y-m-d H:i:s', time()),
            'Success' => true,
            'Reason' => '成功'
        ],
            'json', 200);
    }

    public function sub($params, $requestData, $sign)
    {
        $order_id = $params['OrderCode'];
        $order_s = $this->orderModel->where(['order_id' => $order_id])->findOrEmpty();
        if ($order_s->isEmpty()) return;
        $order_info = $this->deliveryModel->where(['order_id' => $order_id])->findOrEmpty();
        if ($order_info->isEmpty()) return;
        $config = (new CommonService())->getSiteDriver($order_s['site_id'], 'kdniao')['params'];
        if (!isset($config['api_key']) || $config['api_key'] == '') return;
        //if ($this->encrypt($requestData, $config['api_key']) != $sign) return;
        Log::write('=====快递鸟回掉进入业务执行=====' . date('Y-m-d H:i:s', time()));
        //参数验证完成具体业务封装
        $state = $params['State'];
        //订单调度失败/取消推送/虚假揽件 进行订单取消
        if ($state == 99 || $state == 203 || $state == 206) $this->closeOrder($order_id, $params);
        //推送网点/快递员信息
        if ($state == 102 || $state == 103) $this->changePick($order_id, $params);
        //推送取件状态
        if ($state == 104) $this->pickPackage($order_id, $params);
        //推送揽收状态改变重量
        if ($state == 301 || $state == 601 || $state == 208) $this->changeWeight($order_id, $params);
        //推送更换运单信息
        if ($state == 302) $this->changeDelivery($order_id, $params);
        //推送签收状态
        if ($state == 3) $this->signPackage($order_id, $params);
    }

    /**
     * @Notes:订单签收
     * signPackage
     * @param $order_id
     * @param $params
     * 2024/12/12  22:46
     * author:TK
     */
    public function signPackage($order_id, $params)
    {
        $orderInfo = $this->orderModel->where(['order_id' => $order_id])->findOrEmpty();
        if ($orderInfo['order_status'] == JhkdOrderDict::FINISH) return true;
        $orderInfo->save(['order_status' => JhkdOrderDict::FINISH]);
        (new NoticeService())->send($orderInfo['site_id'], 'tk_jhkd_order_sign', ['order_id' => $orderInfo['order_id']]);
        (new OrderFinishService())->orderFinish($orderInfo);
    }

    /**
     * @Notes:运单改变
     * changeDelivery
     * @param $order_id
     * @param $params
     * 2024/12/12  22:46
     * author:TK
     */
    public function changeDelivery($order_id, $params)
    {
        $deliveryInfo = $this->deliveryModel->where(['order_id' => $order_id])->findOrEmpty();
        if ($deliveryInfo->isEmpty()) return;
        $deliveryInfo->save([
            'delivery_id' => $params['LogisticCode'] ?? '',
            'delivery_type' => $params['ShipperCode'] ?? '',
        ]);
    }

    /**
     * @Notes:揽收计费
     * changeWeight
     * @param $order_id
     * @param $params
     * @throws \Exception
     * @throws \Exception
     * 2024/12/12  22:46
     * author:TK
     */
    public function changeWeight($order_id, $params)
    {
        Db::startTrans();
        try{
            $deliveryInfo = $this->deliveryModel->where(['order_id' => $order_id])->findOrEmpty();
            if ($deliveryInfo->isEmpty()) {
                return Response::create(['msg' => '接受成功', 'code' => 200, 'success' => true], 'json', 200);
            }
            $realInfo = $this->deliveryRealModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
            $feeBlockList = [
                [
                    "fee" => $params['Cost'],
                    "type" => '0',
                    "name" => '重量计费'
                ],
                [
                    "fee" => $params['PackageFee'] ?? 0,
                    "type" => '1',
                    "name" => '包装费用'
                ],
                [
                    "fee" => $params['InsureAmount'] ?? 0,
                    "type" => '2',
                    "name" => '保价费用'
                ],
                [
                    "fee" => $params['OtherFee'] ?? 0,
                    "type" => '3',
                    "name" => '其他费用'
                ],
                [
                    "fee" => $params['OverFee'] ?? 0,
                    "type" => '3',
                    "name" => '超长超重费'
                ],
            ];
            // $feeWeight = floatval($params['Weight']) + floatval($params['VolumeWeight'] ?? 0);
            //计费剔除体积重量
            $feeWeight = $params['Weight'];
            $realInfo->where(['order_id' => $deliveryInfo['order_id']])->update([
                'order_id' => $deliveryInfo['order_id'],
                'weight' => $params['Weight'],
                'volume' => $params['Volume'] ?? 0,
                'fee_weight' => $feeWeight,
                'fee_blockList' => json_encode($feeBlockList),
                'total_fee' => $params['TotalFee'],
            ]);
            $orderInfo = $this->orderModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
            $orderInfo->save(['order_status' => JhkdOrderDict::FINISH_PICK]);
            $add = 3;   //初始续费add
            if (!empty($deliveryInfo['price_rule'])) {
                $price_rule = json_decode($deliveryInfo['price_rule'], true);
                $add = $price_rule['add'] ?? 3;
            }
            $weight_diff = $feeWeight - floatval($deliveryInfo['weight']);
            $price_add = $weight_diff > 0 ? ceil($weight_diff) * $add : 0;
            $reduce_price = $weight_diff < 0 ? ceil(abs($weight_diff)) * $add : 0;
            $final_price_diff = $price_add - $reduce_price;
            $additional_fees = array_reduce($feeBlockList, function ($carry, $item) {
                return $carry + ($item['type'] != '0' ? floatval($item['fee']) : 0);
            }, 0);
            $total_additional_price = $final_price_diff + $additional_fees;
            if ($total_additional_price > 0) {
                $addModel = new OrderAdd();
                $addInfo = $addModel->where(['order_id' => $orderInfo['order_id']])->findOrEmpty();
                $u_data = [
                    'site_id' => $orderInfo['site_id'],
                    'member_id' => $orderInfo['member_id'],
                    'order_no' => create_no(),
                    'order_id' => $orderInfo['order_id'],
                    'order_money' => round($total_additional_price, 2), // 保留两位小数
                    'ip' => request()->ip() ?? '',
                ];
                if ($addInfo->isEmpty()) {
                    $addModel->create($u_data);
                    (new NoticeService())->send($orderInfo['site_id'], 'tk_jhkd_order_add', ['order_id' => $orderInfo['order_id']]);
                } else {
                    $addModel->where(['order_id' => $orderInfo['order_id']])->update($u_data);
                }
            }
            Db::conmmit();
            event('DeliveryUploadShipping', ['site_id' => $orderInfo['site_id'], 'order_id' => $orderInfo['order_id']]);
        }catch (Exception $e){
            Db::rollback();
            return [];
        }
    }

    /**
     * @Notes:快递员操作揽收包裹  不返回重量
     * pickPackage
     * @param $order_id
     * @param $params
     * 2024/12/12  22:47
     * author:TK
     */
    public function pickPackage($order_id, $params)
    {
        $deliveryInfo = $this->deliveryModel->where(['order_id' => $order_id])->findOrEmpty();
        if ($deliveryInfo->isEmpty()) return;
        $orderInfo = $this->orderModel->where(['order_id' => $deliveryInfo['order_id']])->findOrEmpty();
        $orderInfo->save(['order_status' => JhkdOrderDict::FINISH_PICK]);
        (new NoticeService())->send($orderInfo['site_id'], 'tk_jhkd_order_pick', ['order_id' => $orderInfo['order_id']]);
    }

    /**
     * @Notes:推送网点或者揽件员
     * changePick
     * @param $order_id
     * @param $params
     * 2024/12/12  20:58
     * author:TK
     */
    public function changePick($order_id, $params)
    {
        $pickInfo = $params['PickerInfo'][0];
        $deliveryInfo = $this->deliveryModel->where(['order_id' => $order_id])->findOrEmpty();
        if ($deliveryInfo->isEmpty()) return;
        if ($params['State'] == 102) {
            $u_data = [
                'courierName' => $pickInfo['StationName'] ?? '',
                'courierPhone' => $pickInfo['StationTel'] ?? '',
                'pickUpCode' => $pickInfo['PickupCode'] ?? '',
            ];
        } else {
            $u_data = [
                'courierName' => $pickInfo['PersonName'] ?? '',
                'courierPhone' => $pickInfo['PersonTel'] ?? '',
                'pickUpCode' => $pickInfo['PickupCode'] ?? '',
            ];
        }
        $deliveryInfo->save([
            'delivery_id' => $params['LogisticCode'] ?? '',
            'courier_context' => json_encode($u_data)
        ]);
    }

    /**
     * @Notes:取消订单调度
     * closeOrder
     * @param $order_id
     * @param $params
     * 2024/12/12  15:02
     * author:TK
     */
    public function closeOrder($order_id, $params)
    {
        $order_info = $this->orderModel->where(['order_id' => $order_id])->findOrEmpty();;
        event('CancelOrder', [
            'id' => $order_info['id'],
            'close_reason' => $params['Reason'],
        ]);
        return;
    }

    /**
     * @Notes:封装签名
     * encrypt
     * @param $data
     * @param $ApiKey
     * 2024/12/12  15:02
     * author:TK
     */
    public function encrypt($data, $ApiKey)
    {
        $data = json_encode($data);
        $data = urlencode($data);
        return urlencode(base64_encode(md5($data . $ApiKey)));
    }

}