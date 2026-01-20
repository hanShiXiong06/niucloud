<?php

namespace addon\tk_jhkd\app\service\core\delivery;

use addon\tk_jhkd\app\dict\delivery\YidaBrandDict;
use Exception;
use think\facade\Log;

/**
 * 易达178渠道
 */
class Yida extends BaseDelivery
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
        if ($params['weight'] > 50) {
            $params['customerType'] = 'ky';
        }
        $bj = 0;
        if ((int)$params['guaranteeValueAmount'] > 0) {
            $bj = 1;
        }
        $resInfo = $this->execute('SMART_PRE_ORDER', $params);
        if ($resInfo['code'] == 500) return [];
        foreach ($resInfo['data'] as $k => $v) {
            foreach ($v as $k1 => $v1) {
                $v1['logo'] = YidaBrandDict::getBrand($v1['deliveryType'])['logo'];
                if ($v1['onlinePay'] == 'Y') {
                    $dataInfo[] = $v1;
                    foreach ($dataInfo as $k2 => $v2) {
                        if($bj == 1 && $v2['preBjFee'] == 0){
                            unset($dataInfo[$k2]);
                        }
                    }
                }
            }
        }
        return $dataInfo;
    }

    public function sendOrder($params)
    {
        if ($params['weight'] > 50) {
            $params['customerType'] = 'ky';
        }
        $resInfo = $this->execute('SUBMIT_ORDER_V2', $params);
        if ($resInfo['code'] != 200) {
            Log::write("===易达发单ERROR===" . date('Y-m-d H:i:s'));
            Log::write($resInfo);
            return ['type' => 'error', 'msg' => $resInfo['msg'] ?? '三方平台下单失败，请重新下单！'];
        }
        Log::write("===易达发单数据===" . date('Y-m-d H:i:s'));
        Log::write($resInfo);
        return $resInfo['data'] ?? [];
    }

    public function callbackOrder($data)
    {

    }

    public function cancelOrder($data)
    {
        $params = [
            "orderNo" => $data['order_no'],
        ];
        $resInfo = $this->execute('CANCEL_ORDER', $params);
        return $resInfo ?? '';
    }

    public function deliveryTrance($params)
    {
        $data = [
            "deliveryId" => $params['delivery_id'] ?? 0
        ];
        $resInfo = $this->execute('DELIVERY_TRACE', $data);
        if ($resInfo['code'] != 200) return [];
        return $resInfo['data'];
    }

    public function getBalance()
    {
        $data = [
            "userMobile" => $this->config['bindMobile']
        ];
        $resInfo = $this->execute('ACCOUNT_BALANCE', $data);
        if ($resInfo['code'] != 200) {
            return $resInfo['msg'] ?? '未查询到信息';
        }
        return $resInfo['data']['accountBalance'] ?? '未查询到信息';
    }


    public function execute($method, $requestParams)
    {

        $timestamp = (string)intval(microtime(1) * 1000);
        $url = 'https://www.yida178.cn/prod-api/thirdApi/execute';
        $sign_Array = [
            "privateKey" => $this->config['privateKey'],
            "timestamp" => $timestamp,
            "username" => $this->config['username']
        ];

        $sign = strtoupper(MD5(json_encode($sign_Array, JSON_UNESCAPED_UNICODE)));

        $body = [
            "apiMethod" => $method,
            "businessParams" => $requestParams,
            "sign" => $sign,
            "timestamp" => $timestamp,
            "username" => $this->config['username']
        ];
        $header = ["Content-Type:application/json"];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
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