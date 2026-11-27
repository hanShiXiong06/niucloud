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
        $config = $this->coreService->getConfig($this->site_id);
        $pricing = $config['device_pricing'];
        $config['device_pricing'] = [
            'enabled' => !empty($config['device_add_enabled']) && !empty($pricing['enabled']) ? 1 : 0,
            'quote_users' => \addon\hsx_recycle\app\service\core\order\CustomerQuoteService::assignmentEnabled($config)
                ? (new \addon\hsx_recycle\app\service\core\order\CustomerQuoteService())->getQuoteUsers((int)$this->site_id, $pricing['quote_user_ids']) : [],
        ];
        unset($config['device_bridge']);
        foreach (($config['work_wechat']['channels'] ?? []) as $key => $channel) {
            unset($config['work_wechat']['channels'][$key]['webhook_url']);
        }

        return $config;
    }
}
