<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\order;

use core\base\BaseModel;

/**
 * 回收代卖日志模型
 */
class RecycleConsignmentLog extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_consignment_log';
    protected $autoWriteTimestamp = false;
    protected $json = ['before_data', 'after_data'];
}
