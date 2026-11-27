<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\printer;

use core\base\BaseModel;

/**
 * 回收打印日志模型
 * Class RecyclePrintLog
 * @package addon\hsx_recycle\app\model\printer
 */
class RecyclePrintLog extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'log_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_print_log';

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
     * @var false
     */
    protected $updateTime = false;

    /**
     * JSON字段
     * @var array
     */
    protected $json = ['plan_snapshot', 'request_snapshot', 'response_snapshot'];

    /**
     * JSON数据返回数组
     * @var bool
     */
    protected $jsonAssoc = true;
}
