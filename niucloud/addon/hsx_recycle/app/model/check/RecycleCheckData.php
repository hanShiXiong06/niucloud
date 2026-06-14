<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

/** 质检数据表(全ID映射)：一行=某型号的某检测项 */
class RecycleCheckData extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_data';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
