<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\api\controller\quotation;

use addon\recycle_daheng_quote\app\service\api\quotation\QuotationV2Service;
use core\base\BaseApiController;

/**
 * 报价 2.0 移动端展示接口
 */
class QuotationV2 extends BaseApiController
{
    /**
     * 可展示报价单
     */
    public function types()
    {
        $data = $this->request->params([
            ['limit', 20],
            ['dataset_ids', ''],
            ['quotation_ids', ''],
        ]);

        return success((new QuotationV2Service())->getTypes($data));
    }

    /**
     * 报价单明细，返回结构兼容旧移动端报价表格
     */
    public function lists()
    {
        $data = $this->request->params([
            ['dataset_id', ''],
            ['quotation_id', ''],
            ['model_name', ''],
            ['capacity_name', ''],
            ['price_date', ''],
        ]);

        return success((new QuotationV2Service())->getList($data));
    }
}
