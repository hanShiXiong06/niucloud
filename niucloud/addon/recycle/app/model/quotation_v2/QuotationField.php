<?php
declare(strict_types=1);

namespace addon\recycle\app\model\quotation_v2;

use addon\recycle\app\dict\quotation\QuotationV2Dict;
use core\base\BaseModel;

/**
 * 报价 2.0 字段项：价格项、附加说明项、报价说明
 */
class QuotationField extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quotation_v2_field';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['raw_data'];
    protected $jsonAssoc = true;
    protected $append = ['field_type_name'];

    public function getFieldTypeNameAttr($value, $data): string
    {
        return QuotationV2Dict::getFieldTypeName((string)($data['field_type'] ?? ''));
    }

    public function searchDatasetIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('dataset_id', '=', $value);
        }
    }

    public function searchFieldTypeAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('field_type', '=', $value);
        }
    }

    public function searchFieldNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('field_name', 'like', '%' . $value . '%');
        }
    }
}
