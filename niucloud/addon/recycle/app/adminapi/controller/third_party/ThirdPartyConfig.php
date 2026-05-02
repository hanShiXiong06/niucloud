<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\third_party;

use addon\recycle\app\service\admin\third_party\RecycleThirdPartyConfigService;
use addon\recycle\app\service\admin\third_party\ThirdPartyCapabilityService;
use core\base\BaseAdminController;

/**
 * 第三方配置中心
 */
class ThirdPartyConfig extends BaseAdminController
{
    public function getConfig()
    {
        return success((new RecycleThirdPartyConfigService())->getConfig());
    }

    public function setConfig()
    {
        (new RecycleThirdPartyConfigService())->setConfig($this->request->post());
        return success();
    }

    public function getDefaultConfig()
    {
        return success((new RecycleThirdPartyConfigService())->getDefaultConfig());
    }

    public function overview()
    {
        return success((new ThirdPartyCapabilityService())->overview());
    }
}
