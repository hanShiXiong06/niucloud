<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

/**
 * 质检目录导入批次
 */
class RecycleCheckImportBatch extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_import_batch';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
