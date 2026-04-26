<?php

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationModel;
use core\base\BaseAdminService;

/**
 * 报价型号服务层
 * Class QuotationModelService
 * @package addon\recycle\app\service\admin\quotation
 */
class QuotationModelService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleQuotationModel();
    }
    // list
    public function getList(array $where = [])
    {
        $field = 'id,site_id,goods_id,goods_name,status,create_at,update_at';
        $order = 'id desc';
        $list = $this->model->field($field)->order($order)->select()->toArray();
        return $list;
    }
}