<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\order;

use core\base\BaseModel;

/**
 * 回收通知发送日志
 */
class RecycleNoticeLog extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_notice_log';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';
}
