<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider\express_query;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\third_party\provider\BaseProvider;

/**
 * 快递100 实时快递查询服务提供者
 *
 * 与 ProviderAliExpress 同为「快递查询(express_query)」能力下的可切换服务商，
 * execute('query', ...) 的入参与返回结构保持一致（{success,data,message}），
 * 因此可在配置中心把默认服务商在 ali_express / kuaidi100 之间自由切换，业务层无需改动。
 *
 * 接口：POST https://poll.kuaidi100.com/poll/query.do
 * 鉴权：form 三字段 customer + sign + param；sign = MD5(param + key + customer) 转 32 位大写。
 * 文档：https://api.kuaidi100.com/document/5f0ffb5ebc8da837cbd8aefc
 *
 * 配置字段（凭证用 api_key 命名以复用配置中心的密钥脱敏机制）：
 *   base_url    接口域名，默认 https://poll.kuaidi100.com
 *   customer    授权码（企业账号 id，非密钥）
 *   api_key     授权 key（参与签名，密钥）
 *   sign_type   签名算法 MD5/SHA256/SM3，默认 MD5
 *   resultv2    返回高级物流状态标记，默认 4
 *   default_com 默认快递公司编码（调用方未传 com 时使用）
 *   timeout     超时秒数
 *
 * @package addon\hsx_recycle\app\service\core\third_party\provider\express_query
 */
class ProviderKuaidi100 extends BaseProvider
{
    private const QUERY_PATH = '/poll/query.do';

    public function getName(): string
    {
        return '快递100快递查询';
    }

    public function getServiceType(): string
    {
        return ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY;
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
     */
    public function execute(string $method, array $params): array
    {
        switch ($method) {
            case 'query':
                return $this->queryExpress($params);
            default:
                throw new \Exception("不支持的方法: {$method}");
        }
    }

    /**
     * 实时查询快递轨迹
     * @param array $params ['express_no'=>单号, 'mobile'=>手机号(顺丰必填), 'com'=>快递公司编码(可选)]
     * @return array {success, data, message}
     */
    protected function queryExpress(array $params): array
    {
        $num = (string)($params['express_no'] ?? $params['num'] ?? '');
        $phone = (string)($params['mobile'] ?? $params['phone'] ?? '');
        $com = (string)($params['com'] ?? $params['company'] ?? $this->getConfig('default_com', ''));

        if ($num === '') {
            throw new \Exception('快递单号不能为空');
        }

        $baseUrl = (string)$this->getConfig('base_url', 'https://poll.kuaidi100.com');
        $customer = (string)$this->getConfig('customer', '');
        $key = (string)$this->getConfig('api_key', '');

        if ($baseUrl === '' || $customer === '' || $key === '') {
            throw new \Exception('快递100查询配置不完整');
        }

        // param 必须是「字符串化的 JSON」，且签名基于这个字符串本身
        $paramData = [
            'com' => $com,
            'num' => $num,
            'phone' => $phone,
            'resultv2' => (string)$this->getConfig('resultv2', '4'),
        ];
        if ($com === '') {
            unset($paramData['com']);
        }
        if ($phone === '') {
            unset($paramData['phone']);
        }
        $paramJson = json_encode($paramData, JSON_UNESCAPED_UNICODE);

        $signType = strtoupper((string)$this->getConfig('sign_type', 'MD5'));
        $sign = $this->makeSign($paramJson, $key, $customer, $signType);

        $form = [
            'customer' => $customer,
            'sign' => $sign,
            'param' => $paramJson,
        ];
        if ($signType !== 'MD5') {
            $form['signType'] = $signType;
        }

        try {
            $url = rtrim($baseUrl, '/') . self::QUERY_PATH;
            $result = $this->httpPostForm($url, $form);

            // 失败响应形如 {"result":false,"returnCode":"503","message":"..."}
            $failed = (isset($result['result']) && $result['result'] === false)
                || (isset($result['returnCode']) && (string)$result['returnCode'] !== '200' && empty($result['data']));
            if ($failed) {
                return [
                    'success' => false,
                    'data' => $result,
                    'message' => (string)($result['message'] ?? '查询失败'),
                ];
            }

            return [
                'success' => true,
                'data' => $result,
                'message' => '查询成功',
            ];
        } catch (\Exception $e) {
            $this->logError('快递100查询失败', [
                'num' => $num,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'data' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 按 signType 生成签名：原文 = param + key + customer，结果转大写。
     * @param string $paramJson
     * @param string $key
     * @param string $customer
     * @param string $signType
     * @return string
     */
    private function makeSign(string $paramJson, string $key, string $customer, string $signType): string
    {
        $raw = $paramJson . $key . $customer;
        switch ($signType) {
            case 'SHA256':
                return strtoupper(hash('sha256', $raw));
            case 'SM3':
                // PHP 未内置 SM3，无扩展时回退 MD5，避免抛错；如需 SM3 请在服务器启用相应扩展后扩展此处
                return in_array('sm3', hash_algos(), true)
                    ? strtoupper(hash('sm3', $raw))
                    : strtoupper(md5($raw));
            case 'MD5':
            default:
                return strtoupper(md5($raw));
        }
    }

    /**
     * 获取余额（快递100查询按量计费，无标准余额查询，返回 0）
     * @return float
     */
    public function getBalance(): float
    {
        return 0.0;
    }

    /**
     * 健康检查：凭证齐全即视为可用
     * @return bool
     */
    public function healthCheck(): bool
    {
        return (string)$this->getConfig('base_url', '') !== ''
            && (string)$this->getConfig('customer', '') !== ''
            && (string)$this->getConfig('api_key', '') !== '';
    }
}
