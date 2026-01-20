<?php

namespace addon\tk_jhkd\app\service\core\delivery;

use addon\tk_jhkd\app\dict\delivery\KdniaoBrandDict;
use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\service\core\ExpressService;
use Exception;
use think\facade\Log;

/**
 * 快递鸟
 */
class Kdniao extends BaseDelivery
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
        $data = [
            'TransportType' => 1,
            'ShipperType' => 5,
            'Weight' => $params['weight'],
            'InsureAmount' => $params['guaranteeValueAmount'],
            'Receiver' => [
                'ProvinceName' => $params['receiveProvince'],
                'CityName' => $params['receiveCity'],
                'ExpAreaName' => $params['receiveDistrict'],
            ],
            'Sender' => [
                'ProvinceName' => $params['senderProvince'],
                'CityName' => $params['senderCity'],
                'ExpAreaName' => $params['senderDistrict'],
            ]
        ];
        $resInfo = $this->execute('1815', $data);
        if (!isset($resInfo['ResultCode'])||$resInfo['ResultCode'] != 100) return [];
        $callbackData = [];
        $resInfo = $resInfo['Data'];
        foreach ($resInfo as $k => $v) {
            $newdata = [
                "channelId" => $v['shipperCode'],
                "channelName" => KdniaoBrandDict::getBrand($v['shipperCode'])['name'],
                "preOrderFee" => $v['totalFee'],
                "preDeliveryFee" => $v['cost'],
                "preBjFee" => $params['guaranteeValueAmount'],
                "deliveryType" => $v['shipperCode'],
                "limitWeight" => 0,
                "lightGoods" => 0,
                "calcFeeType" => 'profit',
                "logo" => KdniaoBrandDict::getBrand($v['shipperCode'])['logo'],
                "price" => [
                    [
                        "first" => $v['firstWeightAmount'],
                        "add" => $v['continuousWeightPrice'],
                        "start" => $v['firstWeight'],
                        "end" => 0,
                    ]
                ],
                "originalPrice" => [
                    [
                        "add" => 0,
                        "end" => 0,
                        "first" => $v['totalFee'],
                        "start" => 1
                    ]
                ],
                "originalFee" => $v['totalFee'],
                "isBest" => null,
                "bjRuleStr" => "",
                "onlinePay" => 'Y',
                "rebateRatio" => null,
                "expReturn" => null,
                "deliveryBusiness" => $v['shipperCode']
            ];

            $callbackData[] = $newdata;
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
            'ShipperCode' => $params['deliveryType'],//注意指定权限
            'TransportType' => 1,
            'ShipperType' => 5,
            'OrderCode' => $params['thirdNo'],
            'ExpType' => 1,
            'PayType' => 3,
            'Sender' => [
                'Name' => $params['senderName'],
                'Mobile' => $params['senderMobile'],
                'ProvinceName' => $params['senderProvince'],
                'CityName' => $params['senderCity'],
                'ExpAreaName' => $params['senderDistrict'],
                'Address' => $params['senderAddress'],
            ],
            'Receiver' => [
                'Name' => $params['receiveName'],
                'Mobile' => $params['receiveMobile'],
                'ProvinceName' => $params['receiveProvince'],
                'CityName' => $params['receiveCity'],
                'ExpAreaName' => $params['receiveDistrict'],
                'Address' => $params['receiveAddress'],
            ],
            'Weight' => $params['weight'],
            'Quantity' => $params['packageCount'],
            'Volume' => (int)$params['vloumLong'] * $params['vloumWidth'] * $params['vloumHeight'],
            'Remark' => $params['remark'],
            'Commodity' => [
                [
                    'GoodsName' => $params['goods'],
                    'GoodsQuantity' => $params['packageCount'],
                    'GoodsPrice' => $params['guaranteeValueAmount'],
                ]
            ],
            'InsureAmount' => $params['guaranteeValueAmount'],
        ];
        $resInfo = $this->execute('1801', $data);
        if ($resInfo['ResultCode'] != 100) {
            Log::write('提交运单失败：kdniao_error--' . $resInfo['Reason']);
            return ['type' => 'error', 'msg' => $resInfo['Reason'] ?? 'kdniao三方平台下单失败，请重新下单！'];
        }

        $res = [
            'orderNo' => $resInfo['Order']['KDNOrderCode'],
            'deliveryId' => '',
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
            "OrderCode" => $data['order_id'],
        ];
        $resInfo = $this->execute('1802', $params);
        $resInfo['data'] = $resInfo['ResultCode'];
        $resInfo['msg'] = $resInfo['Reason'];
        if ($resInfo['ResultCode'] == 100) {
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
        //使用阿里云驱动查询
        if ($params['delivery_type'] == 'SF') {
            $params['mobile'] = json_decode($params['start_address'], true)['mobile'];
            $params['mobile'] = substr($params['mobile'], -4);
        }
        $order_info=(new Order())->where(['order_id'=>$params['order_id']])->findOrEmpty();
        if($order_info->isEmpty()) return [];
        $params['site_id']=$order_info['site_id'];
        return (new ExpressService())->queryExpress($params['site_id'], $params);
    }


    public function getBalance()
    {
        return '请前往快递鸟开放平台查询';
    }

    /**
     * @Notes:公用执行
     * execute
     * @param $type
     * @param $requestData
     * 2024/12/12  00:09
     * author:TK
     */
    public function execute($type, $requestData)
    {
        //沙箱环境
        if( isset($this->config['is_test'])&&$this->config['is_test'] == 1){
            $url = 'http://183.62.170.46:8081/api/dist';
        }else{
            $url = 'https://api.kdniao.com/api/OOrderService';
        }
        $business_id = $this->config['business_id'];
        $api_key = $this->config['api_key'];
        $requestData = json_encode($requestData);
        $datas = array(
            'EBusinessID' => $business_id,
            'RequestType' => $type,
            'RequestData' => urlencode($requestData),
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, $api_key);
        $postdata = http_build_query($datas);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result, true);
    }

    /**
     * @Notes:加签
     * encrypt
     * @param $data
     * @param $ApiKey
     * 2024/12/12  00:09
     * author:TK
     */
    public function encrypt($data, $ApiKey)
    {
        return urlencode(base64_encode(md5($data . $ApiKey)));
    }

}