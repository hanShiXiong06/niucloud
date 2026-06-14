<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

/**
 * 全局“选项→级别”字典（normal/general/abnormal）
 */
class RecycleCheckOptionSeverity extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_option_severity';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
