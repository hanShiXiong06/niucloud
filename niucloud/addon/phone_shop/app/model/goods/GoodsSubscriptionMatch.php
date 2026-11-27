<?php

namespace addon\phone_shop\app\model\goods;

use core\base\BaseModel;

/**
 * 商品订阅命中及通知留痕
 */
class GoodsSubscriptionMatch extends BaseModel
{
    protected $pk = 'match_id';

    protected $name = 'phone_shop_goods_subscription_match';

    // 留痕表仅有 create_time、notify_time，不写入不存在的 update_time。
    protected $updateTime = false;
}
