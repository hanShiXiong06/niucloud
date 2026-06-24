<?php

namespace addon\sd_xiaoyuan\app\adminapi\service;

use addon\sd_xiaoyuan\app\service\core\SignConfigCoreService;
use core\base\BaseAdminService;

/**
 * 签到配置服务（后台）
 */
class SignConfigService extends BaseAdminService
{
    protected function core(): SignConfigCoreService
    {
        return new SignConfigCoreService();
    }

    public function getConfig()
    {
        return $this->core()->getRewardsList((int)$this->site_id);
    }

    public function saveConfig(array $config): void
    {
        $this->core()->saveRewardsList((int)$this->site_id, $config);
    }

    public function getDayReward(int $day)
    {
        return $this->core()->getDayReward((int)$this->site_id, $day);
    }
}
