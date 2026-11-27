<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

/** AI 未安装、全局停用或商城接入锁关闭时均拒绝通信。 */
final class AiIntegrationGuard
{
    public static function allowed(int $siteId): bool
    {
        if ($siteId <= 0) return false;
        foreach ((array)event('HsxAiIntegrationAccessRequested', [
            'site_id' => $siteId,
            'integration_key' => 'phone_shop',
        ]) as $response) {
            if (!is_array($response)) continue;
            if ((string)($response['consumer'] ?? '') === 'hsx_ai'
                && (string)($response['integration_key'] ?? '') === 'phone_shop') {
                return !empty($response['allowed']);
            }
        }
        return false;
    }
}
