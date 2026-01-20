<?php

namespace addon\tk_jhkd\app\service\core\delivery;

use addon\tk_jhkd\app\dict\delivery\XindaBrandDict;
use Exception;
use think\facade\Log;

/**
 * 辛达快递通道
 */
class Xinda extends BaseDelivery
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
            "channelType" => "智能",
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
            "insuredMoney" => $params['guaranteeValueAmount'] != '' ? $params['guaranteeValueAmount'] : 0,
            "goodsLong" => $params['vloumLong'],
            "goodsWidth" => $params['vloumWidth'],
            "goodsHeight" => $params['vloumHeight']
        ];
        $resInfo = $this->execute('QUERY_CHANNELS', $data);
        if ($resInfo['code'] != 0) return [];
        $callbackData = [];
        $resInfo = $resInfo['data']['result'];
        foreach ($resInfo as $k => $v) {
            $newdata = [
                "channelId" => $v['channelId'],
                "channelName" => $v['channelName'],
                "preOrderFee" => $v['totalFreight'],
                "preDeliveryFee" => $v['orderFee'],
                "preBjFee" => $v['freightInsured'],
                "deliveryType" => $v['tagType'],
                "limitWeight" => $v['limitWeight'] ?? '',
                "lightGoods" => $v['paozhong'],
                "calcFeeType" => 'profit',
                "logo" => XindaBrandDict::getBrand($v['tagType'])['logo'],
                "price" => [
                    [
                        "add" => 0,
                        "end" => 0,
                        "first" => $v['priceOne'] == 0 ? $v['originalPrice'] : $v['priceOne'],
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
                "deliveryBusiness" => $v['tagType']
            ];
            $callbackData[] = $newdata;
        }
        return $callbackData;
    }

    public function sendOrder($params)
    {
        $data = [
            "channelId" => $params['channelId'],
            "channelType" => "智能",
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
            "insuredMoney" => $params['guaranteeValueAmount'],
            "goodsLong" => $params['vloumLong'],
            "goodsWidth" => $params['vloumWidth'],
            "goodsHeight" => $params['vloumHeight'],
            "takeStartTime" => "",
            "takeEndTime" => "",
            "notes" => $params['remark'] ?? '',
            "itemName" => $params['goods'],
        ];
        $resInfo = $this->execute('COURIER_PLACE_ORDER', $data);
        if (!isset($resInfo['code'])) {
            Log::write('XINDA_error-- 下单未返回参数 ');
            return ['type' => 'error', 'msg' => '三方平台下单失败，请重新下单！'];
        }
        if ($resInfo['code'] != 0) {
            Log::write('XINDA_error--' . $resInfo['msg']);
            return ['type' => 'error', 'msg' => $resInfo['msg'] ?? '三方平台下单失败，请重新下单！'];
        }
        $res = [
            'orderNo' => $resInfo['data']['XinDabill'],
            'deliveryId' => $resInfo['data']['Waybill'],
        ];
        return $res;
    }

    public function callbackOrder($data)
    {

    }

    public function cancelOrder($data)
    {
        $params = [
            "xinDabill" => $data['order_no'],
        ];
        $resInfo = $this->execute('COURIER_ORDER_CANCEL', $params);
        if ($resInfo['code'] != 0) {
            Log::write('XINDA_error--' . $resInfo['msg']);
        }
        //code  适配易达
        if ($resInfo['code'] == 0) {
            $resInfo['code'] = 200;
        }
        return $resInfo ?? '';
    }

    public function deliveryTrance($params)
    {
        $data = [
            "waybill" => $params['delivery_id'] ?? 0,
        ];
        $resInfo = $this->execute('COURIER_ORDER_QUERY', $data);
        if ($resInfo['code'] != 0) {
            return [];
        }
        $tranceData = [];
        foreach ($resInfo['data']['result'] as $k => $v) {
            $trance = explode('   【', $v);
            $tranceData[] = [
                'time' => $trance[0] ?? '',
                'desc' => $trance[1] ?? $resInfo['data']['result'] == [] ? '暂无轨迹' : '',

            ];
        }
        return $tranceData;
    }


    public function getBalance()
    {
        $data = [
            "username" => $this->config['username']
        ];
        $resInfo = $this->execute('QUERY_BALANCE', $data);
        if ($resInfo['code'] != 0) return $resInfo['msg'] ?? '查询余额失败';
        return $resInfo['data']['money'] ?? '未查询到信息';
    }


    public function execute($serviceCode, $requestParams = [])
    {
        $timestamp = (string)intval(microtime(1) * 1000);

        $url = 'https://xdwl.dadisci.com/api/courier/openService';
        $username = $this->config['username'];
        $secretkey = $this->config['secretkey'];
        $requestId = md5(create_no() . $timestamp);
        $data = $username . $requestId . $timestamp . $secretkey;
        $sign = md5($data);
        $body = [
            "serviceCode" => $serviceCode,
            "timestamp" => $timestamp,
            "requestId" => $requestId,
            "username" => $username,
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