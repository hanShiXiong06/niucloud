<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express\provider;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\service\core\express\contract\ExpressProviderInterface;
use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
use addon\hsx_recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\exception\CommonException;

/** 各站独立账号的快递100上门取件适配器；不会自动选择其他承运商。 */
class Kuaidi100ExpressProvider implements ExpressProviderInterface
{
    private $configLoader;
    private $transport;

    /** 依赖注入只用于测试，不允许前端传入任意 URL。 */
    public function __construct(?callable $configLoader = null, ?callable $transport = null)
    {
        $this->configLoader = $configLoader;
        $this->transport = $transport;
    }

    public function key(): string { return 'kuaidi100'; }
    public function name(): string { return '快递100'; }

    private function config(int $siteId, bool $allowDisabled = false): array
    {
        $config = $this->configLoader ? ($this->configLoader)($siteId)
            : (new RecycleThirdPartyConfigService())->getProviderConfig($siteId, ThirdPartyDict::SERVICE_TYPE_EXPRESS_ORDER, $this->key(), $allowDisabled);
        $config['_site_id'] = $siteId;
        return $config;
    }

    /** 仅校验配置格式，不冒充完成外部权限、余额、承运范围验证。 */
    public function healthCheck(int $siteId): bool
    {
        try { Kuaidi100Protocol::validateConfig($this->config($siteId)); return true; }
        catch (\Throwable $e) { return false; }
    }

    public function prepareSnapshot(int $siteId, array $request = []): array
    {
        $config = $this->config($siteId);
        return $this->snapshotFromConfig($config);
    }

    private function snapshotFromConfig(array $config): array
    {
        Kuaidi100Protocol::validateConfig($config);
        return [
            'provider' => $this->key(), 'provider_name' => $this->name(),
            'provider_mode' => $config['mode'], 'provider_environment' => $config['environment'],
            'provider_account_fingerprint' => hash('sha256', (string)$config['api_key']),
            'carrier_code' => $config['carrier_code'], 'carrier_name' => $config['carrier_name'],
            'service_type' => $config['service_type'], 'payment' => $config['payment'],
            'channel_sw' => (string)($config['channel_sw'] ?? ''),
            'product_code' => Kuaidi100Protocol::productCode($config),
            'callback_base' => $config['callback_url'],
        ];
    }

    public function products(int $siteId): array
    {
        $config = $this->config($siteId);
        try { Kuaidi100Protocol::validateConfig($config); } catch (\Throwable $e) { return []; }
        $paymentTips = $config['mode'] === 'online'
            ? '运费由本站快递100账号结算；客户是否承担以门店约定为准'
            : ($config['payment'] === 'CONSIGNEE'
                ? '到付：收件方按快递公司最终账单支付'
                : '寄付：寄件方按快递公司最终账单支付');
        return [[
            'provider' => $this->key(), 'provider_name' => $this->name(),
            'product_code' => Kuaidi100Protocol::productCode($config),
            'product_name' => $config['carrier_name'] . ' · ' . $config['service_type'],
            'carrier_code' => $config['carrier_code'], 'carrier_name' => $config['carrier_name'],
            'payment' => $config['payment'], 'payment_tips' => $paymentTips, 'enabled' => 1,
            'pickup_time_supported' => true, 'pickup_time_required' => true,
            'capabilities' => ['quote' => true, 'pickup' => true, 'cancel_before_pickup' => true,
                'modify_subject_to_carrier' => true, 'waybill_print' => false, 'account_balance' => false],
            'verification_state' => 'configured_not_verified',
        ]];
    }

    public function quote(int $siteId, array $request): array
    {
        $config = $this->config($siteId);
        Kuaidi100Protocol::validateConfig($config);
        $weight = $request['weight'] ?? 1;
        if (!is_numeric($weight) || !is_finite((float)$weight) || (float)$weight <= 0) {
            throw new ExpressSubmissionException('预估重量需大于 0', 'rejected');
        }
        $carrierField = $config['mode'] === 'online' ? 'kuaidiCom' : 'kuaidicom';
        $param = [$carrierField => $config['carrier_code'], 'serviceType' => $config['service_type'], 'weight' => (string)$weight];
        foreach (['sender' => 'sendManPrintAddr', 'receive' => 'recManPrintAddr'] as $source => $target) {
            $param[$target] = '';
            foreach (['Province', 'City', 'District', 'Address'] as $field) {
                $part = trim((string)($request[$source . $field] ?? ''));
                if ($part === '') throw new ExpressSubmissionException('报价需提供完整寄件、收件地址', 'rejected');
                $param[$target] .= $part;
            }
        }
        if ($config['mode'] === 'online' && !empty($config['channel_sw'])) $param['channelSw'] = $config['channel_sw'];
        $data = $this->request($config, 'price', $param);
        $price = $data['price'] ?? null;
        if (!is_numeric($price) || (float)$price < 0) {
            throw new CommonException('快递100暂未返回有效报价，不能按免费寄件处理，请联系门店核实');
        }
        return [[
            'deliveryType' => Kuaidi100Protocol::productCode($config),
            'deliveryName' => $config['carrier_name'] . ' · ' . $config['service_type'],
            'productCode' => Kuaidi100Protocol::productCode($config),
            'productName' => $config['carrier_name'] . ' · ' . $config['service_type'],
            'provider' => $this->key(), 'carrier_name' => $config['carrier_name'],
            'price' => (string)$price, 'totalPrice' => (string)$price, 'is_estimate' => true,
            'payment' => $config['payment'], 'raw' => Kuaidi100Protocol::safeRaw($data),
        ]];
    }

