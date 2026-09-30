<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\order;

use core\exception\CommonException;

/** 订单设备身份属于成交快照，不能在付款/退款重放时跟随当前商品的关联变化。 */
final class ErpDeviceSnapshot
{
    public static function decode($value): array
    {
        if (is_array($value)) return $value;
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public static function fromSku(array $sku, array $goods = []): array
    {
        $snapshot = self::decode($sku['device_snapshot'] ?? []);
        $identity = self::decode($snapshot['identity'] ?? []);
        $imei = trim((string)($identity['imei'] ?? $snapshot['imei'] ?? ''));
        $sn = trim((string)($identity['serial_number'] ?? $snapshot['sn'] ?? ''));
        $code = trim((string)($sku['sku_no'] ?? ''));
        $conflict = preg_match('/^\d{15}$/D', $code) && $imei !== '' && $imei !== $code;
        if ((int)($sku['is_unique'] ?? 0) === 1 && preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{8,32}$/D', $code)
            && $sn !== '' && strtoupper($sn) !== strtoupper($code)) $conflict = true;
        // SKU 编码不一定是设备串号。短码、商品公共键、型号名不能作为自动配对依据。
        if ($imei === '' && preg_match('/^\d{15}$/D', $code)) $imei = $code;
        if ($sn === '' && (int)($sku['is_unique'] ?? 0) === 1
            && preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{8,32}$/D', $code)) $sn = $code;
        return [
            'version' => 1,
            'erp_asset_id' => max(0, (int)($sku['erp_asset_id'] ?? 0)),
            'imei' => mb_substr($imei, 0, 64),
            'sn' => mb_substr(strtoupper($sn), 0, 64),
            'sku_no' => mb_substr($code, 0, 64),
            'source' => (string)($goods['source'] ?? ''),
            'is_proxy' => (int)($goods['is_proxy'] ?? 0),
            'identity_conflict' => $conflict ? 1 : 0,
        ];
    }

    public static function extend($extend, array $sku, array $goods): array
    {
        $result = self::decode($extend);
        unset($result['erp_sale_at']); // 新订单不能沿用其他扩展传入的旧成交时间。
        $result['erp_device'] = self::fromSku($sku, $goods);
        return self::checkLength($result);
    }

    /** 挂账没有支付时间，在原订单扩展冻结实际确认时点，避免重推时变成当天销售。 */
    public static function withSaleTime($extend, int $occurredAt): array
    {
        $result = self::decode($extend);
        if (empty($result['erp_sale_at'])) $result['erp_sale_at'] = $occurredAt;
        return self::checkLength($result);
    }

    public static function saleTime(array $rawOrder, array $lines): int
    {
        // 模型的 timestamp 获取器会返回日期字符串；必须使用 getData() 的原始整数。
        if ((int)($rawOrder['pay_time'] ?? 0) > 0) return (int)$rawOrder['pay_time'];
        foreach ($lines as $line) {
            $extend = self::decode($line['extend'] ?? []);
            if ((int)($extend['erp_sale_at'] ?? 0) > 0) return (int)$extend['erp_sale_at'];
        }
        return time();
    }

    public static function checkLength(array $result): array
    {
        // 复用订单明细现有扩展字段，不静默截断其他活动的扩展资料。
        // 与 ThinkORM 的 JSON 字段编码一致，包含中文和斜杠转义后的真实落库长度。
        $json = json_encode($result);
        if ($json === false || strlen($json) > 1000) {
            throw new CommonException('商品订单附加资料过长，设备串号无法完整保存，请联系管理员');
        }
        return $result;
    }

    public static function fromOrderLine(array $line, array $sku = [], array $goods = []): array
    {
        $extend = self::decode($line['extend'] ?? []);
        if (is_array($extend['erp_device'] ?? null)) return $extend['erp_device'];
        $device = self::fromSku($sku, $goods);
        if (isset($line['inventory_source']) && in_array($line['inventory_source'], ['self_owned', 'supplier', 'opening'], true)) {
            $device['erp_asset_id'] = 0;
        }
        return $device;
    }
}
