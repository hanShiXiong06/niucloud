<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\order;

use app\model\sys\SysUser;
use core\base\BaseModel;

/**
 * 回收设备打款记录
 */
class RecycleDevicePayment extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_device_payment';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = false;

    public function operator()
    {
        return $this->belongsTo(SysUser::class, 'pay_uid', 'uid')->field('uid,username,real_name');
    }
}
