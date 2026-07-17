<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\core;

use app\service\core\sys\CoreConfigService;

final class MemberCardConfigService
{
    public const CONFIG_KEY = 'HSX_MEMBER_CARD_CONFIG';

    public function get(int $siteId): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        return $this->normalize(is_array($value) ? $value : []);
    }

    public function save(int $siteId, array $data): array
    {
        $config = $this->normalize(array_replace($this->get($siteId), $data));
        (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $config);
        return $config;
    }

    private function normalize(array $data): array
    {
        return [
            'allow_receivable' => (int)($data['allow_receivable'] ?? 1) === 1 ? 1 : 0,
            'allow_unpaid_redemption' => (int)($data['allow_unpaid_redemption'] ?? 1) === 1 ? 1 : 0,
            'default_capital_account_id' => max(0, (int)($data['default_capital_account_id'] ?? 0)),
            // v0.0.1 固定规则：只展示说明，不允许通过配置关闭。
            'require_mobile_name_confirmation' => 1,
            'default_redeem_times' => 1,
            'finance_retry_limit' => min(20, max(1, (int)($data['finance_retry_limit'] ?? 8))),
        ];
    }
}
