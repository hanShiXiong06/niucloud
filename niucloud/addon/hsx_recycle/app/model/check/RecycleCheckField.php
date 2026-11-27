<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

class RecycleCheckField extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_field';
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

    public function searchTemplateIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('template_id', '=', $value);
        }
    }

    public function searchGroupIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('group_id', '=', $value);
        }
    }

    public function searchIsShowAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_show', '=', $value);
        }
    }
}
