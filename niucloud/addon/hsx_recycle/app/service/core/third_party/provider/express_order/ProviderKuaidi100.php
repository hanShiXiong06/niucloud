<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider\express_order;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\third_party\provider\BaseProvider;
use Exception;

/**
 * 快递100 电子面单下单服务提供者
 *
 * 与 ProviderYisu 同为「快递发件(express_order)」能力下的可切换服务商。
 * execute() 的方法名与返回 envelope 与 ProviderYisu 对齐，可在配置中心把默认服务商
 * 在 yisu / kuaidi100 之间切换，ExpressOrderService 业务层无需改动。
 *
 * 支持的子能力：create(下单)、cancel(取消)。
 * 不支持：quote(预估)、detail(轨迹查询，请用「快递查询」能力)、waybillPdf、fund —— 调用时明确抛出，便于上层按能力降级。
 *
 * 接口：POST https://api.kuaidi100.com/label/order
 * 鉴权：form 字段 method + key + t + sign + param；sign = MD5(param + t + key + secret) 转大写。
 * 文档：https://api.kuaidi100.com/document/dianzimiandanV2
 *
 * 配置字段（凭证用 api_key/secret 命名以复用配置中心的密钥脱敏机制）：
 *   base_url          接口域名，默认 https://api.kuaidi100.com
 *   api_key           授权 key（参与签名，密钥）
 *   secret            授权 secret（参与签名，密钥）
 *   default_kuaidicom 默认快递公司编码（调用方未传 kuaidicom 时使用）
 *   tempId            面单模板 id
 *   printType         打印方式 IMAGE/HTML/CLOUD，默认 IMAGE
 *   siid              云打印机设备码（printType=CLOUD 时必填）
 *   pay_type          付款方式，默认 SHIPPER（寄付）
 *   timeout           超时秒数
 *
 * @package addon\hsx_recycle\app\service\core\third_party\provider\express_order
 */
class ProviderKuaidi100 extends BaseProvider
{
    private const ORDER_PATH = '/label/order';

    private const METHOD_ALIASES = [
        'sendOrder' => 'create',
        'cancelOrder' => 'cancel',
    ];

