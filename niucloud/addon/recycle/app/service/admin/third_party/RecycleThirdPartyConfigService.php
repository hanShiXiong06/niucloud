<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\third_party;

use addon\recycle\app\service\core\third_party\RecycleThirdPartyConfigService as CoreRecycleThirdPartyConfigService;
use core\base\BaseAdminService;

/**
 * 管理端第三方配置中心
 */
class RecycleThirdPartyConfigService extends BaseAdminService
{
    private $coreService;

    public function __construct()
    {
        parent::__construct();
        $this->coreService = new CoreRecycleThirdPartyConfigService();
    }

    public function getConfig(): array
    {
        return $this->coreService->getConfig($this->site_id, true);
    }

    public function setConfig(array $data): bool
    {
        return $this->coreService->setConfig($this->site_id, $data);
    }

    public function getDefaultConfig(): array
    {
        return $this->coreService->getDefaultConfig();
    }
}
