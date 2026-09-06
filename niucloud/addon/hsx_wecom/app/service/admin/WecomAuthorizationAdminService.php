<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\service\core\WecomProviderAuthorizationService;
use addon\hsx_wecom\app\service\core\WecomProviderConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class WecomAuthorizationAdminService extends BaseAdminService
{
    public function status(): array
    {
        $this->assertSite();
        return (new WecomProviderConfigService())->siteStatus((int)$this->site_id);
    }

    public function start(string $returnUrl = ''): array
    {
        $this->assertSite();
        return (new WecomProviderAuthorizationService())->startInstall((int)$this->site_id, (int)$this->uid, $returnUrl);
    }

    public function check(): array
    {
        $this->assertSite();
        $status = (new WecomProviderConfigService())->siteStatus((int)$this->site_id);
        if (!in_array((string)($status['status'] ?? ''), ['authorized', 'changed', 'error'], true)) {
            return $status;
        }
        return (new WecomProviderAuthorizationService())->refreshSiteAuthorization((int)$this->site_id);
    }

    private function assertSite(): void
    {
        if ((int)$this->site_id <= 0) throw new CommonException('请进入具体站点后操作企业微信授权');
    }
}
