<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider\express_order;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
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
     * 易速开放接口路径。允许在 sys_config 中通过 api_paths 覆盖，避免接口升级时改代码。
     */
    private const API_PATHS = [
        'quote' => '/openApi/getPrice',
        'create' => '/openApi/doOrder',
        'cancel' => '/openApi/doCancel',
        'modify' => '/openApi/doModify',
        'detail' => '/openApi/getOrderDetail',
        'waybillPdf' => '/openApi/getWaybillPdf',
        'fund' => '/openApi/fund',
    ];

    private const METHOD_ALIASES = [
        'preOrder' => 'quote',
        'sendOrder' => 'create',
        'cancelOrder' => 'cancel',
        'track' => 'detail',
        'balance' => 'fund',
    ];

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
        $baseUrl = $config['base_url'] ?? '';
        $appid = $config['appid'] ?? '';
        $appSecret = $config['app_secret'] ?? '';
        $version = $config['version'] ?? 'V1.0';

        if (empty($baseUrl) || empty($appid) || empty($appSecret)) {
            throw new Exception('亿速快递配置不完整');
        }

        $method = self::METHOD_ALIASES[$method] ?? $method;
        $apiMap = array_merge(self::API_PATHS, is_array($config['api_paths'] ?? null) ? $config['api_paths'] : []);

        if (!isset($apiMap[$method])) {
            throw new Exception("不支持的方法: {$method}");
        }

        $url = $apiMap[$method];

        // 准备请求数据
        $requestData = $this->prepareRequestData($method, $params);

        // 执行请求
        $result = $this->yisuHttpRequest($baseUrl . $url, $requestData, $appid, $version, $appSecret);

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
            case 'quote':
                $data = [
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
                    'volume' => $this->calculateVolume($params),
                    'goods' => $params['goods'] ?? '回收设备',
                ];
                $productCode = $this->firstFilledString($params, ['productCode', 'deliveryType']);
                $hasProductCode = $productCode !== '' || array_key_exists('productCode', $params) || array_key_exists('deliveryType', $params);
                if ($hasProductCode) {
                    $data['productCode'] = (int)$productCode;
                }
                return $data;

            case 'create':
                $data = [
                    'productCode' => (int)($params['deliveryType'] ?? $params['productCode'] ?? 0),
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
                    'length' => $params['vloumLong'] ?? 0,
                    'width' => $params['vloumWidth'] ?? 0,
                    'height' => $params['vloumHeight'] ?? 0,
                    'volume' => $this->calculateVolume($params),
                    'weight' => $params['weight'] ?? 1,
                    'payMethod' => $params['payMethod'] ?? 3,
                    'remark' => $params['remark'] ?? '',
                    'thirdOrderNo' => $params['thirdOrderNo'] ?? $params['third_order_no'] ?? $params['recycle_order_no'] ?? '',
                    'orderSendTime' => $params['orderSendTime'] ?? $params['pickup_time'] ?? '',
                ];
                return $data;

            case 'cancel':
                return $this->buildIdentifierParams($params) + [
                    'genre' => (int)($params['genre'] ?? 1),
                ];

            case 'modify':
                return $this->buildIdentifierParams($params) + [
                    'packageNum' => $params['packageNum'] ?? $params['package_count'] ?? '',
                    'orderSendTime' => $params['orderSendTime'] ?? $params['pickup_time'] ?? '',
                ];

            case 'detail':
                return $this->buildIdentifierParams($params);

            case 'waybillPdf':
                return $this->buildIdentifierParams($params) + [
                    'temCode' => (string)($params['temCode'] ?? $params['template_code'] ?? ''),
                ];

            case 'fund':
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
            case 'quote':
                if (!empty($result['data']['errorCode'])) {
                    throw new Exception($result['data']['errorMsg'] ?? $result['data']['remark'] ?? '获取报价失败');
                }
                return [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                    'raw_data' => $result,
                    'message' => '获取报价成功',
                ];

            case 'create':
                return [
                    'success' => true,
                    'data' => [
                        'orderNo' => $result['data']['orderNo'] ?? '',
                        'deliveryId' => $result['data']['waybillNo'] ?? '',
                        'waybillNo' => $result['data']['waybillNo'] ?? '',
                        'raw' => $result['data'] ?? [],
                    ],
                    'raw_data' => $result,
                    'message' => '下单成功',
                ];

            case 'cancel':
            case 'modify':
            case 'waybillPdf':
                return [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                    'raw_data' => $result,
                    'message' => $method === 'cancel' ? '取消/拦截成功' : '操作成功',
                ];

            case 'detail':
                $traceList = [];
                if (!empty($result['data']['traceList'])) {
                    foreach ($result['data']['traceList'] as $trace) {
                        $traceList[] = [
                            'time' => $trace['opeTimeAll'] ?? $trace['time'] ?? '',
                            'desc' => $trace['opeRemark'] ?? $trace['desc'] ?? '',
                            'raw' => $trace,
                        ];
                    }
                }
                return [
                    'success' => true,
                    'data' => array_merge($result['data'] ?? [], ['trace_list' => $traceList]),
                    'trace_list' => $traceList,
                    'raw_data' => $result,
                    'message' => '查询成功',
                ];

            case 'fund':
                return [
                    'success' => true,
                    'data' => [
                        'balance' => $result['data']['balance'] ?? 0,
                        'commission' => $result['data']['commission'] ?? 0,
                        'integral' => $result['data']['integral'] ?? 0,
                    ],
                    'balance' => $result['data']['balance'] ?? 0,
                    'raw_data' => $result,
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
    private function yisuHttpRequest(string $url, array $data, string $appid, string $version, string $appSecret): array
    {
        $timeStamp = (int)round(microtime(true) * 1000);
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

    private function calculateVolume(array $params): float
    {
        if (isset($params['volume']) && (float)$params['volume'] > 0) {
            return (float)$params['volume'];
        }

        $length = (float)($params['vloumLong'] ?? $params['length'] ?? 0);
        $width = (float)($params['vloumWidth'] ?? $params['width'] ?? 0);
        $height = (float)($params['vloumHeight'] ?? $params['height'] ?? 0);

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return 0;
        }

        return round($length / 100 * $width / 100 * $height / 100, 4);
    }

    private function buildIdentifierParams(array $params): array
    {
        $data = [];
        foreach ([
            'thirdOrderNo' => ['thirdOrderNo', 'third_order_no'],
            'waybillNo' => ['waybillNo', 'delivery_id', 'waybill_no', 'express_no'],
            'orderNo' => ['orderNo', 'order_no'],
        ] as $target => $aliases) {
            foreach ($aliases as $alias) {
                if (!empty($params[$alias])) {
                    $data[$target] = $params[$alias];
                    break;
                }
            }
        }

        return $data;
    }

    private function firstFilledString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                return trim((string)$data[$key]);
            }
        }

        return '';
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
        return '亿速快递';
    }

    /**
     * 获取提供者标识
     * @return string
     */
    public function getProviderName(): string
    {
        return ThirdPartyDict::PROVIDER_YISU;
    }

    /**
     * 获取服务类型
     * @return string
     */
    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER;
    }
}