    public function getName(): string
    {
        return '快递100电子面单';
    }

    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER;
    }

    public function getProviderName(): string
    {
        return ThirdPartyDict::PROVIDER_KUAIDI100;
    }

    /**
     * 执行调用
     * @param string $method
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function execute(string $method, array $params): array
    {
        $method = self::METHOD_ALIASES[$method] ?? $method;

        switch ($method) {
            case 'create':
                return $this->createOrder($params);
            case 'cancel':
                return $this->cancelOrder($params);
            case 'quote':
            case 'detail':
            case 'waybillPdf':
            case 'fund':
                throw new Exception("快递100暂不支持「{$method}」，请改用其它服务商或对应能力");
            default:
                throw new Exception("不支持的方法: {$method}");
        }
    }

    /**
     * 电子面单下单
     * @param array $params 与 ProviderYisu::create 一致的发件参数
     * @return array {success, data:{orderNo,deliveryId,waybillNo,label,raw}, raw_data, message}
     * @throws Exception
     */
    private function createOrder(array $params): array
    {
        $kuaidicom = (string)($params['kuaidicom'] ?? $this->getConfig('default_kuaidicom', ''));
        if ($kuaidicom === '') {
            throw new Exception('未指定快递公司编码(kuaidicom)');
        }

        $paramData = [
            'kuaidicom' => $kuaidicom,
            'recMan' => [
                'name' => (string)($params['receiveName'] ?? ''),
                'mobile' => (string)($params['receiveMobile'] ?? ''),
                'printAddr' => $this->joinAddress($params, 'receive'),
            ],
            'sendMan' => [
                'name' => (string)($params['senderName'] ?? ''),
                'mobile' => (string)($params['senderMobile'] ?? ''),
                'printAddr' => $this->joinAddress($params, 'sender'),
            ],
            'cargo' => (string)($params['goods'] ?? '回收设备'),
            'count' => (int)($params['packageCount'] ?? 1),
            'payType' => (string)($params['payType'] ?? $this->getConfig('pay_type', 'SHIPPER')),
            'printType' => (string)$this->getConfig('printType', 'IMAGE'),
            'tempId' => (string)$this->getConfig('tempId', ''),
            'remark' => (string)($params['remark'] ?? ''),
        ];

        $weight = $params['weight'] ?? '';
        if ($weight !== '' && $weight !== null) {
            $paramData['weight'] = (float)$weight;
        }
        $siid = (string)$this->getConfig('siid', '');
        if ($paramData['printType'] === 'CLOUD' && $siid !== '') {
            $paramData['siid'] = $siid;
        }

        $result = $this->labelOrderRequest('order', $paramData);

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $waybillNo = (string)($data['kuaidinum'] ?? '');

        return [
            'success' => true,
            'data' => [
                'orderNo' => (string)($data['orderId'] ?? $data['taskId'] ?? ($params['thirdOrderNo'] ?? '')),
                'deliveryId' => $waybillNo,
                'waybillNo' => $waybillNo,
                'label' => (string)($data['label'] ?? ''),
                'raw' => $data,
            ],
            'raw_data' => $result,
            'message' => '下单成功',
        ];
    }

    /**
     * 电子面单取消
     * @param array $params ['taskId'|'orderNo' 或 'waybillNo'/'delivery_id'/'express_no']
     * @return array
     * @throws Exception
     */
    private function cancelOrder(array $params): array
    {
        $taskId = (string)($params['taskId'] ?? $params['orderNo'] ?? $params['order_no'] ?? '');
        $waybillNo = (string)($params['waybillNo'] ?? $params['delivery_id'] ?? $params['express_no'] ?? '');
        $kuaidicom = (string)($params['kuaidicom'] ?? $this->getConfig('default_kuaidicom', ''));

        if ($taskId === '' && $waybillNo === '') {
            throw new Exception('取消运单需提供 taskId 或运单号');
        }

        $paramData = array_filter([
            'taskId' => $taskId,
            'kuaidinum' => $waybillNo,
            'kuaidicom' => $kuaidicom,
        ], static fn($v) => $v !== '');

        $result = $this->labelOrderRequest('cancel', $paramData);

        return [
            'success' => true,
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'raw_data' => $result,
            'message' => '取消/拦截成功',
        ];
    }

    /**
     * 调用 label/order 接口（method 区分下单/取消），统一签名与失败判定。
     * @param string $method
     * @param array $paramData
     * @return array
     * @throws Exception
     */
    private function labelOrderRequest(string $method, array $paramData): array
    {
        $baseUrl = (string)$this->getConfig('base_url', 'https://api.kuaidi100.com');
        $key = (string)$this->getConfig('api_key', '');
        $secret = (string)$this->getConfig('secret', '');

        if ($baseUrl === '' || $key === '' || $secret === '') {
            throw new Exception('快递100电子面单配置不完整');
        }

        $paramJson = json_encode($paramData, JSON_UNESCAPED_UNICODE);
        $t = (string)((int)round(microtime(true) * 1000));
        $sign = strtoupper(md5($paramJson . $t . $key . $secret));

        $form = [
            'method' => $method,
            'key' => $key,
            't' => $t,
            'sign' => $sign,
            'param' => $paramJson,
        ];

        $url = rtrim($baseUrl, '/') . self::ORDER_PATH;
        $result = $this->httpPostForm($url, $form);

        $ok = (isset($result['result']) && $result['result'] === true)
            || (isset($result['returnCode']) && (string)$result['returnCode'] === '200');
        if (!$ok) {
            throw new Exception((string)($result['message'] ?? '快递100接口调用失败'));
        }

        return $result;
    }

    /**
     * 拼接省市区+详细地址
     * @param array $params
     * @param string $role sender|receive
     * @return string
     */
    private function joinAddress(array $params, string $role): string
    {
        return (string)($params[$role . 'Province'] ?? '')
            . (string)($params[$role . 'City'] ?? '')
            . (string)($params[$role . 'District'] ?? '')
            . (string)($params[$role . 'Address'] ?? '');
    }

    /**
     * 获取余额（电子面单按量计费，无标准余额接口，返回 0）
     * @return float
     */
    public function getBalance(): float
    {
        return 0.0;
    }

    /**
     * 健康检查
     * @return bool
     */
    public function healthCheck(): bool
    {
        return (string)$this->getConfig('base_url', '') !== ''
            && (string)$this->getConfig('api_key', '') !== ''
            && (string)$this->getConfig('secret', '') !== '';
    }
}
