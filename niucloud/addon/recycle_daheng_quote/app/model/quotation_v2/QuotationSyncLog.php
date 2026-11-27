<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\model\quotation_v2;

use core\base\BaseModel;

/**
 * 报价 2.0 同步日志
 */
class QuotationSyncLog extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quotation_v2_sync_log';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['request_params', 'raw_response', 'parsed_preview', 'stats', 'warnings'];
    protected $jsonAssoc = true;

    public function searchDatasetIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('dataset_id', '=', $value);
        }
    }
}
