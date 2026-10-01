<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

use addon\hsx_express\app\service\core\ProviderException;
use addon\hsx_express\app\service\core\SfClient;
use addon\hsx_express\app\service\core\SfConfigService;
use addon\hsx_express\app\service\core\SfProtocol;
use addon\hsx_recycle\app\service\core\express\contract\ExpressProviderInterface;
use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;
use app\service\core\site\CoreSiteService;

/**
 * 顺丰上门取件桥接：只读取 pickup 配置，不取电子面单配置、不打印、不自动切换渠道。
 * 取号/订单筛选通过不等于派员成功。未经验证的回调及派员状态保持未就绪。
 */
final class RecycleSfPickupProvider implements ExpressProviderInterface
{
    private $configLoader;
    private $transport;

    /** 注入入口只用于无 IO 测试，业务请求不能指定账号、网关或 Provider。 */
    public function __construct(?callable $configLoader = null, ?callable $transport = null)
    {
        $this->configLoader = $configLoader;
        $this->transport = $transport;
    }

    public function key(): string { return 'sf_direct'; }
    public function name(): string { return '顺丰直连'; }
    public function queryRequirements(): array { return ['provider_order_id']; }

    private function config(int $siteId, bool $historical = false): array
    {
        if ($siteId <= 0) throw new ExpressSubmissionException('站点无效，未发起顺丰取件请求', 'rejected');
        if (!$this->configLoader
            && !in_array('hsx_express', (new CoreSiteService())->getAddonKeysBySiteId($siteId), true)) {
            throw new ExpressSubmissionException('本站未开通物流中心，不能调用顺丰上门取件', 'rejected');
        }
        $config = $this->configLoader ? ($this->configLoader)($siteId, 'pickup') : (new SfConfigService())->get($siteId, 'pickup');
        if (isset($config['scene']) && $config['scene'] !== 'pickup') {
            throw new ExpressSubmissionException('顺丰上门取件不能使用电子面单配置', 'rejected');
        }
        if (!$historical && empty($config['enabled'])) {
            throw new ExpressSubmissionException('本站顺丰上门取件尚未启用；电子面单开关不启用上门取件', 'rejected');
        }
        foreach (['client_code', 'check_word', 'product_code'] as $field) {
            if (!isset($config[$field]) || !is_scalar($config[$field]) || trim((string)$config[$field]) === '') {
                throw new ExpressSubmissionException('顺丰上门取件配置不完整，请到物流中心补充', 'rejected');
            }
        }
        if (!in_array($config['environment'] ?? '', ['sandbox', 'production'], true)
            || !in_array((int)($config['pay_method'] ?? 0), [1, 2, 3], true)) {
            throw new ExpressSubmissionException('顺丰上门取件环境或付款方式无效', 'rejected');
        }
        if (!$historical) {
            $readiness = SfConfigService::readiness($config, 'pickup');
            if (empty($readiness['ready'])) {
                throw new ExpressSubmissionException('顺丰上门取件配置尚未就绪，请在物流中心查看缺少的配置', 'rejected');
            }
        }
        return $config;
    }

    public function healthCheck(int $siteId): bool
    {
        try { $this->config($siteId); return true; } catch (\Throwable $e) { return false; }
    }

    private function productCode(array $config): string
    {
        return 'sf_pickup_' . (string)$config['product_code'];
    }

    private function accountFingerprint(array $config): string
    {
        return hash('sha256', 'pickup|' . (string)$config['environment'] . '|' . (string)$config['client_code'] . '|' . (string)($config['monthly_card'] ?? ''));
    }

    private function snapshot(int $siteId, array $config, string $thirdOrderNo): array
    {
        if ($thirdOrderNo === '') throw new ExpressSubmissionException('缺少原回收取件标识，不能安全预约', 'rejected');
        return [
            'provider' => $this->key(), 'provider_name' => $this->name(), 'provider_scene' => 'pickup',
            'provider_site_id' => $siteId, 'provider_environment' => $config['environment'],
            'provider_account_fingerprint' => $this->accountFingerprint($config),
            // 发送之前保存可查询的稳定订单号；超时后查原单，不以新号码重下。
            'provider_order_id' => 'SFP' . substr(hash('sha256', $siteId . '|' . $thirdOrderNo), 0, 29),
            'carrier_code' => 'SF', 'carrier_name' => '顺丰速运',
            'product_code' => $this->productCode($config), 'service_type' => (string)$config['product_code'],
            'payment' => (int)$config['pay_method'], 'callback_ready' => false,
            'status_scope' => 'order_acceptance_only',
        ];
    }

