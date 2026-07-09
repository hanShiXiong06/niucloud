<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

class ErpGoodsCategory extends BaseModel
{
    protected $pk = 'category_id';
    protected $name = 'erp_goods_category';
    protected $autoWriteTimestamp = false;
}
