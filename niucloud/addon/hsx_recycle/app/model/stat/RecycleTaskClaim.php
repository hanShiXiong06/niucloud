<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\stat;

use core\base\BaseModel;

/**
 * 回收任务认领模型（责任到人）
 * @property int $id
 * @property int $site_id
 * @property int $device_id
 * @property string $stage_key
 * @property int $assignee_uid
 * @property string $assignee_name
 */
class RecycleTaskClaim extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_task_claim';
}
