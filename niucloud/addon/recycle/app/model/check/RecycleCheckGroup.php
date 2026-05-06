<?php
declare(strict_types=1);

namespace addon\recycle\app\model\check;

use core\base\BaseModel;

class RecycleCheckGroup extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_group';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchTemplateIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('template_id', '=', $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }
}
