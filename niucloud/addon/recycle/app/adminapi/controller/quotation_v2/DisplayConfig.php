<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\quotation_v2;

use addon\recycle\app\service\admin\quotation_v2\DisplayConfigService;
use core\base\BaseAdminController;

/**
 * 报价 2.0 前台展示配置
 */
class DisplayConfig extends BaseAdminController
{
    public function info()
    {
        return success((new DisplayConfigService())->getConfig());
    }

    public function save()
    {
        (new DisplayConfigService())->setConfig($this->request->post());
        return success('EDIT_SUCCESS');
    }
}