    public function create(int $siteId, array $request): array
    {
        $config = $this->config($siteId);
        // 校验与实际请求必须共用同一份配置，避免两次读取之间切换账号。
        $snapshot = $this->snapshotFromConfig($config);
        foreach (['provider_account_fingerprint', 'provider_mode', 'provider_environment', 'product_code'] as $field) {
            if (isset($request[$field]) && (string)$request[$field] !== (string)$snapshot[$field]) {
                throw new ExpressSubmissionException('预约配置已变更，请核实原预约记录后再处理，未发起下单', 'rejected');
            }
        }
        $param = Kuaidi100Protocol::createParams($siteId, $config, $request);
        $data = $this->request($config, $config['mode'] === 'online' ? 'bOrder' : 'cOrder', $param, true);
        if (empty($data['orderId']) || empty($data['taskId'])) {
            throw new ExpressSubmissionException('快递100已响应但未返回完整预约编号，结果待核实，请勿重复下单');
        }
        $snapshot['third_order_no'] = $param['thirdOrderId'];
        $snapshot['pickup_start'] = (string)($request['pickup_start'] ?? $request['orderSendTime'] ?? '');
        $snapshot['pickup_end'] = (string)($request['pickup_end'] ?? '');
        return array_merge($snapshot, Kuaidi100Protocol::normalize($data, $snapshot));
    }

    private function historicalConfig(int $siteId, array $request): array
    {
        $config = $this->config($siteId, true);
        foreach (['api_key', 'secret'] as $field) {
            if (empty($config[$field])) throw new ExpressSubmissionException('原快递100账号凭证缺失，请管理员恢复配置', 'rejected');
        }
        if (empty($request['provider_account_fingerprint'])
            || !hash_equals(hash('sha256', (string)$config['api_key']), (string)$request['provider_account_fingerprint'])) {
            throw new ExpressSubmissionException('该订单的原快递100账号已变更或无法确认，请恢复原账号后处理，未调用新账号', 'rejected');
        }
        $config['mode'] = (string)($request['provider_mode'] ?? '');
        $config['environment'] = (string)($request['provider_environment'] ?? '');
        Kuaidi100Protocol::endpoint($config);
        if (empty($request['provider_task_id'])) {
            throw new ExpressSubmissionException('尚未取得快递100任务编号，需等待回调或人工核对，不能据此判定未下单');
        }
        return $config;
    }

    public function detail(int $siteId, array $request): array
    {
        $config = $this->historicalConfig($siteId, $request);
        $data = $this->request($config, 'detail', ['taskId' => $request['provider_task_id']]);
        return Kuaidi100Protocol::normalize($data, $request);
    }

    public function cancel(int $siteId, array $request): array
    {
        $config = $this->historicalConfig($siteId, $request);
        $orderId = trim((string)($request['orderNo'] ?? ''));
        if ($orderId === '') throw new ExpressSubmissionException('缺少原快递100订单号，未执行取消', 'rejected');
        $reason = trim((string)($request['reason'] ?? $request['cancelMsg'] ?? '暂时不寄件了'));
        if (mb_strlen($reason) > 30) throw new ExpressSubmissionException('取消原因不能超过 30 个字', 'rejected');
        $data = $this->request($config, 'cancel', ['taskId' => $request['provider_task_id'], 'orderId' => $orderId, 'cancelMsg' => $reason], true);
        return ['provider' => $this->key(), 'orderNo' => $orderId, 'booking_state' => 'cancelled',
            'message' => '取消已确认；费用及退款以渠道后续结算为准', 'raw' => Kuaidi100Protocol::safeRaw($data)];
    }

