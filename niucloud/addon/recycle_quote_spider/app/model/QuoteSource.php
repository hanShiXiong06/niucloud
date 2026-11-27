<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\model;

use core\base\BaseModel;

class QuoteSource extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quote_spider_source';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['request_config'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }

    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->whereLike('source_name|provider|source_key', '%' . $value . '%');
        }
    }
}
