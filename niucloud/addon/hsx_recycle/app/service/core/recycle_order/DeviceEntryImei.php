<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use core\exception\CommonException;

/** 管理端设备录入/签收的串号契约，不影响客户仅填台数的自助下单。 */
final class DeviceEntryImei
{
    public static function error($value): string
    {
        if ($value === null || $value === '') return '请填写 IMEI';
        if ((!is_string($value) && !is_int($value)) || preg_match('/[^A-Za-z0-9]/', (string)$value)) {
            return 'IMEI 只能包含英文字母和数字，不能有空格或特殊字符';
        }
        if (strlen((string)$value) < 6) return 'IMEI 至少填写 6 位';
        // 保持原有接口的长度上限，不把普通串号误当成必须 15 位的标准 IMEI。
        if (strlen((string)$value) > 15) return 'IMEI 不能超过 15 位';
        return '';
    }

    public static function assertValid($value, string $prefix = ''): void
    {
        $error = self::error($value);
        if ($error !== '') throw new CommonException($prefix . $error);
    }

    /** 在批量写入前一次性校验，避免先保存前几台才发现后面的串号无效。 */
    public static function assertDevices(array $devices): void
    {
        foreach (array_values($devices) as $index => $device) {
            self::assertValid(is_array($device) ? ($device['imei'] ?? null) : null, '第 ' . ($index + 1) . ' 台设备：');
        }
    }
}
