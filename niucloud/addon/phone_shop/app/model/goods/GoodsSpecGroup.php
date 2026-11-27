<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 规格分组模型（绑分类，如苹果内存/手表表盘尺寸）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\model\goods;

use core\base\BaseModel;

/**
 * 规格分组
 * @property int $group_id
 * @property int $category_id
 * @property string $label
 */
class GoodsSpecGroup extends BaseModel
{
    protected $pk = 'group_id';

    protected $name = 'phone_shop_goods_spec_group';

    /** 分组下的规格子项 */
    public function items()
    {
        return $this->hasMany(GoodsSpecItem::class, 'group_id', 'group_id')->order('sort asc, item_id asc');
    }
}
