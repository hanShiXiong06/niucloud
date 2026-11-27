<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\check;

use core\base\BaseModel;

/**
 * 质检检测目录(扁平)——50万级拍机堂数据，按型号索引。
 */
class RecycleCheckCatalog extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_check_catalog';
    protected $json = ['options_json'];
    protected $jsonAssoc = true;
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
