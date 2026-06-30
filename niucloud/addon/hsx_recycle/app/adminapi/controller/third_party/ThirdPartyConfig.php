<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\third_party;

use addon\hsx_recycle\app\service\admin\third_party\RecycleThirdPartyConfigService;
use addon\hsx_recycle\app\service\admin\third_party\ThirdPartyCapabilityService;
use addon\hsx_recycle\app\service\core\third_party\CoreThirdPartyService;
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

    /**
     * 测试某能力当前指定服务商的连通就绪状态
     */
    public function testConnection()
    {
        $serviceType = (string)$this->request->param('capability', '');
        if ($serviceType === '') {
            return fail('请指定要测试的能力');
        }
        return success((new CoreThirdPartyService())->testConnection($this->site_id, $serviceType));
    }
}
