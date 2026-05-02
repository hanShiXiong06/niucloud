<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\quotation_v2;

use addon\recycle\app\service\admin\quotation_v2\PriceService;
use core\base\BaseAdminController;

/**
 * 报价 2.0 价格调整
 */
class Price extends BaseAdminController
{
    public function adjust(int $id)
    {
        (new PriceService())->adjust($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function batchAdjust()
    {
        return success((new PriceService())->batchAdjust($this->request->post()));
    }
}
