<?php
namespace addon\phone_shop\app\adminapi\controller\goods;

use addon\phone_shop\app\service\core\goods\CoreTierPricingService;
use core\base\BaseAdminController;

class TierPricing extends BaseAdminController
{
    public function info() { return success((new CoreTierPricingService())->policy((int)$this->request->siteId())); }
    public function save()
    {
        $data = $this->request->params([['enabled', 0], ['base_level_no', 0], ['rules', []]]);
        (new CoreTierPricingService())->save((int)$this->request->siteId(), $data);
        return success('SUCCESS');
    }
    public function preview()
    {
        $data = $this->request->params([['base_price', 0]]);
        return success((new CoreTierPricingService())->quote((int)$this->request->siteId(), $data['base_price']));
    }
}
