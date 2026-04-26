<?php

namespace addon\recycle\app\service\core\delivery;

use Exception;
use think\facade\Log;

/**
 * 安果ERP快递渠道
 */
class Anguo extends BaseDelivery
{
    protected $config;
    protected $baseUrl = 'http://115.190.35.168:3000';

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
     * @return array
     * 2026/01/20
     * author:Anguo
     */
    public function preOrder($params)
    {
        // 安果ERP暂不支持预询价接口，直接返回空数组
        // 后续如果接口添加了询价功能，可以在此处实现
        Log::write('===安果ERP暂不支持预询价功能===');
        return [];
    }

    /**
     * @Notes:发送订单
     * sendOrder
     * @param $params
     * @return array
     * 2026/01/20
     * author:Anguo
     */
    public function sendOrder($params)
    {
        try {
            // 参数校验
            $validateResult = $this->validateOrderParams($params);
            if ($validateResult !== true) {
                return ['type' => 'error', 'msg' => $validateResult];
            }

            // 获取有效的预约时间
            // $sendStartTime = $this->getSendStartTime();

            // 参数映射：系统参数 -> 安果ERP参数
            $data = [
                'senderName' => $params['senderName'],
                'senderPhone' => $params['senderMobile'],
                'senderProvince' => $params['senderProvince'],
                'senderCity' => $params['senderCity'],
                'senderArea' => $params['senderDistrict'],
                'senderAddress' => $params['senderAddress'],

                'recipientName' => $params['receiveName'],
                'recipientPhone' => $params['receiveMobile'],
                'recipientProvince' => $params['receiveProvince'],
                'recipientCity' => $params['receiveCity'],
                'recipientArea' => $params['receiveDistrict'],
                'recipientAddress' => $params['receiveAddress'],

                'weight' => $params['weight'],
                'remark' => $params['remark'] ?? '',
                'expressCompanyId' => $this->getExpressCompanyId($params['deliveryType'] ?? ''),
                'sendStartTime' =>  $sendStartTime['sendStartTime'],
            ];

            Log::write('===安果ERP开始下单===' . date('Y-m-d H:i:s'));
            Log::write(['request_data' => $data]);

            $resInfo = $this->execute('POST', '/api/waybill/sender', $data);

            // 响应结果校验
            if (!isset($resInfo['code'])) {
                Log::write('===安果ERP响应格式异常===');
                Log::write($resInfo);
                return ['type' => 'error', 'msg' => '安果ERP返回数据格式异常，请检查接口配置'];
            }

            if ($resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '安果ERP下单失败，未返回错误信息';
                Log::write('===安果ERP发单ERROR===' . date('Y-m-d H:i:s'));
                Log::write(['code' => $resInfo['code'], 'message' => $errorMsg]);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            // 校验返回数据完整性
            if (!isset($resInfo['data']['trackingNumber']) || empty($resInfo['data']['trackingNumber'])) {
                Log::write('===安果ERP返回运单号为空===');
                Log::write($resInfo);
                return ['type' => 'error', 'msg' => '安果ERP下单成功但未返回运单号，请联系客服'];
            }

            Log::write('===安果ERP发单成功===' . date('Y-m-d H:i:s'));
            Log::write(['trackingNumber' => $resInfo['data']['trackingNumber']]);

            $res = [
                'orderNo' => $resInfo['data']['trackingNumber'],
                'deliveryId' => $resInfo['data']['trackingNumber'],
            ];
            return $res;

        } catch (Exception $e) {
            Log::write('===安果ERP下单异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '下单请求异常：' . $e->getMessage()];
        }
    }
    /**
     * @Notes:收件人模式下单
     * sendOrderByRecipient
     * @param $params
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function sendOrderByRecipient($params)
    {
        try {
            // 参数校验
            $validateResult = $this->validateOrderParams($params);
            if ($validateResult !== true) {
                return ['type' => 'error', 'msg' => $validateResult];
            }

            // 获取有效的预约时间
            $sendStartTime = $this->getSendStartTime();

            // 参数映射：系统参数 -> 安果ERP参数
            $data = [
                'senderName' => $params['senderName'],
                'senderPhone' => $params['senderMobile'],
                'senderProvince' => $params['senderProvince'],
                'senderCity' => $params['senderCity'],
                'senderArea' => $params['senderDistrict'],
                'senderAddress' => $params['senderAddress'],

                'recipientName' => $params['receiveName'],
                'recipientPhone' => $params['receiveMobile'],
                'recipientProvince' => $params['receiveProvince'],
                'recipientCity' => $params['receiveCity'],
                'recipientArea' => $params['receiveDistrict'],
                'recipientAddress' => $params['receiveAddress'],

                'weight' => $params['weight'],
                'remark' => $params['remark'] ?? '',
                'expressCompanyId' => $this->getExpressCompanyId($params['deliveryType'] ?? ''),
                'sendStartTime' => $sendStartTime['sendStartTime'],
            ];

            Log::write('===安果ERP开始收件人模式下单===' . date('Y-m-d H:i:s'));
            Log::write(['request_data' => $data]);

            $resInfo = $this->execute('POST', '/api/waybill/recipient', $data);

            // 响应结果校验
            if (!isset($resInfo['code'])) {
                Log::write('===安果ERP响应格式异常===');
                Log::write($resInfo);
                return ['type' => 'error', 'msg' => '安果ERP返回数据格式异常，请检查接口配置'];
            }

            if ($resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '安果ERP下单失败，未返回错误信息';
                Log::write('===安果ERP收件人模式下单ERROR===' . date('Y-m-d H:i:s'));
                Log::write(['code' => $resInfo['code'], 'message' => $errorMsg]);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            // 校验返回数据完整性
            if (!isset($resInfo['data']['trackingNumber']) || empty($resInfo['data']['trackingNumber'])) {
                Log::write('===安果ERP返回运单号为空===');
                Log::write($resInfo);
                return ['type' => 'error', 'msg' => '安果ERP下单成功但未返回运单号，请联系客服'];
            }

            Log::write('===安果ERP收件人模式下单成功===' . date('Y-m-d H:i:s'));
            Log::write(['trackingNumber' => $resInfo['data']['trackingNumber']]);

            $res = [
                'orderNo' => $resInfo['data']['trackingNumber'],
                'deliveryId' => $resInfo['data']['trackingNumber'],
            ];
            return $res;

        } catch (Exception $e) {
            Log::write('===安果ERP收件人模式下单异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '下单请求异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:获取单个面单
     * getNoodle
     * @param string $trackingNumber 运单号
     * @param int $type 面单类型 1-默认
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getNoodle($trackingNumber, $type = 1)
    {
        try {
            if (empty($trackingNumber)) {
                return ['type' => 'error', 'msg' => '运单号不能为空'];
            }

            $data = [
                'trackingNumber' => $trackingNumber,
                'expressCompanyId' => $this->config['express_company_id'] ?? 2,
                'type' => $type
            ];

            Log::write('===安果ERP开始获取面单===' . $trackingNumber);

            $resInfo = $this->execute('POST', '/api/noodle/get', $data);

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '获取面单失败';
                Log::write('===安果ERP获取面单失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP获取面单成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP获取面单异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '获取面单异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:批量获取面单
     * getBatchNoodles
     * @param array $trackingNumbers 运单号数组
     * @param int $type 面单类型 1-默认
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getBatchNoodles($trackingNumbers, $type = 1)
    {
        try {
            if (empty($trackingNumbers) || !is_array($trackingNumbers)) {
                return ['type' => 'error', 'msg' => '运单号数组不能为空'];
            }

            $data = [
                'trackingNumbers' => $trackingNumbers,
                'expressCompanyId' => $this->config['express_company_id'] ?? 2,
                'type' => $type
            ];

            Log::write('===安果ERP开始批量获取面单===' . count($trackingNumbers) . '个');

            $resInfo = $this->execute('POST', '/api/noodle/batch', $data);

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '批量获取面单失败';
                Log::write('===安果ERP批量获取面单失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP批量获取面单成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP批量获取面单异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '批量获取面单异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:查询运单列表
     * getOrderList
     * @param array $params 查询参数
     *   - trackingNumber: 运单号（精确查询）
     *   - status: 订单状态 1-待取件 2-运输中 3-已签收 4-已取消 5-异常
     *   - page: 页码，默认1
     *   - pageSize: 每页数量，默认10
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getOrderList($params = [])
    {
        try {
            $data = [
                'page' => $params['page'] ?? 1,
                'pageSize' => $params['pageSize'] ?? 10,
            ];

            // 可选参数
            if (isset($params['trackingNumber']) && !empty($params['trackingNumber'])) {
                $data['trackingNumber'] = $params['trackingNumber'];
            }

            if (isset($params['status']) && in_array($params['status'], [1, 2, 3, 4, 5])) {
                $data['status'] = $params['status'];
            }

            Log::write('===安果ERP开始查询运单列表===');

            $resInfo = $this->execute('GET', '/api/query/list', $data);

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '查询运单列表失败';
                Log::write('===安果ERP查询运单列表失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP查询运单列表成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP查询运单列表异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '查询运单列表异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:查询单个运单详情
     * getOrderDetail
     * @param string $trackingNumber 运单号
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getOrderDetail($trackingNumber)
    {
        try {
            if (empty($trackingNumber)) {
                return ['type' => 'error', 'msg' => '运单号不能为空'];
            }

            Log::write('===安果ERP开始查询运单详情===' . $trackingNumber);

            $resInfo = $this->execute('GET', "/api/query/detail/{$trackingNumber}");

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '查询运单详情失败';
                Log::write('===安果ERP查询运单详情失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP查询运单详情成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP查询运单详情异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '查询运单详情异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:获取订单状态枚举
     * getOrderStatus
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getOrderStatus()
    {
        try {
            Log::write('===安果ERP开始获取订单状态枚举===');

            $resInfo = $this->execute('GET', '/api/query/status');

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '获取订单状态枚举失败';
                Log::write('===安果ERP获取订单状态枚举失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP获取订单状态枚举成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP获取订单状态枚举异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '获取订单状态枚举异常：' . $e->getMessage()];
        }
    }



    /**
     * @Notes:订单回调
     * callbackOrder
     * @param $data
     * @return void
     * 2026/01/20
     * author:Anguo
     */
    public function callbackOrder($data)
    {
        // 安果ERP采用主动查询模式，无回调机制
        // 预留接口，后续如果支持webhook可在此处实现
        Log::write('===安果ERP订单回调（当前不支持）===');
    }

    /**
     * @Notes:取消订单(根据运单号) ✅
     * cancelOrder
     * @param $data
     * @return array
     * 2026/01/20
     * author:Anguo
     */
    public function cancelOrder($data)
    {
        try {
            $trackingNumber = $data['order_no'] ?? $data['tracking_number'] ?? '';

            if (empty($trackingNumber)) {
                return [
                    'code' => 400,
                    'msg' => '运单号不能为空',
                    'data' => false
                ];
            }

            Log::write('===安果ERP开始取消订单===' . $trackingNumber);

            $resInfo = $this->execute('POST', "/api/query/cancel-by-tracking/{$trackingNumber}");

            // 响应结果校验
            if (!isset($resInfo['code'])) {
                Log::write('===安果ERP取消订单响应格式异常===');
                Log::write($resInfo);
                return [
                    'code' => 500,
                    'msg' => '安果ERP返回数据格式异常',
                    'data' => false
                ];
            }

            if ($resInfo['code'] == 0) {
                Log::write('===安果ERP取消订单成功===');
                return [
                    'code' => 200,
                    'msg' => $resInfo['message'] ?? '取消成功',
                    'data' => $resInfo['data']['success'] ?? true
                ];
            }

            $errorMsg = $resInfo['message'] ?? '取消失败，未返回错误信息';
            Log::write('===安果ERP取消订单失败===' . $errorMsg);

            return [
                'code' => 500,
                'msg' => $errorMsg,
                'data' => false
            ];

        } catch (Exception $e) {
            Log::write('===安果ERP取消订单异常===' . $e->getMessage());
            return [
                'code' => 500,
                'msg' => '取消订单请求异常：' . $e->getMessage(),
                'data' => false
            ];
        }
    }

    /**
     * @Notes:轨迹查询 ✅
     * deliveryTrance
     * @param $params
     * @return array
     * 2026/01/20
     * author:Anguo
     */
    public function deliveryTrance($params)
    {
        try {
            $trackingNumber = $params['delivery_id'] ?? $params['order_no'] ?? '';

            if (empty($trackingNumber)) {
                Log::write('===安果ERP轨迹查询：运单号为空===');
                return [];
            }

            Log::write('===安果ERP开始查询轨迹===' . $trackingNumber);

            $resInfo = $this->execute('GET', "/api/query/log/{$trackingNumber}");

            // 响应结果校验
            if (!isset($resInfo['code'])) {
                Log::write('===安果ERP轨迹查询响应格式异常===');
                Log::write($resInfo);
                return [];
            }

            if ($resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '查询失败';
                Log::write('===安果ERP轨迹查询失败===' . $errorMsg);
                return [];
            }

            // 校验data字段
            if (!isset($resInfo['data']) || !is_array($resInfo['data'])) {
                Log::write('===安果ERP轨迹数据格式异常===');
                Log::write($resInfo);
                return [];
            }

            // 转换为系统需要的格式
            $traces = [];
            foreach ($resInfo['data'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $traces[] = [
                    'time' => $item['add_time'] ?? '',
                    'status' => $item['title'] ?? '',
                    'context' => $item['content'] ?? '',
                ];
            }

            Log::write('===安果ERP轨迹查询成功，共' . count($traces) . '条记录===');
            return $traces;

        } catch (Exception $e) {
            Log::write('===安果ERP轨迹查询异常===' . $e->getMessage());
            return [];
        }
    }

    /**
     * @Notes:拦截订单（根据订单ID）
     * interceptOrder
     * @param int $orderId 订单ID
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function interceptOrder($orderId)
    {
        try {
            if (empty($orderId)) {
                return ['type' => 'error', 'msg' => '订单ID不能为空'];
            }

            Log::write('===安果ERP开始拦截订单===' . $orderId);

            $resInfo = $this->execute('POST', "/api/query/intercept/{$orderId}");

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '拦截订单失败';
                Log::write('===安果ERP拦截订单失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP拦截订单成功===');
            return [
                'code' => 200,
                'msg' => $resInfo['message'] ?? '拦截成功',
                'data' => $resInfo['data'] ?? []
            ];

        } catch (Exception $e) {
            Log::write('===安果ERP拦截订单异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '拦截订单异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:获取所有订单列表（按状态）
     * getAllOrders
     * @param array $params 查询参数
     *   - status: 订单状态（必填） 1-待取件 2-运输中 3-已签收 4-已取消 5-异常/拦截中
     *   - page: 页码，默认1
     *   - pageSize: 每页数量，默认10
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getAllOrders($params = [])
    {
        try {
            // 状态为必填参数
            if (!isset($params['status']) || !in_array($params['status'], [1, 2, 3, 4, 5])) {
                return ['type' => 'error', 'msg' => '订单状态为必填参数，可选值：1-待取件 2-运输中 3-已签收 4-已取消 5-异常/拦截中'];
            }

            $data = [
                'status' => $params['status'],
                'page' => $params['page'] ?? 1,
                'pageSize' => $params['pageSize'] ?? 10,
            ];

            Log::write('===安果ERP开始获取所有订单列表===');
            Log::write(['status' => $data['status'], 'page' => $data['page']]);

            $resInfo = $this->execute('GET', '/api/query/all', $data);

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '获取订单列表失败';
                Log::write('===安果ERP获取所有订单列表失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP获取所有订单列表成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP获取所有订单列表异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '获取订单列表异常：' . $e->getMessage()];
        }
    }

    /**
     * @Notes:获取扣费详情
     * getFundDetails
     * @param array $params 查询参数
     *   - page: 页码，默认1
     *   - pageSize: 每页数量，默认10
     * @return array
     * 2026/01/21
     * author:Anguo
     */
    public function getFundDetails($params = [])
    {
        try {
            $data = [
                'page' => $params['page'] ?? 1,
                'pageSize' => $params['pageSize'] ?? 10,
            ];

            Log::write('===安果ERP开始获取扣费详情===');

            $resInfo = $this->execute('GET', '/api/query/fund', $data);

            if (!isset($resInfo['code']) || $resInfo['code'] != 0) {
                $errorMsg = $resInfo['message'] ?? '获取扣费详情失败';
                Log::write('===安果ERP获取扣费详情失败===' . $errorMsg);
                return ['type' => 'error', 'msg' => $errorMsg];
            }

            Log::write('===安果ERP获取扣费详情成功===');
            return $resInfo['data'] ?? [];

        } catch (Exception $e) {
            Log::write('===安果ERP获取扣费详情异常===' . $e->getMessage());
            return ['type' => 'error', 'msg' => '获取扣费详情异常：' . $e->getMessage()];
        }
    } 
    /**
     * @Notes:获取余额
     * getBalance
     * @return string
     * 2026/01/20
     * author:Anguo
     */
    public function getBalance()
    {
        // 安果ERP暂不支持余额查询接口
        // 预留方法，后续如果接口添加余额查询功能，可在此处实现
        return '安果ERP暂不支持余额查询，请联系服务商';
    }

    /**
     * @Notes:统一API请求方法
     * execute
     * @param string $method HTTP方法 GET/POST
     * @param string $endpoint API端点
     * @param array $data 请求数据
     * @return array
     * @throws Exception
     * 2026/01/20
     * author:Anguo
     */
    protected function execute($method, $endpoint, $data = [])
    {
        $url = ($this->config['base_url'] ?? $this->baseUrl) . $endpoint;

        $curl = curl_init();

        // GET请求参数拼接到URL
        if ($method == 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config['timeout'] ?? 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);

        // 设置请求头（包含Authorization）
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: afdd0b4ad2ec172c586e2150770fbf9e'
        ];

        // POST请求设置
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        // 网络错误处理
        if ($err) {
            Log::write("===安果ERP API网络请求失败==={$method} {$endpoint}");
            Log::write(['error' => $err]);
            throw new Exception("网络请求失败：{$err}");
        }

        // HTTP状态码检查
        if ($httpCode != 200) {
            Log::write("===安果ERP API返回HTTP状态码异常==={$httpCode}");
            Log::write(['url' => $url, 'response' => $result]);
            throw new Exception("HTTP状态码异常：{$httpCode}");
        }

        // JSON解析
        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::write("===安果ERP API响应JSON解析失败===");
            Log::write(['error' => json_last_error_msg(), 'response' => $result]);
            throw new Exception("响应JSON解析失败：" . json_last_error_msg());
        }

        return $response;
    }

    /**
     * @Notes:获取有效的预约时间 ✅
     * getSendStartTime
     * @return string
     * 2026/01/20
     * author:Anguo
     */
    public function getSendStartTime()
    {

        try {
            $result = $this->execute('GET', '/api/waybill/time');

            if (isset($result['code']) && $result['code'] == 0 && isset($result['data']['sendStartTime'])) {
                Log::write('===获取预约时间成功===' . $result['data']['sendStartTime']);
                return $result['data'];
            }

            Log::write('===获取预约时间失败，使用降级方案===');
        } catch (Exception $e) {
            Log::write('===获取预约时间异常，使用降级方案===' . $e->getMessage());
        }

        // 降级方案：返回当前时间+2小时
        $fallbackTime = date('Y-m-d H:i:s', strtotime('+2 hours'));
        Log::write('===降级预约时间===' . $fallbackTime);
        return $fallbackTime;
    }

    /**
     * @Notes:将快递公司代码转换为安果ERP的ID
     * getExpressCompanyId
     * @param string $deliveryType
     * @return int
     * 2026/01/20
     * author:Anguo
     */
    protected function getExpressCompanyId($deliveryType)
    {
        // 优先使用配置中的快递公司ID
        if (isset($this->config['express_company_id'])) {
            return $this->config['express_company_id'];
        }

        // 快递公司映射表（可通过配置文件扩展）
        $mapping = $this->config['express_company_mapping'] ?? [
            'SF' => 2,      // 顺丰
           
            // 后续可通过配置文件补充更多快递公司映射
        ];

        // 根据快递公司代码映射
        $companyId = $mapping[$deliveryType] ?? 2; // 默认顺丰

        if (!isset($mapping[$deliveryType])) {
            Log::write("===未找到快递公司[{$deliveryType}]的映射，使用默认值2（顺丰）===");
        }

        return $companyId;
    }

    /**
     * @Notes:校验下单参数
     * validateOrderParams
     * @param array $params
     * @return true|string true表示通过，string表示错误信息
     * 2026/01/20
     * author:Anguo
     */
    protected function validateOrderParams($params)
    {
        $requiredFields = [
            'senderName' => '寄件人姓名',
            'senderMobile' => '寄件人电话',
            'senderProvince' => '寄件人省份',
            'senderCity' => '寄件人城市',
            'senderDistrict' => '寄件人区县',
            'senderAddress' => '寄件人详细地址',
            'receiveName' => '收件人姓名',
            'receiveMobile' => '收件人电话',
            'receiveProvince' => '收件人省份',
            'receiveCity' => '收件人城市',
            'receiveDistrict' => '收件人区县',
            'receiveAddress' => '收件人详细地址',
            'weight' => '重量',
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($params[$field]) || $params[$field] === '') {
                return "{$label}不能为空";
            }
        }

        // 重量校验
        if (!is_numeric($params['weight']) || $params['weight'] <= 0) {
            return '重量必须为大于0的数字';
        }

        return true;
    }
}