    public function prepareSnapshot(int $siteId, array $request = []): array
    {
        $config = $this->config($siteId);
        $this->assertBusinessEnvironment($config);
        return $this->snapshot($siteId, $config, trim((string)($request['thirdOrderNo'] ?? $request['third_order_no'] ?? '')));
    }

    public function products(int $siteId): array
    {
        try { $config = $this->config($siteId); } catch (\Throwable $e) { return []; }
        // 沙箱只能验证协议，不能使真实回收订单显示预约成功或已发出。
        if (($config['environment'] ?? '') !== 'production') return [];
        $catalog = array_column(SfConfigService::products(), 'label', 'value');
        $productName = (string)($catalog[(string)$config['product_code']] ?? '本站约定产品');
        return [[
            'provider' => $this->key(), 'provider_name' => $this->name(),
            'product_code' => $this->productCode($config), 'product_name' => '上门取件 · ' . $productName,
            'carrier_code' => 'SF', 'carrier_name' => '顺丰速运', 'payment' => (int)$config['pay_method'],
            'payment_tips' => '运费按本站顺丰合同与付款方式结算，最终费用以顺丰账单为准；未报价不代表免费',
            'enabled' => 1, 'pickup_time_supported' => true, 'pickup_time_required' => true,
            'capabilities' => ['quote' => false, 'pickup' => true, 'cancel_before_pickup' => true,
                'modify_subject_to_carrier' => false, 'waybill_print' => false, 'account_balance' => false,
                'callback' => false, 'courier_assignment' => false],
            'verification_state' => 'configured_not_verified',
        ]];
    }

