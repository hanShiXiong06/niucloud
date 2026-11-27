<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use core\exception\CommonException;

final class WaybillProtocol
{
    public static function normalizePayload(array $payload): array
    {
        $type = trim((string)($payload['business_type'] ?? ''));
        $id = trim((string)($payload['business_id'] ?? ''));
        if (!preg_match('/^[a-z][a-z0-9_]{1,59}$/', $type) || $id === '' || strlen($id) > 100 || preg_match('/[\x00-\x20]/', $id)) throw new CommonException('缺少有效的业务包裹标识，不能安全取号');
        $result = ['business_type' => $type, 'business_id' => $id];
        $businessNo = trim((string)($payload['business_no'] ?? $payload['order_no'] ?? ''));
        if (mb_strlen($businessNo) > 100) throw new CommonException('业务单号过长，请核对订单信息');
        $result['business_no'] = $businessNo;
        foreach (['sender' => '寄件人', 'receiver' => '收件人'] as $field => $label) {
            $data = is_array($payload[$field] ?? null) ? $payload[$field] : [];
            $name = trim((string)($data['name'] ?? ''));
            $mobile = trim((string)($data['mobile'] ?? ''));
            $address = trim((string)($data['address'] ?? ''));
            if ($name === '' || mb_strlen($name) > 60 || !preg_match('/^[0-9+()\-]{5,30}$/', $mobile) || mb_strlen($address) < 5 || mb_strlen($address) > 300) throw new CommonException($label . '姓名、电话和省市区详细地址不完整，请先修改业务地址');
            $result[$field] = compact('name', 'mobile', 'address');
        }
        $cargo = trim((string)($payload['cargo'] ?? '手机'));
        if ($cargo === '' || mb_strlen($cargo) > 100) throw new CommonException('物品名称需要在1至100字之间');
        $weight = (float)($payload['weight'] ?? 0);
        if (!is_finite($weight) || $weight <= 0 || $weight > 100) throw new CommonException('请填写实际包裹重量（0至100公斤，不含0）');
        if ((int)($payload['count'] ?? 1) !== 1) throw new CommonException('当前每次取号对应一个包裹，多包裹请在业务中分别创建，避免子单遗漏');
        $result += ['cargo' => $cargo, 'weight' => round($weight, 3), 'count' => 1,
            'order_id' => max(0, (int)($payload['order_id'] ?? 0)), 'order_goods_ids' => array_values(array_unique(array_map('intval', (array)($payload['order_goods_ids'] ?? []))))];
        sort($result['order_goods_ids']);
        return $result;
    }

    public static function create(array $config, array $payload, string $taskNo, string $salt, int $siteId): array
    {
        $contact = static fn($row) => ['name' => $row['name'], 'mobile' => $row['mobile'], 'printAddr' => $row['address']];
        $param = ['kuaidicom' => $config['carrier'],
            'recMan' => $contact($payload['receiver']), 'sendMan' => $contact($payload['sender']), 'cargo' => $payload['cargo'],
            'weight' => $payload['weight'], 'count' => 1, 'needChild' => '0', 'needBack' => '0',
            'payType' => $config['pay_type'], 'expType' => $config['exp_type'], 'tempId' => $config['template_id'],
            'printType' => $config['scene'] === 'waybill_cloud' ? 'CLOUD' : 'IMAGE', 'orderId' => $taskNo, 'reorder' => false,
            'needOcr' => false, 'needDesensitization' => true];
        $param += WaybillCarrierCatalog::accountParams($config);
        if ($config['scene'] === 'waybill_cloud') $param['siid'] = $config['device_id'];
        if ($config['callback_base_url'] !== '') {
            $param['callBackUrl'] = $config['callback_base_url'] . '/api/hsx_express/callback/' . $siteId . '/' . $taskNo;
            $param['salt'] = $salt;
        }
        return $param;
    }

    public static function resultSucceeded(array $result): bool { return ($result['success'] ?? false) === true && in_array((string)($result['code'] ?? ''), ['200', '30011'], true); }
    public static function safeCreateFailure(array $result): bool
    {
        // 仅参数/账号/额度/模板的确定拒绝允许修改后重试；三方故障和打印失败可能已取号。
        return ($result['success'] ?? null) === false && in_array((string)($result['code'] ?? ''), ['30001', '30002', '30003', '30004', '30006', '30007'], true);
    }
    public static function validCallback(string $param, string $sign, string $salt): bool
    {
        return $salt !== '' && preg_match('/^[a-f0-9]{32}$/i', $sign) === 1 && hash_equals(strtoupper(md5($param . $salt)), strtoupper($sign));
    }
    public static function labels(string $value): array
    {
        $labels = [];
        foreach (explode(',', $value) as $url) {
            $url = trim($url); $parts = parse_url($url);
            if (strlen($url) > 2048 || !is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !empty($parts['user']) || !empty($parts['pass'])) continue;
            $host = strtolower($parts['host'] ?? '');
            if ($host === 'kuaidi100.com' || substr($host, -14) === '.kuaidi100.com' || $host === 'ckd.im' || substr($host, -7) === '.ckd.im') $labels[] = $url;
        }
        return $labels;
    }
    public static function safeMessage(string $message, array $config = [], array $payload = []): string
    {
        foreach (array_merge(ConfigService::SECRETS, ['partner_id']) as $key) if (!empty($config[$key])) $message = str_replace((string)$config[$key], '***', $message);
        foreach (['sender', 'receiver'] as $contact) {
            foreach (['name', 'mobile', 'address'] as $field) if (!empty($payload[$contact][$field])) $message = str_replace((string)$payload[$contact][$field], '***', $message);
        }
        $message = preg_replace('/\b1[3-9]\d{9}\b/', '***手机号***', strip_tags($message));
        return mb_substr((string)$message, 0, 800);
    }
}
