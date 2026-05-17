<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\api\order;

use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService as CoreOrderSubmitConfigService;
use core\base\BaseApiService;

/**
 * 用户端下单配置
 */
class OrderSubmitConfigService extends BaseApiService
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
}
