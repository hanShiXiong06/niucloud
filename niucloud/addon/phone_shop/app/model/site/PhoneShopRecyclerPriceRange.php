<?php
declare(strict_types=1);

namespace addon\phone_shop\app\model\site;

use core\base\BaseModel;

/**
 * 回收商价格区间模型
 */
class PhoneShopRecyclerPriceRange extends BaseModel
{
    // 指定 id
    protected $pk = 'id';
    /**
     * 数据表名
     * @var string
     */
    protected $name = 'phone_shop_recycler_price_range';

    /**
     * 允许写入的字段
     * @var array
     */
    protected $allowField = [
        'config_id',
        'min_price',
        'max_price',
        'member_markup'
    ];

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
}


?>