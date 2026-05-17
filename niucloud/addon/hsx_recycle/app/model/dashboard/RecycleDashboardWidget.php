<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\dashboard;

use core\base\BaseModel;

/**
 * 回收首页组件配置模型
 * Class RecycleDashboardWidget
 * @package addon\hsx_recycle\app\model\dashboard
 */
class RecycleDashboardWidget extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'widget_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_dashboard_widget';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * JSON 字段
     * @var array
     */
    protected $json = ['role_ids', 'uids', 'config'];

    /**
     * JSON 返回数组
     * @var bool
     */
    protected $jsonAssoc = true;
}
