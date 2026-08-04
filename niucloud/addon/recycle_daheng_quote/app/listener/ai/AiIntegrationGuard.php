<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\listener\ai;

final class AiIntegrationGuard
{
    public static function allowed(int $siteId): bool
    {
        if ($siteId <= 0) return false;
        foreach ((array)event('HsxAiIntegrationAccessRequested', [
            'site_id' => $siteId,
            'integration_key' => 'recycle_daheng_quote',
        ]) as $response) {
            if (!is_array($response)) continue;
            if ((string)($response['consumer'] ?? '') === 'hsx_ai'
                && (string)($response['integration_key'] ?? '') === 'recycle_daheng_quote') {
                return !empty($response['allowed']);
            }
        }
        return false;
    }
}
