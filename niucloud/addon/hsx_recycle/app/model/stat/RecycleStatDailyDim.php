<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\stat;

use core\base\BaseModel;

/**
 * 回收每日维度汇总模型（型号/分类/成色/来源分布读它）
 * @property int $id
 * @property int $site_id
 * @property int $stat_date
 * @property string $dim_type
 * @property string $dim_value
 * @property int $cnt
 * @property string $amount
 */
class RecycleStatDailyDim extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'recycle_stat_daily_dim';
}
