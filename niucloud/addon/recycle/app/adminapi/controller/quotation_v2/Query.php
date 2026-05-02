<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\quotation_v2;

use addon\recycle\app\service\admin\quotation_v2\QueryService;
use core\base\BaseAdminController;

/**
 * 报价 2.0 查询
 */
class Query extends BaseAdminController
{
    public function models()
    {
        return success((new QueryService())->getModels($this->request->params([
            ['dataset_id', ''],
            ['model_name', ''],
        ])));
    }

    public function capacities()
    {
        return success((new QueryService())->getCapacities($this->request->params([
            ['dataset_id', ''],
            ['model_id', ''],
            ['capacity_name', ''],
        ])));
    }

    public function fields()
    {
        return success((new QueryService())->getFields($this->request->params([
            ['dataset_id', ''],
            ['field_type', ''],
            ['field_name', ''],
        ])));
    }

    public function prices()
    {
        return success((new QueryService())->getPrices($this->request->params([
            ['dataset_id', ''],
            ['model_id', ''],
            ['capacity_id', ''],
            ['field_id', ''],
            ['model_name', ''],
            ['capacity_name', ''],
            ['field_name', ''],
            ['price_date', ''],
        ])));
    }

    public function priceMatrix()
    {
        return success((new QueryService())->getPriceMatrix($this->request->params([
            ['dataset_id', ''],
            ['model_id', ''],
            ['capacity_id', ''],
            ['field_id', ''],
            ['model_name', ''],
            ['capacity_name', ''],
            ['field_name', ''],
            ['price_date', ''],
        ])));
    }

    public function notes()
    {
        return success((new QueryService())->getNotes($this->request->params([
            ['dataset_id', ''],
            ['model_id', ''],
            ['capacity_id', ''],
            ['model_name', ''],
            ['field_name', ''],
            ['field_type', ''],
        ])));
    }

    public function logs()
    {
        return success((new QueryService())->getLogs($this->request->params([
            ['dataset_id', ''],
        ])));
    }
}
