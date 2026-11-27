<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

class RecycleCheckOption extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_option';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
    protected $json = ['extra_config'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchFieldIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('field_id', '=', $value);
        }
    }

    public function searchIsShowAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_show', '=', $value);
        }
    }
}