    private function payload(array $request): array
    {
        $weight = $request['weight'] ?? 0;
        $count = $request['packageCount'] ?? 1;
        if (!is_numeric($weight) || !is_finite((float)$weight) || (float)$weight <= 0
            || !is_numeric($count) || (float)$count !== 1.0) {
            throw new ExpressSubmissionException('请填写有效包裹重量；顺丰直连当前每次预约只对应一个包裹', 'rejected');
        }
        $cargo = $request['goods'] ?? '回收设备';
        if (!is_scalar($cargo)) throw new ExpressSubmissionException('物品名称格式错误，未发起顺丰预约', 'rejected');
        $payload = ['cargo' => trim((string)$cargo), 'weight' => (float)$weight, 'count' => 1];
        foreach (['sender' => 'sender', 'receiver' => 'receive'] as $target => $prefix) {
            $row = [];
            foreach (['name' => 'Name', 'mobile' => 'Mobile', 'province' => 'Province', 'city' => 'City', 'district' => 'District', 'detail_address' => 'Address'] as $field => $suffix) {
                $value = $request[$prefix . $suffix] ?? '';
                if (!is_scalar($value)) throw new ExpressSubmissionException('寄件或收件资料格式错误，未发起顺丰预约', 'rejected');
                $row[$field] = trim((string)$value);
                if ($row[$field] === '') throw new ExpressSubmissionException('寄件或收件信息不完整，未发起顺丰预约', 'rejected');
            }
            $row['address'] = $row['province'] . $row['city'] . $row['district'] . $row['detail_address'];
            $payload[$target] = $row;
        }
        $pickup = trim((string)($request['pickup_start_at'] ?? $request['pickup_start'] ?? $request['orderSendTime'] ?? ''));
        $end = trim((string)($request['pickup_end_at'] ?? $request['pickup_end'] ?? ''));
        // 解析客户表单的完整时段；共享协议传开始及结束时间，不承诺该时段内必达。
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::00)?\s*[-~至]\s*(\d{2}:\d{2})$/uD', $pickup, $match)) {
            $pickup = $match[1] . ' ' . $match[2];
            $end = $match[1] . ' ' . $match[3];
        }
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $parse = static function (string $text) use ($timezone): ?\DateTimeImmutable {
            $format = strlen($text) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            $value = \DateTimeImmutable::createFromFormat('!' . $format, $text, $timezone);
            return $value && $value->format($format) === $text ? $value : null;
        };
        $startAt = $parse($pickup);
        $endAt = $end !== '' ? $parse($end) : null;
        if (!$startAt || $startAt->getTimestamp() <= time() || ($end !== '' && (!$endAt || $endAt <= $startAt))) {
            throw new ExpressSubmissionException('请选择有效的未来取件时间，未发起顺丰预约', 'rejected');
        }
        $payload['pickup_start_at'] = $startAt->format('Y-m-d H:i:s');
        if ($endAt) $payload['pickup_end_at'] = $endAt->format('Y-m-d H:i:s');
        $payload['pickup_time'] = $startAt->format('Y-m-d H:i') . ($endAt ? '-' . $endAt->format('H:i') : '');
        return $payload;
    }

    public function create(int $siteId, array $request): array
    {
        $config = $this->config($siteId);
        $this->assertBusinessEnvironment($config);
        $snapshot = $this->snapshot($siteId, $config, trim((string)($request['thirdOrderNo'] ?? $request['third_order_no'] ?? '')));
        foreach (['provider_site_id', 'provider_scene', 'provider_environment', 'provider_account_fingerprint', 'provider_order_id', 'product_code'] as $field) {
            if (isset($request[$field]) && (string)$request[$field] !== (string)$snapshot[$field]) {
                throw new ExpressSubmissionException('原取件配置已改变，未发起顺丰请求，请先核实原预约', 'rejected');
            }
        }
        $payload = $this->payload($request);
        try {
            $message = SfProtocol::createOrder($config, $payload, $snapshot['provider_order_id'], 'pickup');
        } catch (ProviderException $e) {
            throw new ExpressSubmissionException($e->getMessage(), 'rejected');
        } catch (\Throwable $e) {
            throw new ExpressSubmissionException('顺丰取件参数未通过校验，未发起预约，请检查联系人、地址与取件时段', 'rejected');
        }
        $data = $this->request(SfClient::CREATE_ORDER, $message, $config);
        return array_merge($snapshot, $this->normalize($data, $snapshot, true), ['pickup_time' => $payload['pickup_time']]);
    }

    private function assertBusinessEnvironment(array $config): void
    {
        if (($config['environment'] ?? '') !== 'production') {
            throw new ExpressSubmissionException('顺丰上门取件当前为沙箱，不能为真实回收订单预约；请管理员完成测试后启用正式环境', 'rejected');
        }
    }

    private function historicalConfig(int $siteId, array $request): array
    {
        $config = $this->config($siteId, true);
        if ((int)($request['provider_site_id'] ?? 0) !== $siteId || ($request['provider_scene'] ?? '') !== 'pickup'
            || ($request['provider_environment'] ?? '') !== $config['environment']
            || empty($request['provider_account_fingerprint'])
            || !hash_equals($this->accountFingerprint($config), (string)$request['provider_account_fingerprint'])) {
            throw new ExpressSubmissionException('原顺丰取件账号或站点无法确认，请恢复原配置，未调用其他账号', 'unknown');
        }
        if (!in_array($request['provider_environment'] ?? '', ['sandbox', 'production'], true)
            || !preg_match('/^SFP[a-f0-9]{29}$/D', (string)($request['provider_order_id'] ?? ''))) {
            throw new ExpressSubmissionException('缺少原顺丰取件查询标识，不能推定未下单，请联系门店核实', 'unknown');
        }
        // 原订单始终使用原环境和产品；停用新预约不妨碍取消、查询原单。
        $config['environment'] = $request['provider_environment'];
        $config['product_code'] = (string)($request['service_type'] ?? $config['product_code']);
        $config['pay_method'] = (int)($request['payment'] ?? $config['pay_method']);
        return $config;
    }

    public function detail(int $siteId, array $request): array
    {
        $config = $this->historicalConfig($siteId, $request);
        $data = $this->request(SfClient::SEARCH_ORDER, SfProtocol::searchOrder($request['provider_order_id']), $config);
        return $this->normalize($data, $request);
    }

    private function normalize(array $data, array $snapshot, bool $creation = false): array
    {
        $result = SfProtocol::normalizeOrder($data);
        if ($creation && !empty($result['definitive_rejected'])) {
            // 只有建单响应的已验证拒绝码能开放自寄；查询失败永远不能反推原预约失败。
            throw new ExpressSubmissionException((string)($result['message'] ?? '顺丰明确拒绝此次取件请求'), 'rejected');
        }
        $orderNo = trim((string)($result['order_no'] ?? ''));
        if (empty($result['success'])) {
            throw new ExpressSubmissionException((string)($result['message'] ?? '顺丰尚未返回可核对的原订单结果，请勿重复预约'), 'unknown');
        }
        if ($orderNo === '' || !hash_equals((string)$snapshot['provider_order_id'], $orderNo)) {
            throw new ExpressSubmissionException('顺丰尚未返回可核对的原订单结果，请勿重复预约', 'unknown');
        }
        $accepted = in_array((string)($result['filter_result'] ?? ''), ['1', '2'], true);
        if (!$accepted && !$creation) {
            throw new ExpressSubmissionException('顺丰尚未确认原订单的收派条件，请联系门店核实，勿重复预约或自行寄件', 'unknown');
        }
        // normalizeOrder.confirmed 只是“可以收寄”，绝不能当作预约确认或已经派员。
        return ['booking_state' => $accepted ? 'accepted' : 'unknown', 'orderNo' => $orderNo,
            'deliveryId' => (string)($result['waybill_no'] ?? ''), 'carrier_name' => '顺丰速运',
            'provider_order_id' => (string)$snapshot['provider_order_id'],
            'provider_filter_result' => (string)($result['filter_result'] ?? ''),
            'status_scope' => 'order_acceptance_only', 'callback_ready' => false,
            'last_provider_response_id' => (string)($data['_response_id'] ?? '')];
    }

    public function cancel(int $siteId, array $request): array
    {
        // 官方取消确认必须匹配原 orderId；仅受理、超时、含糊错误码均不释放自寄入口。
        $config = $this->historicalConfig($siteId, $request);
        $message = SfProtocol::cancelOrder($request['provider_order_id'], (string)($request['waybill_no'] ?? ''), '客户取消上门取件');
        $data = $this->request(SfClient::CANCEL_ORDER, $message, $config);
        if (!SfProtocol::cancelConfirmed($data, (string)$request['provider_order_id'])) {
            throw new ExpressSubmissionException('顺丰取消结果尚未确认，请勿重复预约或自行寄件', 'unknown');
        }
        return ['booking_state' => 'cancelled', 'orderNo' => $request['provider_order_id']];
    }

    private function request(string $service, array $message, array $config): array
    {
        try {
            return $this->transport ? ($this->transport)($service, $message, $config) : (new SfClient())->request($service, $message, $config);
        } catch (ProviderException $e) {
            // SfClient 已将第三方原文转换为脱敏白名单提示，保留可操作的权限/配置原因。
            throw new ExpressSubmissionException($e->getMessage(), $e->isUnknown() ? 'unknown' : 'rejected');
        }
    }

    public function quote(int $siteId, array $request): array { throw new ExpressSubmissionException('顺丰取件未接入报价，费用以顺丰账单为准，未报价不代表免费', 'rejected'); }
    public function modify(int $siteId, array $request): array { throw new ExpressSubmissionException('顺丰改约尚未接入，请联系门店核实原预约；不会重新下单', 'unknown'); }
    public function waybill(int $siteId, array $request): array { throw new ExpressSubmissionException('上门取件不提供电子面单打印，请到物流中心电子面单业务办理', 'rejected'); }
    public function account(int $siteId): array { return ['supported' => false, 'balance' => null, 'message' => '顺丰账户账单请到官方后台查看']; }
}
