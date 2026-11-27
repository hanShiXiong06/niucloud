<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\stat;

use core\base\BaseModel;

/**
 * 回收实时态计数模型（看板/待办读它，免聚合）
 * @property int $id
 * @property int $site_id
 * @property string $metric_key
 * @property int $uid
 * @property int $value
 */
class RecycleStatCurrent extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_stat_current';
}
