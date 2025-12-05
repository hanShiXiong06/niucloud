<?php
declare(strict_types=1);

namespace addon\phone_shop\app\model\goods;

use core\base\BaseModel;

/**
 * 内存规格模型
 * Class Memory
 * @package addon\phone_shop\app\model\goods
 */
class Memory extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'spec_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'phone_shop_memory_spec';


    // 处理 group 关联
    public function group()
    {
        return $this->belongsTo(MemoryGroup::class, 'group_id', 'group_id');
    }
}
