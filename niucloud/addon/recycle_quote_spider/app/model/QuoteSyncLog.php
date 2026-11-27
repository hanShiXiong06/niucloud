<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\model;

use core\base\BaseModel;

class QuoteSyncLog extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quote_spider_sync_log';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['summary'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchSourceIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('source_id', '=', $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }
}
