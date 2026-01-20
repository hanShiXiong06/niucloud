<?php

namespace addon\tk_jhkd\app\service\core\delivery;

use addon\tk_jhkd\app\dict\delivery\KdniaoBrandDict;
use addon\tk_jhkd\app\dict\delivery\YisuBrandDict;
use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\service\core\ExpressService;
use Exception;
use think\facade\Log;

/**
 * 亿速API对接
 */
class Yisu extends BaseDelivery
{
    protected $config;

    /**
     * @param array $config
     * @return void
     */
    protected function initialize(array $config = [])
    {
        parent::initialize($config);
        $this->config = $config;
    }

    /**
     * @Notes:预下单
     * preOrder
     * @param $params
     * @throws Exception
     * @throws Exception
     * 2024/12/12  08:33
     * author:TK
     */
    public function preOrder($params)
    {
        $expressType = 1;
        if ($params['weight'] > 30 || $params['customerType'] == 'ky') {
            $expressType = 2;
        }
        $data = [
            "sendPhone" => "18050000000",
            "sendAddress" => $params['senderProvince'] . $params['senderCity'] . $params['senderDistrict'] . $params['senderAddress'],
            "receiveAddress" => $params['receiveProvince'] . $params['receiveCity'] . $params['receiveDistrict'] . $params['receiveAddress'],
            "packageNum" => $params['packageCount'],
            "goodsValue" => (int)$params['guaranteeValueAmount'],
            "weight" => $params['weight'],
            "length" => $params['vloumLong'],
            "width" => $params['vloumWidth'],
            "height" => $params['vloumHeight'],
            "payMethod" => 3,
            "expressType" => $expressType,
        ];
        $bj = 0;
        if ($data['goodsValue'] > 0) {
            $bj = 1;
        }
        $resInfo = $this->execute('/openApi/getPriceList', $data);
        if ($resInfo['code'] != 0 || !isset($resInfo['code'])) throw new Exception('获取运单报价失败：yisu_error--' . $resInfo['msg']);
        $callbackData = [];
        $resInfo = $resInfo['data'];
        foreach ($resInfo as $k => $v) {
            if (isset($v['channelFee'])) {
                $newdata = [
                    "channelId" => $v['productCode'],
                    "channelName" => YisuBrandDict::getBrand($v['productCode'])['name'],
                    "preOrderFee" => $v['channelFee'],
                    "preDeliveryFee" => $v['channelFee'],
                    "preBjFee" => $v['guarantFee'] ?? 0,
                    "deliveryType" => $v['productCode'],
                    "limitWeight" => $v['limitWeight'],
                    "lightGoods" => 0,
                    "logo" => YisuBrandDict::getBrand($v['productCode'])['logo'],
                    "price" => [
                        [
                            "first" => $v['priceA'] > 0 ? $v['priceA'] : $v['channelFee'],
                            "add" => $v['priceB'],
                            "start" => 1,
                            "end" => 0,
                        ]
                    ],
                    "originalPrice" => [
                        [
                            "add" => 0,
                            "end" => 0,
                            "first" => $v['originalFee'] > 0 ? $v['originalFee'] : $v['channelFee'],
                            "start" => 1,
                        ]
                    ],
                    "isBest" => null,
                    "bjRuleStr" => "",
                    "onlinePay" => 'Y',
                    "rebateRatio" => null,
                    "expReturn" => null,
                    "deliveryBusiness" => $v['productCode']
                ];
                if ($v['genre'] == 2) {
                    $newdata['calcFeeType'] = 'discount';
                    $newdata['price'] = [
                        'perAdd' => 0,
                        'discount' => $v['discount'] / 10
                    ];
                    $newdata['originalPrice'] = [
                        [
                            "add" => 0,
                            "end" => 0,
                            "first" => $v['originalFee'] > 0 ? $v['originalFee'] : $v['channelFee'],
                            "start" => 1
                        ]
                    ];
                } else {
                    $newdata['calcFeeType'] = 'profit';
                }
                $newdata['originalFee'] = $v['originalFee'] ?? 0;
                if (
                    !($bj == 1 && $newdata['preBjFee'] == 0)    // 只排除需要保价但保价费为0的情况
                    && !is_numeric(YisuBrandDict::getBrand($v['productCode'])['name'])
                    && !($v['productCode'] == 69 && $params['weight'] > 3)
                ) {
                    $callbackData[] = $newdata;
                }
            }
        }
        return $callbackData;
    }

