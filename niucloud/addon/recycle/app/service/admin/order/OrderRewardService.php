<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\order;

use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;

/**
 * 订单完成奖励服务
 */
class OrderRewardService extends BaseAdminService
{
    public function getConfig(): array
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, 'recycle_order_reward');
        if (empty($info)) {
            return [
                'is_enable' => 0,
                'reward_point' => 0,
                'reward_times' => 1,
            ];
        }
        return $info['value'] ?? [
            'is_enable' => 0,
            'reward_point' => 0,
            'reward_times' => 1,
        ];
    }

    public function setConfig(array $data): bool
    {
        $config = [
            'is_enable' => $data['is_enable'] ?? 0,
            'reward_point' => $data['reward_point'] ?? 0,
            'reward_times' => $data['reward_times'] ?? 1,
        ];
        (new CoreConfigService())->setConfig($this->site_id, 'recycle_order_reward', $config);
        return true;
    }
}
