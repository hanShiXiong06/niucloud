<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpGoodsMetaService;
use core\base\BaseAdminController;

class ErpGoodsMeta extends BaseAdminController
{
    public function meta()
    {
        $params = $this->request->params([
            ['category_id', 0],
            ['category_path', []],
        ]);
        return success((new ErpGoodsMetaService())->meta($params));
    }
}
