<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\core;

use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

final class PerformanceConfigService
{
    public const CONFIG_KEY = 'HSX_PERFORMANCE_REPORT_CONFIG';

    public function get(int $siteId): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        return $this->normalize(is_array($value) ? $value : []);
    }

    public function save(int $siteId, array $data): array
    {
        $config = $this->normalize(array_replace($this->get($siteId), $data));
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $config['send_time'])) {
            throw new CommonException('报告推送时间格式不正确');
        }
        (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $config);
        return $config;
    }

    private function normalize(array $data): array
    {
        $receiverUids = array_values(array_unique(array_filter(array_map('intval', (array)($data['receiver_uids'] ?? [])))));
        $dailyScope = (string)($data['daily_scope'] ?? 'auto');
        if (!in_array($dailyScope, ['auto', 'current_day', 'previous_day'], true)) $dailyScope = 'auto';
        return [
            'enabled' => (int)!empty($data['enabled']),
            'daily_enabled' => (int)($data['daily_enabled'] ?? 1) === 1 ? 1 : 0,
            'weekly_enabled' => (int)($data['weekly_enabled'] ?? 1) === 1 ? 1 : 0,
            'monthly_enabled' => (int)($data['monthly_enabled'] ?? 1) === 1 ? 1 : 0,
            'send_time' => trim((string)($data['send_time'] ?? '09:10')) ?: '09:10',
            'daily_scope' => $dailyScope,
            'show_finance' => (int)($data['show_finance'] ?? 1) === 1 ? 1 : 0,
            'show_profit' => (int)($data['show_profit'] ?? 1) === 1 ? 1 : 0,
            'receiver_uids' => $receiverUids,
        ];
    }
}
