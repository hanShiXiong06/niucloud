<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider\delivery;

use addon\hsx_recycle\app\service\core\third_party\provider\BaseProvider;
use Exception;

/**
 * 易速快递服务提供者
 * Class ProviderYisu
 * @package addon\hsx_recycle\app\service\core\third_party\provider\delivery
 */
class ProviderYisu extends BaseProvider
{
    /**
     * 执行API调用
     * @param string $method 方法名
     * @param array $params 参数
     * @return array
     * @throws Exception
     */
    public function execute(string $method, array $params): array
    {
        $config = $this->config;
        $baseUrl = $config['base_url'] ?? 'http://open.yisuopen.com';
        $appid = $config['appid'] ?? '';
        $appSecret = $config['app_secret'] ?? '';
        $version = $config['version'] ?? 'V1.0';

        // 根据方法名映射到API端点
        $apiMap = [
            'preOrder' => '/openApi/getPriceList',      // 预下单（获取报价）
            'sendOrder' => '/openApi/doOrder',          // 下单
            'cancelOrder' => '/openApi/doCancel',       // 取消订单
            'track' => '/openApi/getOrderDetail',       // 轨迹查询
            'balance' => '/openApi/fund',               // 余额查询
        ];

        if (!isset($apiMap[$method])) {
            throw new Exception("不支持的方法: {$method}");
        }

        $url = $apiMap[$method];

        // 准备请求数据
        $requestData = $this->prepareRequestData($method, $params);

        // 执行请求
        $result = $this->httpRequest($baseUrl . $url, $requestData, $appid, $version, $appSecret);

        // 处理响应
        return $this->handleResponse($method, $result);
    }

    /**
     * 准备请求数据
     * @param string $method
     * @param array $params
     * @return array
     */
    private function prepareRequestData(string $method, array $params): array
    {
        switch ($method) {
            case 'preOrder':
                // 预下单参数
                $expressType = 1;
                if (($params['weight'] ?? 0) > 30 || ($params['customerType'] ?? '') == 'ky') {
                    $expressType = 2;
                }
                return [
                    'sendPhone' => $params['senderMobile'] ?? '18050000000',
                    'sendAddress' => ($params['senderProvince'] ?? '') . ($params['senderCity'] ?? '') .
                                   ($params['senderDistrict'] ?? '') . ($params['senderAddress'] ?? ''),
                    'receiveAddress' => ($params['receiveProvince'] ?? '') . ($params['receiveCity'] ?? '') .
                                      ($params['receiveDistrict'] ?? '') . ($params['receiveAddress'] ?? ''),
                    'packageNum' => $params['packageCount'] ?? 1,
                    'goodsValue' => (int)($params['guaranteeValueAmount'] ?? 0),
                    'weight' => $params['weight'] ?? 1,
                    'length' => $params['vloumLong'] ?? 0,
                    'width' => $params['vloumWidth'] ?? 0,
                    'height' => $params['vloumHeight'] ?? 0,
                    'payMethod' => 3,
                    'expressType' => $expressType,
                ];

            case 'sendOrder':
                // 下单参数
                return [
                    'productCode' => $params['deliveryType'] ?? '',
                    'senderPhone' => $params['senderMobile'] ?? '',
                    'senderName' => $params['senderName'] ?? '',
                    'guaranteeValueAmount' => $params['guaranteeValueAmount'] ?? 0,
                    'senderAddress' => ($params['senderProvince'] ?? '') . ($params['senderCity'] ?? '') .
                                     ($params['senderDistrict'] ?? '') . ($params['senderAddress'] ?? ''),
                    'receiveAddress' => ($params['receiveProvince'] ?? '') . ($params['receiveCity'] ?? '') .
                                      ($params['receiveDistrict'] ?? '') . ($params['receiveAddress'] ?? ''),
                    'receivePhone' => $params['receiveMobile'] ?? '',
                    'receiveName' => $params['receiveName'] ?? '',
                    'goods' => $params['goods'] ?? '',
                    'packageNum' => $params['packageCount'] ?? 1,
                    'volume' => (int)($params['vloumLong'] ?? 0) / 100 * ($params['vloumWidth'] ?? 0) / 100 * ($params['vloumHeight'] ?? 0) / 100,
                    'weight' => $params['weight'] ?? 1,
                ];

            case 'cancelOrder':
                // 取消订单参数
                return [
                    'genre' => 1,
                    'orderNo' => $params['order_no'] ?? '',
                ];

            case 'track':
                // 轨迹查询参数
                return [
                    'waybillNo' => $params['delivery_id'] ?? '',
                ];

            case 'balance':
                // 余额查询无需参数
                return [];

            default:
                return $params;
        }
    }

