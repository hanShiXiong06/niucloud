<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\model\quotation_v2;

use core\base\BaseModel;

/**
 * 报价 2.0 价格
 */
class QuotationPrice extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quotation_v2_price';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['raw_item'];
    protected $jsonAssoc = true;

    public function searchDatasetIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('dataset_id', '=', $value);
        }
    }

    public function searchModelIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('model_id', '=', $value);
        }
    }

    public function searchCapacityIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('capacity_id', '=', $value);
        }
    }

    public function searchFieldIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('field_id', '=', $value);
        }
    }
}
