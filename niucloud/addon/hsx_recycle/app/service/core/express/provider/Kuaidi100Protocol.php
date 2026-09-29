<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express\provider;

use addon\hsx_recycle\app\service\core\express\ExpressSubmissionException;

/** 快递100上门取件协议。纯数据转换，便于独立测试；不创建面单。 */
class Kuaidi100Protocol
{
    public static function validateConfig(array $config): void
    {
        foreach (['api_key', 'secret', 'callback_salt', 'callback_url', 'carrier_code', 'carrier_name', 'service_type'] as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                throw new ExpressSubmissionException('快递100配置不完整，请管理员补充：' . $field, 'rejected');
            }
        }
        if (!in_array($config['mode'] ?? '', ['online', 'offline'], true)
            || !in_array($config['environment'] ?? '', ['production', 'sandbox'], true)) {
            throw new ExpressSubmissionException('快递100支付模式或运行环境无效', 'rejected');
        }
        $payment = (string)($config['payment'] ?? '');
        if (!in_array($payment, ['SHIPPER', 'CONSIGNEE'], true)
            || ($config['mode'] === 'online' && $payment !== 'SHIPPER')) {
            throw new ExpressSubmissionException('快递100线上支付上门取件仅支持寄付，请调整后台配置', 'rejected');
        }
        if ($payment === 'CONSIGNEE' && in_array($config['carrier_code'], ['yuantong', 'zhongtong'], true)) {
            throw new ExpressSubmissionException('快递100当前承运商不支持到付，请调整后台配置', 'rejected');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/D', (string)$config['carrier_code'])) {
            throw new ExpressSubmissionException('快递公司编码格式不正确', 'rejected');
        }
        $url = parse_url((string)$config['callback_url']);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) {
            throw new ExpressSubmissionException('快递100回调地址必须为可公开访问的 HTTPS 地址', 'rejected');
        }
        if (strlen((string)$config['callback_salt']) < 16 || strlen((string)$config['callback_salt']) > 100) {
            throw new ExpressSubmissionException('回调校验密钥需为 16 至 100 字节，请勿使用空密钥', 'rejected');
        }
    }

    public static function endpoint(array $config, string $mode = ''): string
    {
        $mode = $mode ?: (string)($config['mode'] ?? '');
        if (!in_array($mode, ['online', 'offline'], true)) {
            throw new ExpressSubmissionException('订单缺少原寄件模式，需人工核实渠道，未调用外部接口', 'rejected');
        }
        $path = $mode === 'online' ? 'borderapi.do' : 'corderapi.do';
        if (($config['environment'] ?? '') === 'sandbox') {
            return 'http://e-test.kuaidilab.com/api/order/' . $path;
        }
        if (($config['environment'] ?? '') !== 'production') {
            throw new ExpressSubmissionException('订单运行环境无效，未调用外部接口', 'rejected');
        }
        return ($mode === 'online' ? 'https://poll.kuaidi100.com/order/' : 'https://order.kuaidi100.com/order/') . $path;
    }

    public static function productCode(array $config): string
    {
        // 记录表 product_code 为 varchar(50)。完整业务维度参与哈希，不能截断承运商名称，
        // 也不能使用分隔符拼接（产品名本身可能含分隔符）。固定 10 + 40 = 50 个 ASCII 字符。
        $identity = [];
        foreach (['mode', 'carrier_code', 'service_type', 'channel_sw', 'payment'] as $field) {
            $identity[] = (string)($config[$field] ?? '');
        }
        return 'kuaidi100:' . substr(hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 40);
    }

    public static function signedBody(array $config, string $method, array $param, ?string $timestamp = null): array
    {
        $json = json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = $timestamp ?? (string)(int)floor(microtime(true) * 1000);
        return ['method' => $method, 'key' => $config['api_key'], 't' => $timestamp, 'param' => $json,
            'sign' => strtoupper(md5($json . $timestamp . $config['api_key'] . $config['secret']))];
    }

    public static function thirdOrderId(int $siteId, string $businessOrderNo): string
    {
        if ($siteId <= 0 || trim($businessOrderNo) === '') {
            throw new ExpressSubmissionException('缺少本站稳定预约编号，未发起快递下单', 'rejected');
        }
        // 固定 32 字节，长回收编号不会溢出；本地持久幂等仍由订单服务负责。
        return substr(hash('sha256', $siteId . ':' . $businessOrderNo), 0, 32);
    }

    public static function createParams(int $siteId, array $config, array $request, ?int $now = null): array
    {
        foreach (['callback_url', 'callback_salt'] as $field) {
            if (isset($request[$field])) $config[$field] = $request[$field];
        }
        self::validateConfig($config);
        $param = self::contactParams($request, true);
        $weight = $request['weight'] ?? 1;
        if (!is_numeric($weight) || !is_finite((float)$weight) || (float)$weight <= 0) {
            throw new ExpressSubmissionException('请填写大于 0 的预估重量', 'rejected');
        }
        if ((int)($request['packageCount'] ?? 1) !== 1) {
            throw new ExpressSubmissionException('当前快递100预约按一个包裹下单，多包裹请分开预约', 'rejected');
        }
        $param += [
            'kuaidicom' => $config['carrier_code'], 'serviceType' => $config['service_type'],
            'payment' => $config['payment'], 'cargo' => trim((string)($request['goods'] ?? '回收设备')) ?: '回收设备',
            'weight' => (string)$weight, 'callBackUrl' => $config['callback_url'], 'salt' => $config['callback_salt'],
            'thirdOrderId' => self::thirdOrderId($siteId, (string)($request['thirdOrderNo'] ?? '')),
            'remark' => trim((string)($request['remark'] ?? '')), 'returnType' => '',
        ];
        if ($config['mode'] === 'online' && trim((string)($config['channel_sw'] ?? '')) !== '') {
            $param['channelSw'] = trim((string)$config['channel_sw']);
        }
        return $param + self::pickupParams($request, (string)$config['carrier_code'], $now);
    }

    public static function contactParams(array $request, bool $required): array
    {
        $param = [];
        foreach (['sender' => 'sendMan', 'receive' => 'recMan'] as $source => $target) {
            foreach (['Name' => 'Name', 'Mobile' => 'Mobile'] as $input => $output) {
                $value = trim((string)($request[$source . $input] ?? ''));
                if ($required && $value === '') {
                    throw new ExpressSubmissionException(($source === 'sender' ? '寄件' : '收件') . '联系人及电话不能为空', 'rejected');
                }
                if ($value !== '') $param[$target . $output] = $value;
            }
            $parts = [];
            foreach (['Province', 'City', 'District', 'Address'] as $part) {
                $value = trim((string)($request[$source . $part] ?? ''));
                if ($required && $value === '') {
                    throw new ExpressSubmissionException(($source === 'sender' ? '寄件' : '收件') . '地址不完整，请补充省市区及详细地址', 'rejected');
                }
                $parts[] = $value;
            }
            if (implode('', $parts) !== '') {
                if (count(array_filter($parts, static fn(string $part): bool => $part !== '')) !== 4) {
                    throw new ExpressSubmissionException('修改地址时请同时提交完整省市区及详细地址', 'rejected');
                }
                $param[$target . 'PrintAddr'] = implode('', $parts);
            }
        }
        return $param;
    }

    public static function pickupParams(array $request, string $carrier, ?int $now = null): array
    {
        $now = $now ?? time();
        $text = trim((string)($request['orderSendTime'] ?? ''));
        $start = trim((string)($request['pickup_start'] ?? ''));
        $end = trim((string)($request['pickup_end'] ?? ''));
        // 统一输入为绝对北京时间：2026-09-29 09:00 或 2026-09-29 09:00-11:00。
        if ($start === '' && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::00)?(?:\s*[-~至]\s*(\d{2}:\d{2}))?$/uD', $text, $m)) {
            $start = $m[1] . ' ' . $m[2];
            $end = isset($m[3]) ? $m[1] . ' ' . $m[3] : '';
        }
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $parse = static function (string $value) use ($timezone): ?\DateTimeImmutable {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);
            return $date && $date->format('Y-m-d H:i') === $value ? $date : null;
        };
        $startAt = $parse($start);
        $endAt = $parse($end);
        if (!$startAt || !$endAt) {
            throw new ExpressSubmissionException('请选择完整的预约日期和起止时间，例如今天 09:00–11:00', 'rejected');
        }
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($timezone)->setTime(0, 0);
        $day = (int)(($startAt->setTime(0, 0)->getTimestamp() - $today->getTimestamp()) / 86400);
        if ($day < 0 || $day > 2 || $startAt->format('Y-m-d') !== $endAt->format('Y-m-d')
            || $startAt->getTimestamp() < $now || $endAt <= $startAt
            || ($carrier === 'shunfeng' && $endAt->getTimestamp() - $startAt->getTimestamp() < 3600)) {
            throw new ExpressSubmissionException('预约时段需在未来三天内且结束晚于开始，顺丰时段至少 1 小时', 'rejected');
        }
        return ['dayType' => ['今天', '明天', '后天'][$day], 'pickupStartTime' => $startAt->format('H:i'), 'pickupEndTime' => $endAt->format('H:i')];
    }

    /** 验签必须使用收到的原始 param 字符串，先定位本站订单再传该账号 salt。 */
    public static function verifyCallback(string $rawParam, string $sign, string $salt): bool
    {
        return strlen($salt) >= 16 && preg_match('/^[A-Fa-f0-9]{32}$/D', $sign) === 1
            && hash_equals(strtoupper(md5($rawParam . $salt)), strtoupper($sign));
    }

    public static function state($status): string
    {
        $states = ['0' => 'confirmed', '1' => 'confirmed', '2' => 'confirmed', '9' => 'cancelled', '99' => 'cancelled',
            '10' => 'picked_up', '101' => 'in_transit', '400' => 'in_transit', '13' => 'delivered',
            '11' => 'failed', '201' => 'failed', '610' => 'failed', '14' => 'exception', '166' => 'exception', '302' => 'exception',
            '200' => 'accepted'];
        // 15 已结算、155 调重、110 付款事件不得覆盖物流阶段。
        return $states[(string)$status] ?? '';
    }

    public static function normalize(array $data, array $context = []): array
    {
        $status = $data['status'] ?? null;
        $carrierCode = trim((string)($data['kuaidiCom'] ?? $data['kuaidicom'] ?? '')) ?: (string)($context['carrier_code'] ?? '');
        $conflict = in_array((string)$status, ['166', '302'], true)
            || (!empty($context['carrier_code']) && $carrierCode !== (string)$context['carrier_code']);
        $bookingState = $status === null ? 'accepted' : self::state($status);
        if ($bookingState === 'confirmed' && (!empty($data['courierName']) || !empty($data['courierMobile']))) $bookingState = 'assigned';
        if ($conflict) $bookingState = 'exception';
        $knownCarriers = ['shunfeng' => '顺丰速运', 'jd' => '京东快递', 'yuantong' => '圆通速递', 'zhongtong' => '中通快递'];
        $carrierName = $carrierCode === ($context['carrier_code'] ?? '') ? (string)($context['carrier_name'] ?? '') : ($knownCarriers[$carrierCode] ?? $carrierCode);
        return [
            'provider' => 'kuaidi100', 'provider_name' => '快递100',
            'orderNo' => (string)($data['orderId'] ?? $context['orderNo'] ?? ''),
            'deliveryId' => (string)($data['kuaidiNum'] ?? $data['kuaidinum'] ?? ''),
            'provider_task_id' => (string)($data['taskId'] ?? $context['provider_task_id'] ?? ''),
            'provider_mode' => (string)($context['provider_mode'] ?? ''),
            'provider_environment' => (string)($context['provider_environment'] ?? ''),
            'booking_state' => $bookingState, 'provider_status' => $status, 'conflict' => $conflict,
            'carrier_code' => $carrierCode, 'carrier_name' => $carrierName,
            'courier_name' => (string)($data['courierName'] ?? ''), 'courier_mobile' => (string)($data['courierMobile'] ?? ''),
            'pickup_start' => (string)($data['pickupStartTime'] ?? $context['pickup_start'] ?? ''),
            'pickup_end' => (string)($data['pickupEndTime'] ?? $context['pickup_end'] ?? ''),
            'third_order_no' => (string)($data['thirdOrderId'] ?? $context['third_order_no'] ?? ''),
            'actual_cost' => null,
            'reported_freight' => isset($data['freight']) && is_numeric($data['freight']) ? (string)$data['freight'] : null,
            'fee_verification_state' => 'pending_reconciliation',
            'chargeable_weight' => $data['weight'] ?? null, 'actual_weight' => $data['actualWeight'] ?? null,
            'fee_details' => is_array($data['feeDetails'] ?? null) ? $data['feeDetails'] : [],
            'raw' => self::safeRaw($data),
        ];
    }

    public static function normalizeCallback($rawParam, string $taskId = '', array $context = []): array
    {
        $payload = is_array($rawParam) ? $rawParam : json_decode($rawParam, true, 512, JSON_THROW_ON_ERROR);
        $taskId = $taskId ?: (string)($payload['taskId'] ?? '');
        if (!is_array($payload) || !is_array($payload['data'] ?? null) || !isset($payload['data']['status'])) {
            throw new \InvalidArgumentException('快递100回调缺少订单数据');
        }
        $data = $payload['data'];
        if ($taskId !== '') $data['taskId'] = $taskId;
        if (!empty($payload['kuaidicom'])) $data['kuaidicom'] = (string)$payload['kuaidicom'];
        if (!empty($payload['kuaidinum'])) $data['kuaidinum'] = (string)$payload['kuaidinum'];
        $normalized = self::normalize($data, $context);
        $normalized['message'] = trim((string)($payload['message'] ?? ''));
        return $normalized;
    }

    public static function safeRaw(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/key|secret|token|salt|sign|callbackurl/i', (string)$key)) { unset($data[$key]); continue; }
            if (is_array($value)) $data[$key] = self::safeRaw($value);
        }
        return $data;
    }
}
