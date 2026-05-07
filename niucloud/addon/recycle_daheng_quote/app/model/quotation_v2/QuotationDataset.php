<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\model\quotation_v2;

use core\base\BaseModel;

/**
 * 报价数据集
 */
class QuotationDataset extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quotation_v2_dataset';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['request_params', 'parse_rule', 'last_sync_summary'];
    protected $jsonAssoc = true;

    public function searchQuotationIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('quotation_id', '=', $value);
        }
    }

    public function searchDatasetNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('dataset_name', 'like', '%' . $value . '%');
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }
}
