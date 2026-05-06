<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\model;

use core\base\BaseModel;

class QuoteRow extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quote_spider_row';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['columns', 'source_prices', 'manual_prices', 'final_prices', 'raw_data'];
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

    public function searchItemIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('item_id', '=', $value);
        }
    }

    public function searchIsShowAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_show', '=', $value);
        }
    }

    public function searchFollowSourceAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('follow_source', '=', $value);
        }
    }

    public function searchHasUpdateAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('has_update', '=', $value);
        }
    }

    public function searchBrandAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('brand', '=', $value);
        }
    }

    public function searchTabAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('tab', '=', $value);
        }
    }

    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->whereLike('model_name|brand|tab|keywords|remark', '%' . $value . '%');
        }
    }
}
