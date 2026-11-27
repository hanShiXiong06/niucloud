<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\template;

use core\base\BaseModel;

/**
 * 回收型号模板绑定
 */
class RecycleTemplateBinding extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_template_binding';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';
}
