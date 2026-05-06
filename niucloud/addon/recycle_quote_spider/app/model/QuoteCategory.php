<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\model;

use core\base\BaseModel;

class QuoteCategory extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_quote_spider_category';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['raw_data'];
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

    public function searchParentIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('parent_id', '=', $value);
        }
    }

    public function searchIsShowAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_show', '=', $value);
        }
    }

    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->whereLike('name', '%' . $value . '%');
        }
    }
}
