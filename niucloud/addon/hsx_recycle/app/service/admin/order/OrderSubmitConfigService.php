<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService as CoreOrderSubmitConfigService;
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
        $config = $this->coreService->getConfig($this->site_id);
        $config['quote_user_options'] = (new \addon\hsx_recycle\app\service\core\order\CustomerQuoteService())->getStaffOptions((int)$this->site_id);
        $pricingIds = array_column(\addon\hsx_recycle\app\service\admin\stat\TaskService::forSite((int)$this->site_id)
            ->getAssignableUsers(\addon\hsx_recycle\app\dict\stat\RecycleStageDict::STAGE_PRICE, true), 'uid');
        foreach ($config['quote_user_options'] as &$user) $user['can_price'] = in_array($user['uid'], $pricingIds, true);
        unset($user);
        return $config;
    }

    public function setConfig(array $data): bool
    {
        return $this->coreService->setConfig($this->site_id, $data);
    }

    /** 录单和签收仅取人员及分支开关，不暴露完整配置或通知凭据。 */
    public function quoteOptions(): array
    {
        $service = new \addon\hsx_recycle\app\service\core\order\CustomerQuoteService();
        $enabled = $service::assignmentEnabled($this->coreService->getConfig($this->site_id));
        $users = $service->getStaffOptions((int)$this->site_id);
        $pricingIds = array_column(\addon\hsx_recycle\app\service\admin\stat\TaskService::forSite((int)$this->site_id)
            ->getAssignableUsers(\addon\hsx_recycle\app\dict\stat\RecycleStageDict::STAGE_PRICE, true), 'uid');
        return ['assign_to_quote_user' => $enabled ? 1 : 0,
            'users' => array_map(static function (array $user) use ($enabled, $pricingIds): array {
                $user['disabled'] = $enabled && !in_array($user['uid'], $pricingIds, true);
                return $user;
            }, $users)];
    }
}
