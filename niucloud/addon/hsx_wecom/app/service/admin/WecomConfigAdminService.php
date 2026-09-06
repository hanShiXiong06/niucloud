<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\service\core\WecomClient;
use addon\hsx_wecom\app\service\core\WecomConfigService;
use addon\hsx_wecom\app\service\core\WecomDeliveryContextService;
use addon\hsx_wecom\app\service\core\WecomProviderConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class WecomConfigAdminService extends BaseAdminService
{
    public function getConfig(): array
    {
        if ((int)$this->site_id <= 0) {
            return [
                'is_platform' => 1,
                'provider' => (new WecomProviderConfigService())->info(true),
            ];
        }
        $config = (new WecomConfigService())->get((int)$this->site_id, true);
        $config['is_platform'] = 0;
        $config['provider'] = (new WecomProviderConfigService())->siteStatus((int)$this->site_id);
        return $config;
    }

    public function save(array $data): array
    {
        if ((int)$this->site_id <= 0) throw new CommonException('平台端请在服务商应用配置中保存');
        return (new WecomConfigService())->save((int)$this->site_id, $data);
    }

    public function test(): array
    {
        if ((int)$this->site_id <= 0) throw new CommonException('平台端请测试服务商通道');
        $context = (new WecomDeliveryContextService())->resolve((int)$this->site_id, true);
        return (new WecomClient())->test($context);
    }
}
