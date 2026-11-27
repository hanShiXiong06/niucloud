<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 成色等级模型（扁平：九九新靓机/95新花机）
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\model\goods;

use core\base\BaseModel;

class GoodsGrade extends BaseModel
{
    protected $pk = 'grade_id';

    protected $name = 'phone_shop_goods_grade';
}
