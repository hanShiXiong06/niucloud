<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\service\core\WecomProviderConfigService;
use addon\hsx_wecom\app\service\core\WecomProviderCredentialService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class WecomProviderAdminService extends BaseAdminService
{
    public function info(): array
    {
        $this->assertPlatform();
        return (new WecomProviderConfigService())->info(true);
    }

    public function save(array $data): array
    {
        $this->assertPlatform();
        return (new WecomProviderConfigService())->save($data);
    }

    public function test(): array
    {
        $this->assertPlatform();
        $suite = (new WecomProviderConfigService())->active();
        if ($suite->isEmpty()) throw new CommonException('请先保存并启用企业微信服务商通道');
        (new WecomProviderCredentialService())->suiteAccessToken($suite, true);
        return [
            'connected' => true,
            'channel_code' => (string)$suite->channel_code,
            'suite_id' => (string)$suite->suite_id,
            'suite_ticket_at' => (int)$suite->suite_ticket_at,
            'message' => 'SuiteTicket 与服务商凭据验证成功',
        ];
    }

    private function assertPlatform(): void
    {
        if ((int)$this->site_id > 0) throw new CommonException('仅平台管理员可以维护企业微信服务商凭据');
    }
}