    public function modify(int $siteId, array $request): array
    {
        $config = $this->historicalConfig($siteId, $request);
        $orderId = trim((string)($request['orderNo'] ?? ''));
        if ($orderId === '') throw new ExpressSubmissionException('缺少原快递100订单号，未执行改约', 'rejected');
        $param = Kuaidi100Protocol::contactParams($request, false);
        if (!empty($request['orderSendTime']) || !empty($request['pickup_start'])) {
            $param += Kuaidi100Protocol::pickupParams($request, (string)($request['carrier_code'] ?? 'shunfeng'));
        }
        if (!$param) throw new ExpressSubmissionException('请提供需要修改的联系人、地址或预约时段', 'rejected');
        $data = $this->request($config, 'modifyOrder', $param + ['taskId' => $request['provider_task_id'], 'orderId' => $orderId], true);
        return ['provider' => $this->key(), 'orderNo' => $orderId, 'message' => '改约请求已受理，请以渠道最新安排为准',
            'raw' => Kuaidi100Protocol::safeRaw($data)];
    }

    public function waybill(int $siteId, array $request): array
    {
        throw new CommonException('当前使用上门取件服务，无需打印电子面单，请在预约记录中查看真实运单号');
    }

    public function account(int $siteId): array
    {
        return ['provider' => $this->key(), 'balance' => null, 'message' => '余额与结算请登录本站自己的快递100账户查看；这里不代扣款'];
    }

    /** 网络异常不暴露 URL/凭证，不在提交结果未知时改走其他渠道。 */
    private function request(array $config, string $method, array $param, bool $mutation = false): array
    {
        $start = microtime(true);
        $result = [];
        $error = '';
        try {
            $result = $this->executeRequest($config, $method, $param, $mutation);
            return $result;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            if (!$this->transport) {
                try {
                    // 日志保留追踪标识和结果，不记录姓名、地址、电话、签名或凭证。
                    \addon\hsx_recycle\app\model\third_party\ThirdPartyApiLog::log([
                        'site_id' => (int)$config['_site_id'], 'service_type' => 'express_order',
                        'provider_name' => $this->key(), 'method' => $method,
                        'request_params' => array_intersect_key($param, array_flip(['taskId', 'orderId', 'thirdOrderId', 'kuaidicom', 'serviceType', 'payment'])),
                        'response_data' => array_intersect_key($result, array_flip(['taskId', 'orderId', 'status'])),
                        'cost' => 0, 'duration' => (int)round((microtime(true) - $start) * 1000),
                        'status' => $error === '' ? 1 : 0,
                        'error_msg' => $error === '' ? '' : '调用未确认成功，详情请查看对应预约记录',
                    ]);
                } catch (\Throwable $ignored) { /* 日志异常不能抹去外部真实结果。 */ }
            }
        }
    }

    private function executeRequest(array $config, string $method, array $param, bool $mutation = false): array
    {
        $url = Kuaidi100Protocol::endpoint($config);
        $body = Kuaidi100Protocol::signedBody($config, $method, $param);
        try {
            if ($this->transport) {
                $response = ($this->transport)($url, $body, $config);
            } else {
                $handle = curl_init($url);
                if ($handle === false) throw new \RuntimeException('http unavailable');
                curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($body),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => max(3, min(60, (int)($config['timeout'] ?? 30))),
                    CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
                $raw = curl_exec($handle);
                $code = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
                curl_close($handle);
                if ($raw === false || $code < 200 || $code >= 300) throw new \RuntimeException('http failed');
                $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\Throwable $e) {
            throw new ExpressSubmissionException($mutation ? '快递请求结果待核实，请勿重复预约；请等待回调或联系门店' : '快递100查询暂未响应，请稍后重试');
        }
        if (!is_array($response) || !array_key_exists('result', $response) || !isset($response['returnCode'])) {
            throw new ExpressSubmissionException('快递100响应格式异常，结果待核实，请勿重复预约');
        }
        if ($response['result'] !== true || (string)$response['returnCode'] !== '200') {
            $code = (string)$response['returnCode'];
            // 500/501 可能为超时、重复请求或已受理后的错误，不能断言没建单。
            $rejected = in_array($code, ['400', '503', '600', '601', '700'], true);
            $message = mb_substr(trim(strip_tags((string)($response['message'] ?? '渠道未确认成功'))), 0, 200);
            foreach (['api_key', 'secret', 'callback_salt'] as $field) {
                if (!empty($config[$field])) $message = str_replace((string)$config[$field], '[已隐藏]', $message);
            }
            throw new ExpressSubmissionException('快递100：' . $message . '（' . $code . '）', $rejected ? 'rejected' : 'unknown');
        }
        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }
}
