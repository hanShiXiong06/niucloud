<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseModel;

class ErpSettlement extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_settlement';
    protected $autoWriteTimestamp = false;

    /** 防止任一业务入口把空字符串写进站点级唯一幂等键。 */
    public function setRequestIdAttr($value): ?string
    {
        return ErpIdempotency::nullable($value);
    }
}
