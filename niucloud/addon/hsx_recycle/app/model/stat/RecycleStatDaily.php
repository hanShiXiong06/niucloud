<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\stat;

use core\base\BaseModel;

/**
 * 回收按日流水汇总模型（趋势/绩效读它）
 * @property int $id
 * @property int $site_id
 * @property int $stat_date
 * @property string $metric_key
 * @property int $uid
 * @property int $value
 * @property string $amount
 */
class RecycleStatDaily extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_stat_daily';
}
