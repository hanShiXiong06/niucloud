<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpGoodsMetaService;
use core\base\BaseAdminController;

class ErpGoodsMeta extends BaseAdminController
{
    public function meta()
    {
        return success((new ErpGoodsMetaService())->meta());
    }
}
