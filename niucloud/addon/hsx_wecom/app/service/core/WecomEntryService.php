<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\support\WecomEntryTicket;

final class WecomEntryService
{
    public function issueTicket(int $siteId, string $miniappPath, string $routeKey = '', int $ttl = WecomEntryTicket::DEFAULT_TTL): string
    {
        return WecomEntryTicket::issue($siteId, $miniappPath, $routeKey, $ttl);
    }

    /**
     * 生成企业微信消息中配置的小程序 pagepath（不带开头斜杠）。
     */
    public function issuePagePath(int $siteId, string $miniappPath, string $routeKey = '', int $ttl = WecomEntryTicket::DEFAULT_TTL): string
    {
        $ticket = $this->issueTicket($siteId, $miniappPath, $routeKey, $ttl);
        return 'app/pages/wecom/entry?site_id=' . $siteId . '&ticket=' . rawurlencode($ticket);
    }

    public function verifyTicket(string $ticket): array
    {
        return WecomEntryTicket::verify($ticket);
    }
}
