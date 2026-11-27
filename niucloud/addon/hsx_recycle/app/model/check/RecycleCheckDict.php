<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

/** 质检参考表(字典：分类/检测项/选项)，option 行带 severity */
class RecycleCheckDict extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_dict';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
