<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\model\quotation_v2;

use core\base\BaseModel;

/**
 * 报价 2.0 容量
 */
class QuotationCapacity extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quotation_v2_capacity';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['raw_data'];
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

    public function searchCapacityNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('capacity_name', 'like', '%' . $value . '%');
        }
    }
}
