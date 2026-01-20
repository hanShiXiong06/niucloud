<?php

namespace addon\kd_api\app\api\controller\open;

use core\exception\CommonException;
use think\facade\Log;

class Demo
{
    public function getLink()
    {
        $res = $this->excute('/kdapi/getlink', [
            'sid' => 'tkceshisid45544'//自定义跟单参数
        ]);
        return success($res);
    }

    public function getOrder()
    {
        $res = $this->excute('/kdapi/getorder', [
            'page' => 1,
            'limit' => 10,
            'sid' => '',
            'status' => '',       //1已支付 2已完成 3已取消
            'is_js' => '',        //0未结算 1已结算
            'start_time' => '',   //2025-09-02 14:20:13
            'end_time' => ''      //2025-09-02 14:20:13
        ]);
        return success($res);
    }

    public function excute($url, $data)
    {
        $domain = 'http://niuaddon.saas'; //接口域名
        $pub_id = 2; //接口分配的pub_id
        $api_key = '9833214a4faddd4aa42ff02c4968fb81';//接口分配的api_key
        $api_secret = 'b2ff0fa0c8a0ebdb96cf60fcacae80a9fd0cea97727ae158017e9d41aa0be693';//接口分配的api_secret
        $url = $domain . $url;
        $request_id = uniqid();//自定义请求ID
        $date = strtotime(date('YmdHis'));
        $str = $api_key . $request_id . $date;
        $signature = hash_hmac('sha256', $str, $api_secret, false);
        $payload = json_encode([
            'api_key' => $api_key,
            'timestamp' => $date,
            'sign' => $signature,
            'request_id' => $request_id,
            'content' => $data
        ], JSON_UNESCAPED_UNICODE);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "pub-id: " . $pub_id
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new CommonException('Curl Error: ' . $err);
        }
        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CommonException('Invalid JSON response: ' . json_last_error_msg());
        }
        return $response;
    }
}
