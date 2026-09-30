<?php

namespace addon\phone_shop\app\model\agent;

use core\base\BaseModel;

/** 跨站分类映射与待处理台账。 */
class PhoneShopCategoryMapping extends BaseModel
{
    protected $pk = 'mapping_id';
    protected $name = 'phone_shop_category_mapping';
    protected $json = ['candidate_ids', 'master_snapshot'];
    protected $jsonAssoc = true;
}