    /**
     * 处理响应
     * @param string $method
     * @param array $result
     * @return array
     * @throws Exception
     */
    private function handleResponse(string $method, array $result): array
    {
        // 检查响应状态
        if (!isset($result['code'])) {
            throw new Exception('API响应格式错误');
        }

        if ($result['code'] != 0) {
            throw new Exception($result['msg'] ?? 'API调用失败');
        }

        // 根据方法处理响应数据
        switch ($method) {
            case 'preOrder':
                // 返回报价列表
                return [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                    'message' => '获取报价成功',
                ];

            case 'sendOrder':
                // 返回订单信息
                return [
                    'success' => true,
                    'data' => [
                        'orderNo' => $result['data']['orderNo'] ?? '',
                        'deliveryId' => $result['data']['waybillNo'] ?? '',
                    ],
                    'message' => '下单成功',
                ];

            case 'cancelOrder':
                // 返回取消结果
                return [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                    'message' => '取消成功',
                ];

            case 'track':
                // 返回轨迹信息
                $traceList = [];
                if (!empty($result['data']['traceList'])) {
                    foreach ($result['data']['traceList'] as $trace) {
                        $traceList[] = [
                            'time' => $trace['opeTimeAll'] ?? '',
                            'desc' => $trace['opeRemark'] ?? '',
                        ];
                    }
                }
                return [
                    'success' => true,
                    'data' => $traceList,
                    'message' => '查询成功',
                ];

            case 'balance':
                // 返回余额信息
                return [
                    'success' => true,
                    'data' => [
                        'balance' => $result['data']['balance'] ?? 0,
                    ],
                    'balance' => $result['data']['balance'] ?? 0,
                    'message' => '查询成功',
                ];

            default:
                return [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                    'message' => '操作成功',
                ];
        }
    }

    /**
     * HTTP请求
     * @param string $url
     * @param array $data
     * @param string $appid
     * @param string $version
     * @param string $appSecret
     * @return array
     * @throws Exception
     */
    private function httpRequest(string $url, array $data, string $appid, string $version, string $appSecret): array
    {
        $timeStamp = time();
        $sign = $this->generateSign($appid, $version, $timeStamp, $appSecret);

        $headers = [
            'version: ' . $version,
            'appid: ' . $appid,
            'timestamp: ' . $timeStamp,
            'sign: ' . $sign,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config['timeout'] ?? 30);

        $result = curl_exec($curl);

        if ($result === false) {
            $err = curl_error($curl);
            curl_close($curl);
            throw new Exception('Curl Error: ' . $err);
        }

        curl_close($curl);

        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON解析失败: ' . json_last_error_msg());
        }

        return $response;
    }

    /**
     * 生成签名
     * @param string $appid
     * @param string $version
     * @param int $timeStamp
     * @param string $appSecret
     * @return string
     */
    private function generateSign(string $appid, string $version, int $timeStamp, string $appSecret): string
    {
        return md5($appid . $version . $timeStamp . $appSecret);
    }

    /**
     * 获取余额
     * @return float
     */
    public function getBalance(): float
    {
        try {
            $result = $this->execute('balance', []);
            return (float)($result['balance'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 健康检查
     * @return bool
     */
    public function healthCheck(): bool
    {
        // 检查配置是否完整
        $requiredFields = ['base_url', 'appid', 'app_secret'];
        foreach ($requiredFields as $field) {
            if (empty($this->config[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取提供者名称
     * @return string
     */
    public function getName(): string
    {
        return 'yisu';
    }

    /**
     * 获取提供者显示名称
     * @return string
     */
    public function getProviderName(): string
    {
        return '易速快递';
    }
}
