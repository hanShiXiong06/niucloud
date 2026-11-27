<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

class RecycleCheckTemplate extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_template';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

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

    public function searchSceneAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('scene', '=', $value);
        }
    }

    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->whereLike('template_name|template_key', '%' . $value . '%');
        }
    }
}
