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

    /**
     * 报价单详情（生成报价单用，返回与 spider 一致的形状）
     */
    public function detail()
    {
        $data = $this->request->params([
            ['dataset_id', ''],
            ['quotation_id', ''],
            ['price_date', ''],
        ]);
        return success((new QuotationV2Service())->getDetail($data));
    }

    /**
     * 单行(容量)价格历史（趋势弹窗）
     */
    public function priceHistory()
    {
        $id = (int)$this->request->param('id', 0);
        $days = (int)$this->request->param('days', 30);
        return success((new QuotationV2Service())->getPriceHistory($id, $days));
    }

    /**
     * 报价单生成会员权益校验
     */
    public function reportPermission()
    {
        return success((new QuotationV2Service())->reportPermission());
    }
}
