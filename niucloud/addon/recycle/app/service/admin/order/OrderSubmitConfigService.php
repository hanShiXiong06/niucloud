<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\order;

use addon\recycle\app\service\core\order\OrderSubmitConfigService as CoreOrderSubmitConfigService;
use core\base\BaseAdminService;

/**
 * 管理端下单配置
 */
class OrderSubmitConfigService extends BaseAdminService
{
    private CoreOrderSubmitConfigService $coreService;

    public function __construct()
    {
        parent::__construct();
        $this->coreService = new CoreOrderSubmitConfigService();
    }

    public function getConfig(): array
    {
        return $this->coreService->getConfig($this->site_id);
    }

    public function setConfig(array $data): bool
    {
        return $this->coreService->setConfig($this->site_id, $data);
    }
}