    /**
     * @Notes:发送订单
     * sendOrder
     * @param $params
     * 2024/12/12  08:33
     * author:TK
     */
    public function sendOrder($params)
    {
        $data = [
            "productCode" => $params['deliveryType'],
            "senderPhone" => $params['senderMobile'],
            "senderName" => $params['senderName'],
            "guaranteeValueAmount" => $params['guaranteeValueAmount'],
            "senderAddress" => $params['senderProvince'] . $params['senderCity'] . $params['senderDistrict'] . $params['senderAddress'],
            "receiveAddress" => $params['receiveProvince'] . $params['receiveCity'] . $params['receiveDistrict'] . $params['receiveAddress'],
            "receivePhone" => $params['receiveMobile'],
            "receiveName" => $params['receiveName'],
            "goods" => $params['goods'],
            "packageNum" => $params['packageCount'],
            "volume" => (int)$params['vloumLong'] / 100 * $params['vloumWidth'] / 100 * $params['vloumHeight'] / 100,
            "weight" => $params['weight'],
        ];
        $resInfo = $this->execute('/openApi/doOrder', $data);
        if ($resInfo['code'] != 0) {
            Log::write('提交运单失败：yisu_error--' . $resInfo['msg']);
            return ['type' => 'error', 'msg' => $resInfo['msg'] ?? 'yisu三方平台下单失败，请重新下单！'];
        }

        $res = [
            'orderNo' => $resInfo['data']['orderNo'],
            'deliveryId' => $resInfo['data']['waybillNo'],
        ];
        return $res;
    }

    public function callbackOrder($data)
    {

    }

    /**
     * @Notes:取消订单
     * cancelOrder
     * @param $data
     * 2024/12/12  08:32
     * author:TK
     */
    public function cancelOrder($data)
    {
        $params = [
            "genre" => 1,
            "orderNo" => $data['order_no'] ?? 0,
        ];
        $resInfo = $this->execute('/openApi/doCancel', $params);
        $resInfo['data'] = $resInfo['code'];
        if ($resInfo['code'] == 0) {
            $resInfo['code'] = 200;
        }
        return $resInfo ?? '';
    }

    /**
     * @Notes:轨迹查询
     * deliveryTrance
     * @param $params
     * @throws Exception
     * @throws Exception
     * 2024/12/12  08:32
     * author:TK
     */
    public function deliveryTrance($params)
    {
        $data = [
            "waybillNo" => $params['delivery_id'] ?? 0,
        ];
        $resInfo = $this->execute('/openApi/getOrderDetail', $data);
        if ($resInfo['code'] != 0) {
            return [];
        }
        $tranceData = [];
        if ($resInfo['data']['traceList'] != []) {
            foreach ($resInfo['data']['traceList'] as $k => $v) {
                $tranceData[] = [
                    'time' => $v['opeTimeAll'] ?? '',
                    'desc' => $v['opeRemark'] ?? ''
                ];
            }
        }
        return $tranceData;
    }


    public function getBalance()
    {
        $resInfo = $this->execute('/openApi/fund', []);
        if ($resInfo['code'] != 0) {
            return $resInfo['msg'] ?? '未查询到信息';
        } else {
            return $resInfo['data']['balance'] ?? '未查询到信息';
        }
    }

    /**
     * @Notes:公用请求方法
     * execute
     * @param $url
     * @param $data
     * @throws Exception
     * @throws Exception
     * 2024/12/19  14:43
     * author:TK
     */
    public function execute($url, $data = [])
    {
        $appid = $this->config['appid'];
        $app_secret = $this->config['appkey'];
        $baseUrl = 'http://open.yisuopen.com';
        $timeStamp = time();
        $version = 'V1.0';
        $sign = $this->getSign($appid, $version, $timeStamp, $app_secret);
        $curl = curl_init();
        $headers = [
            'version: ' . $version,
            'appid: ' . $appid,
            'timestamp: ' . $timeStamp,
            'sign: ' . $sign,
            'Content-Type: application/json'
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $baseUrl . $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        if ($result === false) {
            $err = curl_error($curl);
            curl_close($curl);
            throw new Exception('Curl Error: ' . $err);
        }
        curl_close($curl);
        return json_decode($result, true);
    }

    /**
     * @Notes:生成签名
     * getSign
     * @param $appid
     * @param $version
     * @param $timeStamp
     * @param $appSecret
     * 2024/12/19  14:42
     * author:TK
     */
    public function getSign($appid, $version, $timeStamp, $appSecret)
    {
        return md5($appid . $version . $timeStamp . $appSecret);
    }
}