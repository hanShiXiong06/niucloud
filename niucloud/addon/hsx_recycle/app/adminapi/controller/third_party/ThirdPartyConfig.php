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
        if ($serviceType === 'express_order') {
            try {
                $provider = (new \addon\hsx_recycle\app\service\core\express\ExpressProviderRegistry())->resolve($this->site_id);
                $ready = $provider->healthCheck($this->site_id);
                return success(['success' => $ready, 'provider' => $provider->key(), 'provider_name' => $provider->name(),
                    'verification_state' => $ready ? 'configured_not_verified' : 'incomplete',
                    'message' => $ready ? '已保存的配置格式检查通过；未发起真实寄件，账号权限、承运范围和余额仍需验证' : '已保存的配置不完整或付款方式不受支持，请补充后重新保存']);
            } catch (\Throwable $e) {
                return success(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        return success((new CoreThirdPartyService())->testConnection($this->site_id, $serviceType));
    }
}
