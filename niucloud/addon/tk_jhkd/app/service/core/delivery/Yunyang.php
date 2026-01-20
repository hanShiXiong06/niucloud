<?php

namespace addon\tk_jhkd\app\service\core\delivery;

use Exception;
use think\facade\Log;

/**
 * 云洋快递通道
 */
class Yunyang extends BaseDelivery
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

    public function preOrder($params)
    {
        $data = [
            "channelTag" => "智能",
            "channelSubTag" => "",
            "sender" => $params['senderName'],
            "senderMobile" => $params['senderMobile'],
            "senderProvince" => $params['senderProvince'],
            "senderCity" => $params['senderCity'],
            "senderCounty" => $params['senderDistrict'],
            "senderTown" => "",
            "senderLocation" => $params['senderAddress'],
            "senderAddress" => "",
            "receiver" => $params['receiveName'],
            "receiverMobile" => $params['receiveMobile'],
            "receiveProvince" => $params['receiveProvince'],
            "receiveCity" => $params['receiveCity'],
            "receiveCounty" => $params['receiveDistrict'],
            "receiveTown" => "",
            "receiveLocation" => $params['receiveAddress'],
            "receiveAddress" => "",
            "weight" => $params['weight'],
            "packageCount" => 1,
            "insured" => $params['guaranteeValueAmount'],
            "vloumLong" => $params['vloumLong'],
            "vloumWidth" => $params['vloumWidth'],
            "vloumHeight" => $params['vloumHeight']
        ];
        $resInfo = $this->execute('CHECK_CHANNEL_INTELLECT', $data);
        if ($resInfo['code'] != 1) return [];
        $callbackData = [];
        $resInfo = $resInfo['result'];
        $bj = 0;
        if ($data['insured'] > 0) {
            $bj = 1;
        }
        foreach ($resInfo as $k => $v) {

            $newdata = [
                "channelId" => $v['channelId'],
                "channelName" => $v['channel'],
                "preOrderFee" => $v['totalFreight'],
                "preDeliveryFee" => $v['freight'],
                "preBjFee" => $v['freightInsured'],
                "deliveryType" => $v['tagType'],
                "limitWeight" => $v['limitWeight'],
                "lightGoods" => $v['paozhong'],
                "calcFeeType" => $v['chargeType'] == 2 ? 'profit' : 'discount',
                "logo" => $v['channelLogoUrl'],
                "price" => [
                    [
                        "add" => $v['price']['priceMore'] ?? '',
                        "end" => 0,
                        "first" => $v['originalPrice'],
                        "start" => 1
                    ]
                ],
                "originalPrice" => [
                    [
                        "add" => 0,
                        "end" => 0,
                        "first" => $v['originalPrice'],
                        "start" => 1
                    ]
                ],
                "originalFee" => $v['originalPrice'],
                "isBest" => null,
                "bjRuleStr" => "",
                "onlinePay" => $v['payType'] == 2 ? 'Y' : "N",
                "rebateRatio" => null,
                "expReturn" => null,
                "deliveryBusiness" => $v['tagCode']
            ];
            if($v['chargeType'] == 2 ){
                $newdata['price'] = [
                    [
                        "add" => $v['priceMore'] ?? '',
                        "end" => 0,
                        "first" =>$v['priceOne'],
                        "start" => 1
                    ]
                ];

            }else{
                $newdata['price'] = [
                       'perAdd'=>$v['discountMore'],
                       'discount'=>$v['discount']
                ];
                $newdata['originalPrice'] = [
                    [
                        "add" =>$v['discountPriceMore']/$v['discount'],
                        "end" => 0,
                        "first" => $v['discountPriceOne']/$v['discount'],
                        "start" => 1
                    ]
                ];
            }
            if($bj==0){
                $callbackData[] = $newdata;
            }elseif($bj==1&&$v['allowInsured']==1){
                $callbackData[] = $newdata;
            }
        }
        return $callbackData;
    }

    public function sendOrder($params)
    {
        $data = [
            "channelId" => $params['channelId'],
            "channelTag" => "智能",
            "channelSubTag" => $params['deliveryType'],
            "sender" => $params['senderName'],
            "senderMobile" => $params['senderMobile'],
            "senderProvince" => $params['senderProvince'],
            "senderCity" => $params['senderCity'],
            "senderCounty" => $params['senderDistrict'],
            "senderTown" => "",
            "senderLocation" => $params['senderAddress'],
            "senderAddress" => $params['senderProvince'] . $params['senderCity'] . $params['senderDistrict'] . $params['senderAddress'],
            "receiver" => $params['receiveName'],
            "receiverMobile" => $params['receiveMobile'],
            "receiveProvince" => $params['receiveProvince'],
            "receiveCity" => $params['receiveCity'],
            "receiveCounty" => $params['receiveDistrict'],
            "receiveTown" => "",
            "receiveLocation" => $params['receiveAddress'],
            "receiveAddress" => $params['receiveProvince'] . $params['receiveCity'] . $params['receiveDistrict'] . $params['receiveAddress'],
            "weight" => $params['weight'],
            "packageCount" => 1,
            "itemName" => $params['goods'],
            "insured" => $params['guaranteeValueAmount'],
            "vloumLong" => $params['vloumLong'],
            "vloumWidth" => $params['vloumWidth'],
            "vloumHeight" => $params['vloumHeight'],
            "billRemark" => $params['remark'] ?? ''
        ];

        $resInfo = $this->execute('ADD_BILL_INTELLECT', $data);
        if ($resInfo['code'] != 1) {
            Log::write('提交运单失败：yunyang_error--' . $resInfo['message']);
            return ['type'=>'error','msg'=>$resInfo['message']??'三方平台下单失败，请重新下单！'];
        }

        $res = [
            'orderNo' => $resInfo['result']['shopbill'],
            'deliveryId' => $resInfo['result']['waybill'],
        ];
        return $res;
    }

    public function callbackOrder($data)
    {

    }

    public function cancelOrder($data)
    {
        $params = [
            "shopbill" => $data['order_no'],
        ];
        $resInfo = $this->execute('CANCEL', $params);
        $resInfo['data'] = $resInfo['result'];
        $resInfo['msg'] = $resInfo['message'];
        if ($resInfo['code'] == 1) {
            $resInfo['code'] = 200;
        }
        return $resInfo ?? '';
    }

    public function deliveryTrance($params)
    {
        $data = [
            "waybill" => $params['delivery_id'] ?? 0,
            "traceFormat" => 'obj'
        ];
        $resInfo = $this->execute('QUERY_TRANCE', $data);
        if ($resInfo['code'] != 1) {
            return [];
        }
        $tranceData = [];
        if ($resInfo['result'] != []) {
            foreach ($resInfo['result'] as $k => $v) {
                if (isset($v['comments'])) {
                    $tranceData[] = [
                        'time' => '',
                        'desc' => $v['comments'] ?? '',
                    ];
                } else {
                    $tranceData[] = [
                        'time' => $v['time'] ?? '',
                        'desc' => $v['desc'] ?? ''
                    ];
                }
            }
        }

        return $tranceData;
    }


    public function getBalance()
    {
        $data = [
            "appid" => $this->config['appid']
        ];
        $resInfo = $this->execute('QUERY_BALANCE', $data);
        if ($resInfo['code'] != 200) return $resInfo['message']??'未查询到信息';
        return $resInfo['result']['keyong']??'未查询到信息';
    }


    public function execute($serviceCode, $requestParams = [])
    {
        $timestamp = (string)intval(microtime(1) * 1000);
        //沙箱环境
        //$url = 'https://api.yunyangwl.com/api/sandbox/openService';
        //正式环境
        $url = 'https://api.yunyangwl.com/api/wuliu/openService';
        $appid = $this->config['appid'];
        $appkey = $this->config['appkey'];
        $requestId = md5(create_no() . $timestamp);
        $data = $appid . $requestId . $timestamp . $appkey;
        $sign = md5($data);
        $body = [
            "serviceCode" => $serviceCode,
            "timeStamp" => $timestamp,
            "requestId" => $requestId,
            "appid" => $appid,
            "sign" => $sign,
            "content" => $requestParams
        ];
        $header = ["Content-Type: application/json;charset=UTF-8"];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new Exception($err);
        } else {
            return json_decode($result, true);
        }
    }

}