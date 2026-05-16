<?php
declare(strict_types=1);

namespace addon\recycle\app\model\printer;

use core\base\BaseModel;

/**
 * 回收打印任务模型
 * Class RecyclePrintTask
 * @package addon\recycle\app\model\printer
 */
class RecyclePrintTask extends BaseModel
{
    protected $pk = 'task_id';

    protected $name = 'recycle_print_task';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';

    protected $json = ['payload', 'variables_snapshot', 'instruction_snapshot', 'response_snapshot'];

    protected $jsonAssoc = true;

    const STATUS_PENDING = 0;
    const STATUS_RUNNING = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAIL = 3;
    const STATUS_SKIPPED = 4;
    const STATUS_CANCELLED = 5;

    const MODE_AUTO = 'auto';
    const MODE_MANUAL = 'manual';
    const MODE_REPRINT = 'reprint';

    public static function statusList(): array
    {
        return [
            self::STATUS_PENDING => '待执行',
            self::STATUS_RUNNING => '执行中',
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAIL => '失败',
            self::STATUS_SKIPPED => '已跳过',
            self::STATUS_CANCELLED => '已取消',
        ];
    }
}
