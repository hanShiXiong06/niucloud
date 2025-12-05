<?php
namespace addon\ai_image\app\service\core;

use GuzzleHttp\Client;

class SxfApi
{
  //protected $api_url = 'https://openapi-test.tianquetech.com'; // 测试环境
   protected $api_url = 'https://openapi.tianquetech.com'; // // 生产环境

    protected $publick_key = '';
    protected $private_key = '';

    protected $orgId = '';

    /**
     * api版本
     * @var string
     */
    protected $api_version = '1.0';

    protected $sign_type = 'RSA';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false,
        ]);
    }

    /**
     * 设置
     */
    public function setConfig(array $data) {
        $this->publick_key = $data['public_key'];
        $this->private_key = $data['private_key'];
        $this->orgId = $data['org_id'];
    }
    
    public function httpRun($uri, $data) {
        try {
            $requestUrl = sprintf("%s%s", $this->api_url, $uri);

            $requestData = $this->getRequestData($data);
            $response = $this->client->request('POST', $requestUrl, [
                'json' => $requestData
            ]);
            $responseData = $response->getBody()->getContents();
            $responseData = json_decode($responseData, true);
            return $responseData;
        } catch (\Throwable $th) {
            return ['code' => 500, 'message' => '请求失败:' . $th->getMessage()];
        }
    }

    protected function getRequestData($data) {
        $now = time();
        $requestData = [
            "orgId"     => $this->orgId,
            "reqId"     => strval($now),
            "reqData"   => $data,
            "timestamp" => date("yyyyMMddHHmmss", $now),
            "version"   => "1.0",
            "signType"  => "RSA",
        ];

        // 计算签名
        $sign = $this->getSign($requestData);
        $requestData['sign'] = $sign;
        return $requestData;
    }


    protected function getSign($requestData) {
        $requestDataString = $this->buildData($requestData);

        // 私钥
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($this->private_key, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        // 加密
        if ("RSA2" == $this->sign_type) {
            openssl_sign($requestDataString, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($requestDataString, $sign, $res);
        }

        return base64_encode($sign);
    }


    protected function buildData($params) {
        ksort($params);
        $stringToBeSigned = "";
        foreach ($params as $k => $v) {
            $isarray = is_array($v);
            if ($isarray) {
                $stringToBeSigned .= "$k" . "=" . json_encode($v, 320) . "&";
            } else {
                $stringToBeSigned .= "$k" . "=" . "$v" . "&";
            }
        }
        unset ($k, $v);
        $stringToBeSigned = substr($stringToBeSigned, 0, strlen($stringToBeSigned) - 1);
        return $stringToBeSigned;
    }

    // ====================================================================================================================
}