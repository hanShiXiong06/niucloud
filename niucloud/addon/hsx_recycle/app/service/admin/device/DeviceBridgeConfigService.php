<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device;

use addon\hsx_recycle\app\service\core\device\DeviceBridgeDownloads;
use app\dict\sys\AppTypeDict;
use core\base\BaseAdminService;
use core\exception\CommonException;

class DeviceBridgeConfigService extends BaseAdminService
{
    public function info(): array
    {
        $this->assertPlatform();
        return DeviceBridgeDownloads::getConfig();
    }

    public function save(array $data): bool
    {
        $this->assertPlatform();
        return DeviceBridgeDownloads::setConfig($data);
    }

    private function assertPlatform(): void
    {
        if ((int)$this->site_id !== 0 || $this->app_type !== AppTypeDict::ADMIN) {
            throw new CommonException('仅 SaaS 平台管理员可以维护设备桥下载配置');
        }
    }
}
