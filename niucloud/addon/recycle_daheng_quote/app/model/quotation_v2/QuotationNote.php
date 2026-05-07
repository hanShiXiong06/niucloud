<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\model\quotation_v2;

use core\base\BaseModel;

/**
 * 报价 2.0 附加说明内容
 */
class QuotationNote extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quotation_v2_note';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['merge_items', 'raw_item'];
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
}
